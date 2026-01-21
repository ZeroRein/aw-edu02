<?php
// 他のページと同様に、Redisセッションハンドラを使用する設定
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis:6379');
session_start();

// 1. セッション変数をすべて空にする
$_SESSION = [];

// 2. セッションクッキーを削除する
// (セッションIDがクライアント側に残っていると、セッション破棄後に
// 別のセッションが作られてしまうのを防ぐため)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. サーバー側（Redis）のセッションデータを破棄する
session_destroy();

// 4. ログアウト後はログインページにリダイレクトする
header("HTTP/1.1 302 Found");
header("Location: ./login.php");
exit;
?>
