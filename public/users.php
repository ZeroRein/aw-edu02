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
$my_user_id = $_SESSION['login_user_id'];
$check_sth = $dbh->prepare("SELECT COUNT(*) FROM user_relationships WHERE follower_user_id = :me AND followee_user_id = :target");
$sql = 'SELECT * FROM users WHERE 1=1'; 
$params = [];
// 名前検索
if (!empty($_GET['search_name'])) {
    $sql .= ' AND name LIKE :name';
    $params[':name'] = '%' . $_GET['search_name'] . '%';
}
// 誕生年検索
if (!empty($_GET['year_from'])) {
    $sql .= ' AND birthday >= :year_from';
    $params[':year_from'] = $_GET['year_from'] . '-01-01';
}
if (!empty($_GET['year_to'])) {
    $sql .= ' AND birthday <= :year_to';
    $params[':year_to'] = $_GET['year_to'] . '-12-31';
}
$sql .= ' ORDER BY id DESC';
// クエリ実行
$select_sth = $dbh->prepare($sql);
$select_sth->execute($params);
?>
<body>
  <h1>会員一覧</h1>

  <form action="" method="GET" style="margin-bottom: 2em; padding: 1em; background-color: #f0f0f0;">
    <div style="margin-bottom: 10px;">
        名前:
        <input type="text" name="search_name" value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>" placeholder="名前を入力">
    </div>
    <div style="margin-bottom: 10px;">
        生まれ年:
        <input type="number" name="year_from" value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>" placeholder="1990" style="width: 80px;"> 年
        ～
        <input type="number" name="year_to" value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>" placeholder="2000" style="width: 80px;"> 年
    </div>
    <div>
        <button type="submit">検索する</button>
        <a href="?" style="margin-left: 10px; font-size: small;">リセット</a>
    </div>
  </form>
  <?php foreach($select_sth as $user): ?>
    <?php
    // フォロー状態判定
    if ($user['id'] == $my_user_id) {
        $is_following = false;
        $is_me = true;
    } else {
        $is_me = false;
        $check_sth->execute([':me' => $my_user_id, ':target' => $user['id']]);
        $is_following = ($check_sth->fetchColumn() > 0);                                                          
    }
    ?>
    <div style="display: flex; justify-content: start; align-items: center; padding: 1em 2em;">
      <?php if(empty($user['icon_filename'])): ?>
        <div style="height: 2em; width: 2em;"></div>
      <?php else: ?>
        <img src="/image/<?= $user['icon_filename'] ?>"
          style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <?php endif; ?>
      <a href="/profile.php?user_id=<?= $user['id'] ?>" style="margin-left: 1em;">
        <?= htmlspecialchars($user['name']) ?>
      </a>
      <span style="margin-left: 0.5em; color: gray; font-size: small;">
        (<?= htmlspecialchars($user['birthday'] ?? '') ?>)
      </span>
      <div style="margin-left: 1em;">
        <?php if($is_me): ?>
        <?php elseif($is_following): ?>
            <span style="font-size: small; color: green;">フォロー済み</span>
            (<a href="./unfollow.php?followee_user_id=<?= $user['id'] ?>" style="color: red;">解除</a>)
        <?php else: ?>
            <a href="./follows.php?followee_user_id=<?= $user['id'] ?>">フォローする</a>
        <?php endif; ?>
      </div>
    </div>
    <hr style="border: none; border-bottom: 1px solid gray;">
  <?php endforeach; ?>
</body>
