<?php
session_start();

// ログイン確認
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// DB接続
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

// 自分(login_user_id) が「フォローされている(followee)」データを取得
// 相手(follower) の情報を users テーブルから結合して取得
$sql = 'SELECT user_relationships.*, users.name AS follower_user_name, users.icon_filename AS follower_user_icon_filename'
     . ' FROM user_relationships'
     . ' INNER JOIN users ON user_relationships.follower_user_id = users.id'
     . ' WHERE user_relationships.followee_user_id = :my_id'
     . ' ORDER BY user_relationships.id DESC';

$stmt = $dbh->prepare($sql);
$stmt->execute([
  ':my_id' => $_SESSION['login_user_id'],
]);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>フォロワー一覧</title>
</head>
<body>
    <h1>フォロワー一覧</h1>

    <div style="margin-bottom: 1em;">
        <a href="/timeline.php">タイムラインに戻る</a> |
        <a href="/follow_list.php">フォロー中リストを見る</a>
    </div>
    <hr>
    
    <ul>
      <?php foreach($stmt as $relationship): ?>
      <li style="margin-bottom: 1em; list-style: none;">
        <div style="display: flex; align-items: center;">
            <?php if(!empty($relationship['follower_user_icon_filename'])): ?>
                <img src="/image/<?= htmlspecialchars($relationship['follower_user_icon_filename']) ?>"
                    style="height: 40px; width: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;">
            <?php else: ?>
                <div style="height: 40px; width: 40px; background-color: #ccc; border-radius: 50%; margin-right: 10px;"></div>
            <?php endif; ?>

            <div>
                <a href="/profile.php?user_id=<?= htmlspecialchars($relationship['follower_user_id']) ?>">
                  <?= htmlspecialchars($relationship['follower_user_name']) ?>
                </a>
                <span style="font-size: small; color: gray;">(ID: <?= htmlspecialchars($relationship['follower_user_id']) ?>)</span>
                
                <br>
                <span style="font-size: small; color: gray;">
                    <?= $relationship['created_at'] ?> にフォローされました
                </span>
            </div>
        </div>
      </li>
      <hr style="border: none; border-bottom: 1px dashed #eee;">
      <?php endforeach; ?>
    </ul>
</body>
</html>
