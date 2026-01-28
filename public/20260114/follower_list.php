<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

// フォローしている会員の一覧
$select_sth = $dbh->prepare(
    'SELECT user_relationships.*, users.name AS follower_user_name, users.icon_filename AS follower_user_icon_filename'
    . ' FROM user_relationships INNER JOIN users ON user_relationships.follower_user_id = users.id'
    . ' WHERE user_relationships.followee_user_id = :followee_user_id'
    . ' ORDER BY user_relationships.id DESC'
);
$select_sth->execute([
    ':followee_user_id' => $_SESSION['login_user_id'],
]);
include_once('header.php');
?>

<h1>自分をフォローしているユーザー一覧（フォロワー）</h1>

<ul>
    <?php foreach($select_sth as $relationship): ?>
    <li>
        <a href="./profile.php?user_id=<?= $relationship['follower_user_id'] ?>">
            <?php if(!empty($relationship['follower_user_icon_filename'])):  ?>
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
