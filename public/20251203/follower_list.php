<?php
session_start();

// ログインしてなければログイン画面に飛ばす
if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// DBに接続
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// ログインユーザーをフォローしている会員の一覧をDBから引く。
// テーブル結合を使って、フォローしている対象の会員情報も一緒に取得。
$select_sth = $dbh->prepare(
    // フォロワー（フォローしている側）の情報を取得するため、
    // user_relationships.follower_user_id を users.id に結合します。
    'SELECT user_relationships.*, users.name AS follower_user_name, users.icon_filename AS follower_user_icon_filename'
    . ' FROM user_relationships INNER JOIN users ON user_relationships.follower_user_id = users.id'
    // 条件は「フォローされている側」がログインユーザーであること
    . ' WHERE user_relationships.followee_user_id = :followee_user_id'
    . ' ORDER BY user_relationships.id DESC'
);
$select_sth->execute([
    ':followee_user_id' => $_SESSION['login_user_id'], // ログインユーザーID
]);
?>

<h1>自分をフォローしているユーザー一覧（フォロワー）</h1>

<ul>
    <?php foreach($select_sth as $relationship): ?>
    <li>
        <a href="./profile.php?user_id=<?= $relationship['follower_user_id'] ?>">
            <?php if(!empty($relationship['follower_user_icon_filename'])): // アイコン画像がある場合は表示 ?>
            <img src="/image/<?= $relationship['follower_user_icon_filename'] ?>"
                style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
            <?php endif; ?>

            <?= htmlspecialchars($relationship['follower_user_name']) ?>
            (ID: <?= htmlspecialchars($relationship['follower_user_id']) ?>)
        </a>
        (<?= $relationship['created_at'] ?>にフォローされました)
    </li>
    <?php endforeach; ?>
</ul>

<?php if ($select_sth->rowCount() === 0): ?>
<p>まだ誰もあなたをフォローしていません。</p>
<?php endif; ?>

<hr>
<a href="./profile.php?user_id=<?= $_SESSION['login_user_id'] ?>">自分のプロフィールに戻る</a>
