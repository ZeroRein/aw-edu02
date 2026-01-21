<?php
session_start();
  // ログイン確認
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
 return;
}

if (empty($_GET['followee_user_id'])) {
    header("HTTP/1.1 400 Bad Request");
    print("");
    return;
  }
  // DB接続
    $dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

  $insert_sth = $dbh->prepare(
    "INSERT INTO user_relationships (follower_user_id, followee_user_id) VALUES (:follower_user_id, :followee_user_id)"
  );

  $insert_sth->execute([
    ':followee_user_id' => $_GET['followee_user_id'], // フォローされる側(フォロー対象)
    ':follower_user_id' => $_SESSION['login_user_id'], // フォローする側はログインしている会員
  ]);
  // 処理が終わったら、元の画面（または一覧）に戻す
  // ここでは HTTP_REFERER (直前のページ) があればそこへ、なければフォロー一覧へ戻します
  if (!empty($_SERVER['HTTP_REFERER'])) {
      header("Location: " . $_SERVER['HTTP_REFERER']);
  } else {
      header("Location: /follow_list.php");
  }
  ?>
