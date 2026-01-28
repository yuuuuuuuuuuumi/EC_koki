<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: ./login.php");
  return;
}

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォロー対象
$followee_user = null;
if (!empty($_GET['followee_user_id'])) {
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
  $select_sth->execute([
      ':id' => $_GET['followee_user_id'],
  ]);
  $followee_user = $select_sth->fetch();
}
if (empty($followee_user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

// 現在のフォロー状態取得
$select_sth = $dbh->prepare(
  "SELECT * FROM user_relationships"
  . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
);
$select_sth->execute([
  ':followee_user_id' => $followee_user['id'], 
  ':follower_user_id' => $_SESSION['login_user_id'], 
]);
$relationship = $select_sth->fetch();
if (!empty($relationship)) {
  print("既にフォローしています。");
  return;
}

$insert_result = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
  $insert_sth = $dbh->prepare(
    "INSERT INTO user_relationships (follower_user_id, followee_user_id) VALUES (:follower_user_id, :followee_user_id)"
  );
  $insert_result = $insert_sth->execute([
    ':followee_user_id' => $followee_user['id'], 
    ':follower_user_id' => $_SESSION['login_user_id'], 
  ]);
}
include_once('header.php');
?>

<?php if($insert_result): ?>
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんをフォローしました。<br>
  <a href="./profile.php?user_id=<?= $followee_user['id'] ?>">
    <?= htmlspecialchars($followee_user['name']) ?> さんのプロフィールへ
  </a>
  /
  <a href="./users.php">
    会員一覧へ
  </a>
</div>
<?php else: ?>
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんをフォローしますか?
  <form method="POST">
    <button type="submit">
      フォローする
    </button>
  </form>
</div>
<?php endif; ?>
