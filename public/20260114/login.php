<?php
session_start();

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

if (!empty($_POST['email']) && !empty($_POST['password'])) {
  // email から会員情報を引く
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE email = :email ORDER BY id DESC LIMIT 1");
  $select_sth->execute([
    ':email' => $_POST['email'],
  ]);
  $user = $select_sth->fetch();

  if (empty($user)) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./login.php?error=1");
    return;
  }

  $correct_password = password_verify($_POST['password'], $user['password']);

  if (!$correct_password) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./login.php?error=1");
    return;
  }

  $_SESSION["login_user_id"] = $user['id'];

  header("HTTP/1.1 303 See Other");
  header("Location: ./login_finish.php");
  return;
}
include_once('header.php');
?>

初めての人は<a href="/20260114/signup.php">会員登録</a>しましょう。
<hr>
<h1>ログイン</h1>
<!-- ログインフォーム -->
<form method="POST">
  <label>
    メールアドレス:
    <input type="email" name="email">
  </label>
  <br>
  <label>
    パスワード:
    <input type="password" name="password" minlength="6">
  </label>
  <br>
  <button type="submit">決定</button>
</form>

<?php if(!empty($_GET['error'])):  ?>
<div style="color: red;">
  メールアドレスかパスワードが間違っています。
</div>
<?php endif; ?>
