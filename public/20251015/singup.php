<?php
// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// エラーメッセージ用変数
$error_message = '';

if (!empty($_POST['name']) && !empty($_POST['email']) && !empty($_POST['password'])) {
  // メールアドレスの重複チェック
  $check_sth = $dbh->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
  $check_sth->execute([':email' => $_POST['email']]);
  $count = $check_sth->fetchColumn();

  if ($count > 0) {
    // 既に登録されている場合はエラーメッセージを表示
    $error_message = "すでにこのメールアドレスは登録されています。";
  } else {
    // 新規登録
    $insert_sth = $dbh->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
    $insert_sth->execute([
      ':name' => $_POST['name'],
      ':email' => $_POST['email'],
      ':password' => $_POST['password'],
    ]);
    // 完了画面にリダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./signup_finish.php");
    return;
  }
}
?>

<h1>会員登録</h1>

<!-- エラーメッセージ表示 -->
<?php if (!empty($error_message)): ?>
  <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
<?php endif; ?>

<!-- 登録フォーム -->
<form method="POST">
  <label>
    名前：
    <input type="text" name="name" required>
  </label>
  <br>
  <label>
    メールアドレス:
    <input type="email" name="email" required>
  </label>
  <br>
  <label>
    パスワード：
    <input type="password" name="password" minlength="6" autocomplete="new-password" required>
  </label>
  <br>
  <button type="submit">決定</button>
</form>
