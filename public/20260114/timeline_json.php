<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');


session_start();
if (empty($_SESSION['login_user_id'])) { 
  header("HTTP/1.1 401 Unauthorized");
  header("Content-Type: application/json");
  print(json_encode(['entries' => []]));
  return;
}

// ログイン情報
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 10; // 1回あたりの取得件数（N）

// 投稿データ
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
    . ' FROM bbs_entries'
    . ' INNER JOIN users ON bbs_entries.user_id = users.id'
    . ' LEFT OUTER JOIN user_relationships ON bbs_entries.user_id = user_relationships.followee_user_id'
    . ' WHERE user_relationships.follower_user_id = :login_user_id OR bbs_entries.user_id = :login_user_id'
    . ' GROUP BY bbs_entries.id' 
    . ' ORDER BY bbs_entries.created_at DESC'
    . ' LIMIT :limit OFFSET :offset'; 

$select_sth = $dbh->prepare($sql);
$select_sth->bindValue(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$select_sth->bindValue(':limit', $limit, PDO::PARAM_INT);
$select_sth->bindValue(':offset', $offset, PDO::PARAM_INT);
$select_sth->execute();

function bodyFilter (string $body): string {
    $body = htmlspecialchars($body);
    $body = nl2br($body);
    $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);
    return $body;
}

$result_entries = [];
foreach ($select_sth as $entry) {
    $img_sth = $dbh->prepare("SELECT image_filename FROM entry_images WHERE entry_id = :entry_id");
    $img_sth->execute([':entry_id' => $entry['id']]);
    $images = $img_sth->fetchAll(PDO::FETCH_COLUMN);

    $result_entries[] = [
        'id' => $entry['id'],
        'user_id' => $entry['user_id'],
        'user_name' => $entry['user_name'],
        'user_icon_file_url' => empty($entry['user_icon_filename']) ? '' : ('/image/' . $entry['user_icon_filename']),
        'body' => bodyFilter($entry['body']),
        'image_filenames' => $images, 
        'created_at' => $entry['created_at'],
    ];
}

header("Content-Type: application/json");
print(json_encode(['entries' => $result_entries]));
