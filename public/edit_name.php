<?php

$session_cookie_name = 'session_id';
$session_id = $_COOKIE[$session_cookie_name] ?? base64_encode(random_bytes(64));

if (!isset($_COOKIE[$session_cookie_name])) {
    setcookie($session_cookie_name, $session_id);
}

$redis = new Redis();
$redis->connect('redis', 6379);

$redis_session_key = "session-" . $session_id;

$session_values = $redis->exists($redis_session_key)
   ? json_decode($redis->get($redis_session_key), true)
   : [];


$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');


if (isset($_POST['update_name']) && !empty($_POST['new_name'])) {
    $new_name = trim($_POST['new_name']);
    $user_id = $session_values['login_user_id'];

    // データベースの名前を更新
    $update_sth = $dbh->prepare("UPDATE users SET name = :name WHERE id = :id");
    $update_sth->execute([
        ':name' => $new_name,
        ':id'   => $user_id
    ]);

    // 更新成功後、クエリパラメータを付けてリダイレクトし、二重送信を防ぐ
    header("HTTP/1.1 303 See Other");
    header("Location: ./edit_name.php?update_success=1");
    exit;
}


// セッションにあるログインIDから、ログインしている対象の会員情報を引く（更新後の情報を取得するために必須）
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $session_values['login_user_id'],
]);
$user = $select_sth->fetch();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>名前変更</title>
</head>
<body>

<?php if ($user): ?>
    <p>
        **<?php echo htmlspecialchars($user['name']); ?>** さん
    </p>

    <?php if (isset($_GET['update_success'])): ?>
        <div style="color: green; font-weight: bold; margin-bottom: 15px;">
            名前を更新しました！
        </div>
    <?php endif; ?>

    <hr>
    
    <h2>名前の変更</h2>
    <form method="POST">
        <input type="hidden" name="update_name" value="1">
        
        <label>
            新しい名前:
            <input type="text" name="new_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
        </label>
        <br>
        <button type="submit">名前を更新</button>
    </form>
    
    <hr>

<?php else: ?>
    <p>エラー: ユーザー情報が見つかりませんでした。</p>
<?php endif; ?>
</body>
</html>
