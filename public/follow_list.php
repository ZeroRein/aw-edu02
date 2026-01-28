<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

$select_sth = $dbh->prepare(
  'SELECT user_relationships.*, users.name AS followee_user_name, users.icon_filename AS followee_user_icon_filename'
  . ' FROM user_relationships INNER JOIN users ON user_relationships.followee_user_id = users.id'
  . ' WHERE user_relationships.follower_user_id = :follower_user_id'
  . ' ORDER BY user_relationships.id DESC'
);
$select_sth->execute([
  ':follower_user_id' => $_SESSION['login_user_id'],
]);
?>

<h1>フォロー済のユーザー一覧</h1>
<div>
<a href="/bbs.php">掲示板に戻る</a> |
<a href="/follower_list.php">フォロワーリストを見る</a>
</div>
<hr>
<ul>
  <?php foreach($select_sth as $relationship): ?>
  <li style="margin-bottom: 1em;">
    <a href="/profile.php?user_id=<?= $relationship['followee_user_id'] ?>">
      <?php if(!empty($relationship['followee_user_icon_filename'])): ?>
      <img src="/image/<?= $relationship['followee_user_icon_filename'] ?>"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <?php endif; ?>

      <?= htmlspecialchars($relationship['followee_user_name']) ?>
      (ID: <?= htmlspecialchars($relationship['followee_user_id']) ?>)
    </a>
    <br>
    <span style="font-size: small; color: gray;">
        (<?= $relationship['created_at'] ?>にフォロー)
        <a href="/unfollow.php?followee_user_id=<?= $relationship['followee_user_id'] ?>"
           onclick="return confirm('本当にフォロー解除しますか？');"
           style="color: red; margin-left: 1em;">
           [解除]
        </a>
    </span>
  </li>
  <?php endforeach; ?>
</ul>
