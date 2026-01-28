<?php
session_start();

// ログイン確認
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// DB接続
try {
    $dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');
} catch (PDOException $e) {
    die("データベース接続エラー: " . $e->getMessage());
}

$my_user_id = $_SESSION['login_user_id'];

// 自分の名前などを取得（ヘッダー表示用）
$me_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$me_sth->execute([':id' => $my_user_id]);
$me = $me_sth->fetch();

// フォロー状態チェック用のプリペアドステートメント
$check_sth = $dbh->prepare("SELECT COUNT(*) FROM user_relationships WHERE follower_user_id = :me AND followee_user_id = :target");

// ユーザー一覧取得クエリの構築
$sql = 'SELECT * FROM users WHERE 1=1'; 
$params = [];

// 名前検索
if (!empty($_GET['search_name'])) {
    $sql .= ' AND name LIKE :name';
    $params[':name'] = '%' . $_GET['search_name'] . '%';
}

// 誕生年検索
// ※注意: データベースに birthday カラム(DATE型)が必要です
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
try {
    $select_sth = $dbh->prepare($sql);
    $select_sth->execute($params);
} catch (PDOException $e) {
    // birthdayカラムがない場合などのエラー対策
    $error_message = "検索エラー: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>会員一覧</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        a { text-decoration: none; color: #007bff; }
        .user-row { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
        .user-icon { height: 40px; width: 40px; border-radius: 50%; object-fit: cover; background: #ddd; margin-right: 10px; }
        .nav-links { margin-bottom: 20px; }
    </style>
</head>
<body>
  <h1>会員一覧</h1>

  <div class="nav-links">
      <a href="/timeline.php">« タイムラインに戻る</a> | 
      <a href="/follow_list.php">フォロー中リスト</a> | 
      現在ログイン中: <b><?= htmlspecialchars($me['name'] ?? 'ゲスト') ?></b>
  </div>

  <?php if(isset($error_message)): ?>
    <p style="color: red;"><?= htmlspecialchars($error_message) ?></p>
    <p>※「誕生年検索」を行うにはデータベースに <code>birthday</code> カラムが必要です。</p>
  <?php endif; ?>

  <form action="" method="GET" style="margin-bottom: 2em; padding: 1em; background-color: #f9f9f9; border-radius: 5px;">
    <div style="margin-bottom: 10px;">
        <label>名前: <input type="text" name="search_name" value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>" placeholder="名前を入力"></label>
    </div>
    <div style="margin-bottom: 10px;">
        <label>生まれ年:
        <input type="number" name="year_from" value="<?= htmlspecialchars($_GET['year_from'] ?? '') ?>" placeholder="1990" style="width: 80px;"> 年</label>
        ～
        <label><input type="number" name="year_to" value="<?= htmlspecialchars($_GET['year_to'] ?? '') ?>" placeholder="2000" style="width: 80px;"> 年</label>
    </div>
    <div>
        <button type="submit">検索する</button>
        <a href="users.php" style="margin-left: 10px; font-size: small; color: #666;">リセット</a>
    </div>
  </form>

  <?php if(isset($select_sth)): ?>
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
        <div class="user-row">
          <?php if(empty($user['icon_filename'])): ?>
            <div class="user-icon" style="display: flex; align-items: center; justify-content: center; color: white;">?</div>
          <?php else: ?>
            <img src="/image/<?= htmlspecialchars($user['icon_filename']) ?>" class="user-icon">
          <?php endif; ?>

          <div style="flex-grow: 1;">
              <a href="/profile.php?user_id=<?= $user['id'] ?>" style="font-weight: bold; font-size: 1.1em;">
                <?= htmlspecialchars($user['name']) ?>
              </a>
              <?php if(!empty($user['birthday'])): ?>
                  <span style="margin-left: 0.5em; color: gray; font-size: small;">
                    (<?= htmlspecialchars($user['birthday']) ?>生まれ)
                  </span>
              <?php endif; ?>
          </div>

          <div>
            <?php if($is_me): ?>
                <span style="color: #999;">自分</span>
            <?php elseif($is_following): ?>
                <span style="font-size: small; color: green; margin-right: 5px;">フォロー済</span>
                <a href="./unfollow.php?followee_user_id=<?= $user['id'] ?>" 
                   onclick="return confirm('フォロー解除しますか？')"
                   style="color: red; border: 1px solid red; padding: 2px 5px; border-radius: 4px; font-size: small;">解除</a>
            <?php else: ?>
                <a href="./follows.php?followee_user_id=<?= $user['id'] ?>"
                   style="background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; font-size: small;">フォローする</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
  <?php endif; ?>
</body>
</html>
