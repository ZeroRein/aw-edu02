<?php
session_start();

// ログイン確認
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// URLから "誰を" 解除するか取得
if (empty($_GET['followee_user_id'])) {
    header("HTTP/1.1 400 Bad Request");
    print("解除する相手のIDが指定されていません");
    return;
}

// DB接続
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

// 削除クエリ実行
// DELETE文を使って、自分のID(follower) と 相手のID(followee) が一致する行を消す
$delete_sth = $dbh->prepare(
    "DELETE FROM user_relationships"
    . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
);

$delete_sth->execute([
    ':follower_user_id' => $_SESSION['login_user_id'], // 自分
    ':followee_user_id' => $_GET['followee_user_id'], // 相手
]);

// 処理が終わったら、元の画面（または一覧）に戻す
// ここでは HTTP_REFERER (直前のページ) があればそこへ、なければフォロー一覧へ戻します
if (!empty($_SERVER['HTTP_REFERER'])) {
    header("Location: " . $_SERVER['HTTP_REFERER']);
} else {
    header("Location: /follow_list.php");
}
?>
