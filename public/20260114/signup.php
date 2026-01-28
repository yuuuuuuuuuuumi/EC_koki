<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['password'])) {

  $select_sth = $dbh->prepare("SELECT * FROM users WHERE email = :email ORDER BY id DESC LIMIT 1");
  $select_sth->execute([
    ':email' => $_POST['email'],
  ]);
  $user = $select_sth->fetch();
  if (!empty($user)) {
    header("HTTP/1.1 303 See Other");
    header("Location: ./signup.php?duplicate_email=1");
    return;
  }

  $insert_sth = $dbh->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
  $insert_sth->execute([
    ':name' => $_POST['name'],
    ':email' => $_POST['email'],
    ':password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
  ]);
  header("HTTP/1.1 303 See Other");
  header("Location: ./signup_finish.php");
  return;
}
include_once('header.php');
?>
<h1>会員登録</h1>

会員登録済の人は<a href="/20260114/login.php">ログイン</a>しましょう。
<hr>

<form method="POST">
  <label>
    名前:
    <input type="text" name="name">
  </label>
  <br>
  <label>
    メールアドレス:
    <input type="email" name="email">
  </label>
  <br>
  <label>
    パスワード:
    <input type="password" name="password" minlength="6" autocomplete="new-password">
  </label>
  <br>
  <button type="submit">決定</button>
</form>

<?php if(!empty($_GET['duplicate_email'])): ?>
<div style="color: red;">
  入力されたメールアドレスは既に使われています。
</div>
<?php endif; ?>
