<?php
session_start();

// ログインしてなければログイン画面に飛ばす
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

// 自分(login_user_id) が「フォローされている(followee)」データを引く
// 今度は「相手(follower)」の情報を users テーブルから結合して取得します
$select_sth = $dbh->prepare(
  'SELECT user_relationships.*, users.name AS follower_user_name, users.icon_filename AS follower_user_icon_filename'
  . ' FROM user_relationships INNER JOIN users ON user_relationships.follower_user_id = users.id'
  . ' WHERE user_relationships.followee_user_id = :followee_user_id'
  . ' ORDER BY user_relationships.id DESC'
);
$select_sth->execute([
  ':followee_user_id' => $_SESSION['login_user_id'], // 自分が「フォローされている側」
]);
?>

<h1>フォロワー一覧</h1>

 <div>
     <a href="/bbs.php">掲示板に戻る</a> |
     <a href="/follow_list.php">フォローリストを見る</a>
</div>
<hr>
<ul>
  <?php foreach($select_sth as $relationship): ?>
  <li style="margin-bottom: 1em;">
    <a href="/profile.php?user_id=<?= $relationship['follower_user_id'] ?>">
      
      <?php if(!empty($relationship['follower_user_icon_filename'])): // アイコンがある場合 ?>
      <img src="/image/<?= $relationship['follower_user_icon_filename'] ?>"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <?php endif; ?>

      <?= htmlspecialchars($relationship['follower_user_name']) ?>
      (ID: <?= htmlspecialchars($relationship['follower_user_id']) ?>)
    </a>
    <br>
    <span style="font-size: small; color: gray;">
        (<?= $relationship['created_at'] ?>にフォローされました)
    </span>
  </li>
  <?php endforeach; ?>
</ul>

