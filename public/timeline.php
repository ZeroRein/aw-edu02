<?php
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// ログインユーザー情報取得
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// --- 投稿処理 (POST) ---
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {

  // 1. 画像ファイルをサーバーに保存し、ファイル名のリストを作る
  $uploaded_filenames = [];

  if (isset($_POST['image_base64']) && is_array($_POST['image_base64'])) {
      foreach ($_POST['image_base64'] as $base64) {
          if (empty($base64)) continue;

          // Base64デコード
          $base64 = preg_replace('/^data:.+base64,/', '', $base64);
          $image_binary = base64_decode($base64);

          // ファイル名生成 (一意にする)
          $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
          $filepath =  '/var/www/upload/image/' . $image_filename;

          // 保存
          file_put_contents($filepath, $image_binary);
          $uploaded_filenames[] = $image_filename;
      }
  }

  // 2. データベースへの保存 (トランザクション)
  try {
      $dbh->beginTransaction();

      // (A) bbs_entries に本文を保存
      // ※ image_filename カラムはもう使わないのでINSERT対象から外す
      $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body) VALUES (:user_id, :body)");
      $insert_sth->execute([
          ':user_id' => $_SESSION['login_user_id'],
          ':body' => $_POST['body'],
      ]);

      // (B) 直前のINSERTで発行されたIDを取得
      $entryId = $dbh->lastInsertId();

      // (C) entry_images に画像を保存
      if (!empty($uploaded_filenames)) {
          $image_sth = $dbh->prepare("INSERT INTO entry_images (entry_id, image_filename) VALUES (:entry_id, :filename)");
          
          foreach ($uploaded_filenames as $filename) {
              $image_sth->execute([
                  ':entry_id' => $entryId,  // 親のID
                  ':filename' => $filename,
              ]);
          }
      }

      $dbh->commit();

  } catch (Exception $e) {
      $dbh->rollBack();
      // 本来はここでエラーログなどを出す
  }

  header("HTTP/1.1 303 See Other");
  header("Location: ./timeline.php");
  return;
}
?>

<div>
  現在 <?= htmlspecialchars($user['name']) ?> (ID: <?= $user['id'] ?>) さんでログイン中
</div>
<div style="margin-bottom: 1em;">
  <a href="/setting/index.php">設定画面</a> / <a href="/users.php">会員一覧画面</a>
</div>

<form method="POST" action="./timeline.php">
  <textarea name="body" required placeholder="今なにしてる？"></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image[]" id="imageInput" multiple>
    <button type="button" id="removeImageButton" style="display: none; margin-left: 10px;">画像を削除</button>
  </div>
  
  <div id="hiddenInputsContainer"></div>

  <div id="imagePreviewContainer" style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 10px;"></div>
  
  <button type="submit">送信</button>
</form>
<hr>

<dl id="entryTemplate" style="display: none; margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
  <dt>番号</dt>
  <dd data-role="entryIdArea"></dd>
  <dt>投稿者</dt>
  <dd>
    <img src="" data-role="entryUserIconImage" style="display: none; width: 30px; height: 30px; object-fit: cover; border-radius: 50%; vertical-align: middle; margin-right: 5px;">
    <a href="" data-role="entryUserAnchor"></a>
  </dd>
  <dt>日時</dt>
  <dd data-role="entryCreatedAtArea"></dd>
  <dt>内容</dt>
  <dd data-role="entryBodyArea"></dd>
  <dt>画像</dt>
  <dd data-role="entryImageArea"></dd>
</dl>

<div id="entriesRenderArea"></div>
<div id="loadingIndicator" style="display: none;">読み込み中...</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const entryTemplate = document.getElementById('entryTemplate');
  const entriesRenderArea = document.getElementById('entriesRenderArea');
  const loadingIndicator = document.getElementById('loadingIndicator');

  let lastId = null;
  let isLoading = false;
  let isFinished = false;

  // --- 投稿取得・表示ロジック ---
  const fetchEntries = () => {
    if (isLoading || isFinished) return;

    isLoading = true;
    loadingIndicator.style.display = 'block';

    const request = new XMLHttpRequest();
    let url = '/timeline_json.php'; // 先ほど修正したJSONファイルを呼ぶ
    if (lastId !== null) {
        url += '?last_id=' + lastId;
    }

    request.open('GET', url, true);
    request.responseType = 'json';

    request.onload = (event) => {
      const response = event.target.response;
      
      if (!response.entries || response.entries.length === 0) {
          isFinished = true;
          loadingIndicator.style.display = 'none';
          isLoading = false;
          return;
      }

      response.entries.forEach((entry) => {
        const entryCopied = entryTemplate.cloneNode(true);
        entryCopied.style.display = 'block';

        entryCopied.querySelector('[data-role="entryIdArea"]').innerText = entry.id.toString();
        
        // アイコン
        if (entry.user_icon_filename) {
            const iconImage = entryCopied.querySelector('[data-role="entryUserIconImage"]');
            iconImage.src = '/image/' + entry.user_icon_filename;
            iconImage.style.display = 'inline-block';
        }

        entryCopied.querySelector('[data-role="entryUserAnchor"]').innerText = entry.user_name;
        entryCopied.querySelector('[data-role="entryUserAnchor"]').href = entry.user_profile_url;
        entryCopied.querySelector('[data-role="entryCreatedAtArea"]').innerText = entry.created_at;
        entryCopied.querySelector('[data-role="entryBodyArea"]').innerHTML = entry.body;

        // ▼ 投稿画像の表示処理 (複数対応)
        // timeline_json.php からは "img1.png,img2.png" のようにカンマ区切りで来る
        if (entry.image_filename) {
            const imageContainer = entryCopied.querySelector('[data-role="entryImageArea"]');
            const filenames = entry.image_filename.split(','); // カンマで分割して配列にする

            filenames.forEach(filename => {
                if(!filename) return; // 空文字チェック
                
                const img = document.createElement('img');
                img.src = '/image/' + filename;
                img.style.maxWidth = '200px';
                img.style.maxHeight = '200px';
                img.style.display = 'block';
                img.style.marginBottom = '5px';
                img.style.borderRadius = '4px';
                
                imageContainer.appendChild(img);
            });
        }

        entriesRenderArea.appendChild(entryCopied);
        lastId = entry.id;
      });

      isLoading = false;
      loadingIndicator.style.display = 'none';
    };

    request.send();
  };

  fetchEntries();

  window.addEventListener('scroll', () => {
    const scrollHeight = document.documentElement.scrollHeight;
    const scrollPosition = window.innerHeight + window.scrollY;
    if ((scrollHeight - scrollPosition) < 100) {
      fetchEntries();
    }
  });


  // --- 画像選択・縮小・プレビューロジック (複数対応) ---
  const imageInput = document.getElementById("imageInput");
  const removeImageButton = document.getElementById("removeImageButton");
  const hiddenInputsContainer = document.getElementById("hiddenInputsContainer");
  const imagePreviewContainer = document.getElementById("imagePreviewContainer");

  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) return;
    
    // 枚数制限チェック
    if (imageInput.files.length > 4) {
        alert("画像は最大4枚までです");
        imageInput.value = '';
        return;
    }

    // リセット
    hiddenInputsContainer.innerHTML = '';
    imagePreviewContainer.innerHTML = '';

    // 選択されたファイルを1つずつ処理
    Array.from(imageInput.files).forEach((file) => {
        if (!file.type.startsWith('image/')) return;

        const reader = new FileReader();
        const image = new Image();

        reader.onload = () => {
            image.onload = () => {
                // 縮小計算
                const originalWidth = image.naturalWidth;
                const originalHeight = image.naturalHeight;
                const maxLength = 1000;
                let width, height;

                if (originalWidth <= maxLength && originalHeight <= maxLength) {
                    width = originalWidth; height = originalHeight;
                } else if (originalWidth > originalHeight) {
                    width = maxLength; height = maxLength * originalHeight / originalWidth;
                } else {
                    width = maxLength * originalWidth / originalHeight; height = maxLength;
                }

                // Canvas作成
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                
                // プレビュー用の見た目スタイル
                canvas.style.maxWidth = '150px'; 
                canvas.style.maxHeight = '150px';
                canvas.style.objectFit = 'contain';
                canvas.style.border = '1px solid #ddd';

                const context = canvas.getContext("2d");
                context.drawImage(image, 0, 0, width, height);

                // プレビュー表示
                imagePreviewContainer.appendChild(canvas);

                // 送信データ作成 (配列形式 name="image_base64[]")
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'image_base64[]';
                input.value = canvas.toDataURL();
                hiddenInputsContainer.appendChild(input);

                // 削除ボタン表示
                removeImageButton.style.display = 'inline-block';
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
  });

  // 画像削除ボタン
  removeImageButton.addEventListener("click", () => {
    imageInput.value = '';
    hiddenInputsContainer.innerHTML = '';
    imagePreviewContainer.innerHTML = '';
    removeImageButton.style.display = 'none';
  });
});
</script>
