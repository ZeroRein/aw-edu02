<?php
// DB接続
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

session_start();

// ログイン確認
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// ユーザー情報取得
$user_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_sth->fetch();

// --- 投稿処理 (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['body'])) {
    
    // 画像保存処理
    $uploaded_filenames = [];
    if (isset($_POST['image_base64']) && is_array($_POST['image_base64'])) {
        $count = 0;
        foreach ($_POST['image_base64'] as $base64) {
            if ($count >= 4) break;
            if (empty($base64)) continue;
            
            // Base64デコード
            $base64 = preg_replace('/^data:.+base64,/', '', $base64);
            $image_binary = base64_decode($base64);
            
            // ファイル名生成
            $filename = time() . bin2hex(random_bytes(10)) . '.png';
            $filepath = '/var/www/upload/image/' . $filename;
            
            // 保存
            file_put_contents($filepath, $image_binary);
            $uploaded_filenames[] = $filename;

            $count++;
        }
    }

    try {
        $dbh->beginTransaction();

        // 1. 本文保存
        $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body) VALUES (:user_id, :body)");
        $insert_sth->execute([
            ':user_id' => $_SESSION['login_user_id'],
            ':body' => $_POST['body']
        ]);
        
        // 2. 直前のID取得
        $entryId = $dbh->lastInsertId();

        // 3. 画像データ保存 (entry_imagesテーブル)
        if (!empty($uploaded_filenames)) {
            $img_sth = $dbh->prepare("INSERT INTO entry_images (entry_id, image_filename) VALUES (:eid, :fname)");
            foreach ($uploaded_filenames as $fname) {
                $img_sth->execute([
                    ':eid' => $entryId, 
                    ':fname' => $fname
                ]);
            }
        }
        $dbh->commit();

    } catch (Exception $e) {
        $dbh->rollBack();
    }

    header("Location: ./timeline.php");
    return;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>タイムライン</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .entry { border-bottom: 1px solid #ddd; padding: 15px 0; }
        .entry-header { display: flex; align-items: center; margin-bottom: 5px; }
        .user-icon { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px; background: #eee; }
        .user-name { font-weight: bold; text-decoration: none; color: #333; }
        .entry-date { color: #888; font-size: 0.85em; margin-left: auto; }
        .entry-body { margin: 5px 0 10px 50px; white-space: pre-wrap; }
        .entry-images { margin-left: 50px; display: flex; flex-wrap: wrap; gap: 10px; }
        .entry-image { max-width: 200px; max-height: 200px; border-radius: 5px; border: 1px solid #eee; }
        
        #preview-area canvas { max-width: 100px; max-height: 100px; border: 1px solid #ccc; margin: 5px; }
    </style>
</head>
<body>

<div>
  現在 <strong><?= htmlspecialchars($user['name']) ?></strong> (ID: <?= $user['id'] ?>) さんでログイン中
</div>
<div style="margin-bottom: 1em;">
  <a href="/users.php">会員一覧(フォローする)</a> | 
  <a href="/follow_list.php">フォロー中</a> | 
  <a href="/follower_list.php">フォロワー</a> | 
  <a href="/setting/index.php">設定</a> |
  <a href="/login.php">ログアウト</a>
</div>

<form method="POST" action="./timeline.php" style="background: #f9f9f9; padding: 15px; border-radius: 8px;">
  <textarea name="body" required placeholder="今なにしてる？" style="width: 100%; height: 60px; box-sizing: border-box;"></textarea>
  
  <div style="margin-top: 10px;">
    <input type="file" accept="image/*" id="imageInput" multiple>
    <button type="button" id="clearImagesBtn" style="display:none;">画像をクリア</button>
  </div>

  <div id="preview-area" style="margin-top: 10px;"></div>
  <div id="hidden-inputs"></div>
  
  <div style="margin-top: 10px; text-align: right;">
    <button type="submit" style="padding: 5px 20px;">送信</button>
  </div>
</form>

<hr>

<div id="entriesRenderArea"></div>
<div id="loadingIndicator" style="display: none; text-align: center; color: #888;">読み込み中...</div>

<template id="entryTemplate">
  <div class="entry">
    <div class="entry-header">
      <img src="" class="user-icon" data-role="icon">
      <a href="" class="user-name" data-role="name"></a>
      <span class="entry-date" data-role="date"></span>
    </div>
    <div class="entry-body" data-role="body"></div>
    <div class="entry-images" data-role="images"></div>
  </div>
</template>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const entriesArea = document.getElementById('entriesRenderArea');
  const loadingIndicator = document.getElementById('loadingIndicator');
  const template = document.getElementById('entryTemplate');

  let lastId = null;
  let isLoading = false;
  let isFinished = false;

  const fetchEntries = () => {
    if (isLoading || isFinished) return;

    isLoading = true;
    loadingIndicator.style.display = 'block';

    let url = '/timeline_json.php';
    if (lastId !== null) {
        url += '?last_id=' + lastId;
    }

    fetch(url)
      .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        const entries = data.entries;
        
        if (!entries || entries.length === 0) {
            isFinished = true;
            loadingIndicator.style.display = 'none';
            // 初回ロードで0件の場合はメッセージを出す
            if (lastId === null) {
                entriesArea.innerHTML = '<p style="text-align:center; color:#888;">投稿がありません。<br>誰かをフォローするか、自分で投稿してみましょう！</p>';
            }
            return;
        }

        entries.forEach(entry => {
            const clone = template.content.cloneNode(true);
            
            // アイコン
            const iconImg = clone.querySelector('[data-role="icon"]');
            if (entry.user_icon_filename) {
                iconImg.src = '/image/' + entry.user_icon_filename;
            } else {
                iconImg.src = ''; // またはデフォルトアイコン
                iconImg.style.display = 'none'; // 画像がない場合は非表示にするなど
            }

            // 名前・リンク
            const nameLink = clone.querySelector('[data-role="name"]');
            nameLink.textContent = entry.user_name;
            nameLink.href = entry.user_profile_url;

            // 日時
            clone.querySelector('[data-role="date"]').textContent = entry.created_at;

            // 本文
            clone.querySelector('[data-role="body"]').innerHTML = entry.body;

            // 画像 (カンマ区切りで来る前提)
            const imagesArea = clone.querySelector('[data-role="images"]');
            if (entry.image_filename) {
                const files = entry.image_filename.split(',');
                files.forEach(file => {
                    if (!file) return;
                    const img = document.createElement('img');
                    img.src = '/image/' + file;
                    img.className = 'entry-image';
                    imagesArea.appendChild(img);
                });
            }

            entriesArea.appendChild(clone);
            lastId = entry.id; 
        });
      })
      .catch(error => {
        console.error('Error:', error);
        if (lastId === null) {
            entriesArea.innerHTML = '<p style="color:red;">投稿の読み込みに失敗しました。</p>';
        }
      })
      .finally(() => {
        isLoading = false;
        loadingIndicator.style.display = 'none';
      });
  };

  fetchEntries();

  window.addEventListener('scroll', () => {
    const { scrollTop, scrollHeight, clientHeight } = document.documentElement;
    if (scrollHeight - scrollTop <= clientHeight + 100) {
        fetchEntries();
    }
  });


  const imageInput = document.getElementById('imageInput');
  const previewArea = document.getElementById('preview-area');
  const hiddenInputs = document.getElementById('hidden-inputs');
  const clearBtn = document.getElementById('clearImagesBtn');

  imageInput.addEventListener('change', () => {
    if (imageInput.files.length === 0) return;

    previewArea.innerHTML = '';
    hiddenInputs.innerHTML = '';
    clearBtn.style.display = 'inline-block';

    Array.from(imageInput.files).forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const MAX_SIZE = 800;
                let w = img.width;
                let h = img.height;
                
                if (w > h) {
                    if (w > MAX_SIZE) { h *= MAX_SIZE / w; w = MAX_SIZE; }
                } else {
                    if (h > MAX_SIZE) { w *= MAX_SIZE / h; h = MAX_SIZE; }
                }

                canvas.width = w;
                canvas.height = h;
                ctx.drawImage(img, 0, 0, w, h);

                previewArea.appendChild(canvas);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'image_base64[]';
                input.value = canvas.toDataURL('image/jpeg');
                hiddenInputs.appendChild(input);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
  });

  clearBtn.addEventListener('click', () => {
    imageInput.value = '';
    previewArea.innerHTML = '';
    hiddenInputs.innerHTML = '';
    clearBtn.style.display = 'none';
  });
});
</script>
</body>
</html>
