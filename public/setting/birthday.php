<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// DB接続
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

// POST送信があった場合（生年月日更新処理）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 入力値を取得
    $birthday = $_POST['birthday']; 
    
    // 空文字が送られてきた場合はNULLにする（未設定に戻す場合など）
    if ($birthday === '') {
        $birthday = null;
    }

    // DB更新
    $update_sth = $dbh->prepare("UPDATE users SET birthday = :birthday WHERE id = :id");
    $update_sth->execute([
        ':birthday' => $birthday,
        ':id' => $_SESSION['login_user_id'],
    ]);

    // リロード対策のリダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./birthday.php");
    return;
}

// 現在のユーザー情報を取得（フォームの初期値用）
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $select_sth->fetch();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <title>生年月日設定</title>
</head>
<body>
    <a href="./index.php">設定一覧に戻る</a>
    <h1>生年月日設定</h1>

    <form method="POST">
        <label for="birthday">生年月日: </label>
        <input type="date" name="birthday" id="birthday" 
               value="<?= htmlspecialchars($user['birthday'] ?? '') ?>">
        <br><br>
        <button type="submit">設定する</button>
    </form>
    
    <p>※設定するとプロフィールに年齢が表示されます。</p>
</body>
</html>
