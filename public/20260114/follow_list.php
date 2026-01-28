<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォローしている一覧
// フォローしている対象の会員情報も一緒に取得
$select_sth = $dbh->prepare(
  'SELECT user_relationships.*, users.name AS followee_user_name, users.icon_filename AS followee_user_icon_filename'
  . ' FROM user_relationships INNER JOIN users ON user_relationships.followee_user_id = users.id'
  . ' WHERE user_relationships.follower_user_id = :follower_user_id'
  . ' ORDER BY user_relationships.id DESC'
);
$select_sth->execute([
  ':follower_user_id' => $_SESSION['login_user_id'],
]);
include_once('header.php');
?>

<h1>フォロー済のユーザー一覧</h1>

<ul>
  <?php foreach($select_sth as $relationship): ?>
  <li>
    <a href="i./profile.php?user_id=<?= $relationship['followee_user_id'] ?>">
      <?php if(!empty($relationship['followee_user_icon_filename'])): ?>
      <img src="/image/<?= $relationship['followee_user_icon_filename'] ?>"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <?php endif; ?>

      <?= htmlspecialchars($relationship['followee_user_name']) ?>
      (ID: <?= htmlspecialchars($relationship['followee_user_id']) ?>)
    </a>
    (<?= $relationship['created_at'] ?>にフォロー)
    <a href="./unfollow.php?followee_user_id=<?= $relationship['followee_user_id'] ?>"
      style="margin-left: 1em; color: red; text-decoration: none;">
      [解除]
    </a>
  </li>
  <br>
  <a href="./timeline_in.php">タイムラインへ戻る</a>
  <?php endforeach; ?>
</ul>
