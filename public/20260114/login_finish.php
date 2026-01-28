<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: ./20260114/login.php");
  return;
}

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
$insert_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$insert_sth->execute([
  ':id' => $_SESSION['login_user_id'],
]);
$user = $insert_sth->fetch();
include_once('header.php');
?>

<h1>ログイン完了</h1>
<p>
  ログイン完了しました!<br>
  <a href="/20260114/timeline_in.php">タイムラインはこちら</a>
</p>
<hr>
<p>
  現在ログインしている会員情報は以下のとおりです。
</p>
<dl> 
  <dt>ID</dt>
  <dd><?= htmlspecialchars($user['id']) ?></dd>
  <dt>メールアドレス</dt>
  <dd><?= htmlspecialchars($user['email']) ?></dd>
  <dt>名前</dt>
  <dd><?= htmlspecialchars($user['name']) ?></dd>
</dl>
