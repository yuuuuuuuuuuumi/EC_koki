<?php
session_start();
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}
// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
// セッションにあるログインIDから、ログインしている対象の会員情報を引く
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $_SESSION['login_user_id'],
]);
$user = $select_sth->fetch();

if (isset($_POST['birthdate'])) {
    // POSTで生年月日が送られてきた場合の処理
    $birthdate = $_POST['birthdate'];

    // 簡単なバリデーション (日付形式であるか)
    // 実際にはより厳密なチェックが必要ですが、ここでは date() 関数で検証
    if (!empty($birthdate) && strtotime($birthdate) !== false) {
        // usersテーブルを更新する
        $update_sth = $dbh->prepare("UPDATE users SET birthdate = :birthdate WHERE id = :id");
        $update_sth->execute([
            ':birthdate' => $birthdate,
            ':id' => $_SESSION['login_user_id'],
        ]);
        
        // 処理が終わったらリダイレクトする
        header("HTTP/1.1 303 See Other");
        header("Location: ./birthdate.php");
        return;
    } else {
        // エラー処理（ここでは省略）
        $error = "正しい日付形式で入力してください。";
    }
}
include_once('../header.php');
?>
<a href="./index.php">設定一覧に戻る</a>

<h1>生年月日設定</h1>

<?php if (isset($error)): ?>
    <p style="color: red;"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<p>
    現在の生年月日: 
    <strong><?= htmlspecialchars($user['birthdate'] ?? '未設定') ?></strong>
</p>

<form method="POST" action="./birthdate.php">
    <div style="margin: 1em 0;">
        <label for="birthdate">生年月日:</label>
        <input type="date" name="birthdate" id="birthdate" required
               value="<?= htmlspecialchars($user['birthdate'] ?? '') ?>">
    </div>
    <button type="submit">設定を保存</button>
</form>
