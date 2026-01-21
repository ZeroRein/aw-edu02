<?php
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {
  $image_filename = null;
  if (!empty($_POST['image_base64'])) {
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);
    $image_binary = base64_decode($base64);
    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
    $filepath =  '/var/www/upload/image/' . $image_filename;
    file_put_contents($filepath, $image_binary);
  }

  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
  $insert_sth->execute([
    ':user_id' => $_SESSION['login_user_id'],
    ':body' => $_POST['body'],
    ':image_filename' => $image_filename,
  ]);

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
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <input id="imageBase64Input" type="hidden" name="image_base64">
  <canvas id="imageCanvas" style="display: none;"></canvas>
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
  <dd>
    <img src="" style="display: none; max-width: 200px;" data-role="entryImageArea">
  </dd>
</dl>
<div id="entriesRenderArea"></div>

<div id="loadingIndicator" style="display: none;">読み込み中...</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const entryTemplate = document.getElementById('entryTemplate');
  const entriesRenderArea = document.getElementById('entriesRenderArea');
  const loadingIndicator = document.getElementById('loadingIndicator');

  let lastId = null; // 最後に読み込んだ投稿のIDを記録
  let isLoading = false; // 重複読み込み防止用フラグ
  let isFinished = false; // 全件読み終わったかどうかのフラグ

  // 投稿を取得して描画する関数
  const fetchEntries = () => {
    if (isLoading || isFinished) return; // 読み込み中または終了なら何もしない

    isLoading = true;
    loadingIndicator.style.display = 'block';

    const request = new XMLHttpRequest();
    // lastIdがあればURLパラメータとして付与する
    let url = '/timeline_json.php';
    if (lastId !== null) {
        url += '?last_id=' + lastId;
    }

    request.open('GET', url, true);
    request.responseType = 'json';

    request.onload = (event) => {
      const response = event.target.response;
      
      // データが空なら終了
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
        
        // ユーザーアイコン
        if (entry.user_icon_filename) {
            const iconImage = entryCopied.querySelector('[data-role="entryUserIconImage"]');
            iconImage.src = '/image/' + entry.user_icon_filename;
            iconImage.style.display = 'inline-block';
        }

        entryCopied.querySelector('[data-role="entryUserAnchor"]').innerText = entry.user_name;
        entryCopied.querySelector('[data-role="entryUserAnchor"]').href = entry.user_profile_url;
        entryCopied.querySelector('[data-role="entryCreatedAtArea"]').innerText = entry.created_at;
        entryCopied.querySelector('[data-role="entryBodyArea"]').innerHTML = entry.body;

        // 投稿画像
        if (entry.image_filename) {
            const imageArea = entryCopied.querySelector('[data-role="entryImageArea"]');
            imageArea.src = '/image/' + entry.image_filename;
            imageArea.style.display = 'block';
        }

        entriesRenderArea.appendChild(entryCopied);
        
        // 最後に読み込んだIDを更新
        lastId = entry.id;
      });

      isLoading = false;
      loadingIndicator.style.display = 'none';
    };

    request.send();
  };

  // 初回読み込み
  fetchEntries();

  // スクロールイベント監視
  window.addEventListener('scroll', () => {
    // 画面の一番下から 100px 以内の位置に来たら次のデータを読み込む
    const scrollHeight = document.documentElement.scrollHeight;
    const scrollPosition = window.innerHeight + window.scrollY;
    
    if ((scrollHeight - scrollPosition) < 100) {
      fetchEntries();
    }
  });


  // --- 以下、投稿画像縮小用スクリプト（変更なし） ---
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) return;
    const file = imageInput.files[0];
    if (!file.type.startsWith('image/')) return;

    const imageBase64Input = document.getElementById("imageBase64Input");
    const canvas = document.getElementById("imageCanvas");
    const reader = new FileReader();
    const image = new Image();
    reader.onload = () => {
      image.onload = () => {
        const originalWidth = image.naturalWidth;
        const originalHeight = image.naturalHeight;
        const maxLength = 1000;
        if (originalWidth <= maxLength && originalHeight <= maxLength) {
            canvas.width = originalWidth;
            canvas.height = originalHeight;
        } else if (originalWidth > originalHeight) {
            canvas.width = maxLength;
            canvas.height = maxLength * originalHeight / originalWidth;
        } else {
            canvas.width = maxLength * originalWidth / originalHeight;
            canvas.height = maxLength;
        }
        const context = canvas.getContext("2d");
        context.drawImage(image, 0, 0, canvas.width, canvas.height);
        imageBase64Input.value = canvas.toDataURL();
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
});
</script>
