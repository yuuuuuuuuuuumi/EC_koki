<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

// フォロー解除対象のユーザーID
if (empty($_GET['followee_user_id'])) {
  header("HTTP/1.1 400 Bad Request");
  print("フォロー解除対象のユーザーIDが指定されていません");
  return;
}
$followee_user_id = $_GET['followee_user_id'];

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォロー解除対象のユーザー情報を取得
$select_sth = $dbh->prepare("SELECT id, name FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $followee_user_id,
]);
$followee_user = $select_sth->fetch();

if (empty($followee_user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

// 現在のフォロー状態を確認
$select_sth = $dbh->prepare(
  "SELECT * FROM user_relationships"
  . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
);
$select_sth->execute([
  ':followee_user_id' => $followee_user['id'], 
  ':follower_user_id' => $_SESSION['login_user_id'],
]);
$relationship = $select_sth->fetch();

if (empty($relationship)) { 
  print(htmlspecialchars($followee_user['name']) . " さんは既にフォローされていません。");
  return;
}

$delete_result = false;
if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
  $delete_sth = $dbh->prepare(
    "DELETE FROM user_relationships"
    . " WHERE follower_user_id = :follower_user_id AND followee_user_id = :followee_user_id"
  );
  $delete_result = $delete_sth->execute([
    ':followee_user_id' => $followee_user['id'],
    ':follower_user_id' => $_SESSION['login_user_id'],
  ]);
}
include_once('header.php');
?>

<h1>フォロー解除確認</h1>

<?php if($delete_result): ?>
<div>
  <?= htmlspecialchars($followee_user['name']) ?> さんへのフォローを解除しました。<br>
  <a href="./profile.php?user_id=<?= $followee_user['id'] ?>">
    <?= htmlspecialchars($followee_user['name']) ?> さんのプロフィールに戻る
  </a>
</div>
<?php else: ?>
<div>
  本当に <?= htmlspecialchars($followee_user['name']) ?> さんへのフォローを解除しますか?
  <form method="POST">
    <button type="submit" style="color: white; background-color: red; padding: 0.5em 1em; border: none; cursor: pointer;">
      フォローを解除する
    </button>
  </form>
</div>
<hr>
<div>
  <a href="./profile.php?user_id=<?= $followee_user['id'] ?>">キャンセル</a>
</div>
<?php endif; ?>
