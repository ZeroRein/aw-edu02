<?php
$dbh = new PDO('mysql:host=mysql;dbname=koki04', 'root', '');

session_start();

if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 401 Unauthorized");
    header("Content-Type: application/json");
    echo json_encode(['entries' => []]);
    return;
}
$limit = 10;
$last_id = isset($_GET['last_id']) ? intval($_GET['last_id']) : null;

$sql = "SELECT 
            b.*, 
            u.name AS user_name, 
            u.icon_filename AS user_icon_filename,
            (
                SELECT GROUP_CONCAT(image_filename SEPARATOR ',') 
                FROM entry_images 
                WHERE entry_id = b.id
            ) AS image_filenames
        FROM bbs_entries b
        INNER JOIN users u ON b.user_id = u.id
        WHERE 
            (
                -- 自分がフォローしている人の投稿
                b.user_id IN (
                    SELECT followee_user_id 
                    FROM user_relationships 
                    WHERE follower_user_id = :login_user_id
                )
                -- または自分の投稿
                OR b.user_id = :login_user_id
            )";

if ($last_id !== null) {
    $sql .= ' AND b.id < :last_id';
}

$sql .= ' ORDER BY b.id DESC LIMIT :limit';

$stmt = $dbh->prepare($sql);

$stmt->bindValue(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
if ($last_id !== null) {
    $stmt->bindValue(':last_id', $last_id, PDO::PARAM_INT);
}

$stmt->execute();
$result_entries = [];
foreach ($stmt as $entry) {
    $result_entries[] = [
        'id' => $entry['id'],
        'user_name' => $entry['user_name'],
        'user_icon_filename' => $entry['user_icon_filename'],
        'user_profile_url' => '/profile.php?user_id=' . $entry['user_id'],
        'body' => nl2br(htmlspecialchars($entry['body'])), // XSS対策と改行コードの変換
        'created_at' => $entry['created_at'],
        'image_filename' => $entry['image_filenames'], 
    ];
}

header("HTTP/1.1 200 OK");
header("Content-Type: application/json");
echo json_encode(['entries' => $result_entries]);
?>
