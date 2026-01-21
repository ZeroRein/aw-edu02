<?php
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 401 Unauthorized");
  header("Content-Type: application/json");
  print(json_encode(['entries' => []]));
  return;
}

// 現在のログイン情報を取得する（ここは変更なし）
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// --- 修正箇所開始 ---

// 1回の取得件数
$limit = 10;

// URLパラメータから last_id を取得（なければ null）
// JavaScriptから「今表示されている一番古いID」が送られてくる
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : null;

// 基本となるSQL
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries'
  . ' INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE'
  . '   (' // OR条件を括弧で囲むことが重要
  . '     bbs_entries.user_id IN'
  . '       (SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id)'
  . '     OR bbs_entries.user_id = :login_user_id'
  . '   )';

// last_id がある場合（2回目以降のロード）、それより古い(IDが小さい)ものを取得する条件を追加
if ($last_id !== null) {
    $sql .= ' AND bbs_entries.id < :last_id';
}

// 並び替えと件数制限
$sql .= ' ORDER BY bbs_entries.created_at DESC LIMIT :limit';

$select_sth = $dbh->prepare($sql);

// パラメータのバインド
$select_sth->bindValue(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$select_sth->bindValue(':limit', $limit, PDO::PARAM_INT);
if ($last_id !== null) {
    $select_sth->bindValue(':last_id', $last_id, PDO::PARAM_INT);
}

$select_sth->execute();

// --- 修正箇所終了 ---

function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body);
  $body = nl2br($body);
  return $body;
}

$result_entries = [];
foreach ($select_sth as $entry) {
  $result_entry = [
    'id' => $entry['id'],
    'user_name' => $entry['user_name'],
    'user_icon_filename' => $entry['user_icon_filename'],
    'user_profile_url' => '/profile.php?user_id=' . $entry['user_id'],
    'body' => bodyFilter($entry['body']),
    'created_at' => $entry['created_at'],
    'image_filename' => $entry['image_filename'],
  ];
  $result_entries[] = $result_entry;
}

header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
print(json_encode(['entries' => $result_entries]));
