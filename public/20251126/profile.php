<?php
$user = null;
if (!empty($_GET['user_id'])) {
  $user_id = $_GET['user_id'];
  // DBに接続
  $dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
  // 対象の会員情報を引く
  $select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
  $select_sth->execute([
    ':id' => $user_id,
  ]);
  $user = $select_sth->fetch();
}
if (empty($user)) {
  header("HTTP/1.1 404 Not Found");
  print("そのようなユーザーIDの会員情報は存在しません");
  return;
}

/**
 * 生年月日から現在の年齢を計算する
 * @param string|null $birthdate 'YYYY-MM-DD' 形式の生年月日
 * @return int|null 年齢。生年月日が無効な場合はnull
 */
function calculate_age(?string $birthdate): ?int
{
    if (empty($birthdate) || !strtotime($birthdate)) {
        return null;
    }
    $birth = new DateTime($birthdate);
    $now = new DateTime();
    $interval = $now->diff($birth);
    return $interval->y;
}

// ユーザーの年齢を計算
$age = calculate_age($user['birthdate']);

// この人の投稿データを取得
$select_sth = $dbh->prepare(
  'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
  . ' FROM bbs_entries INNER JOIN users ON bbs_entries.user_id = users.id'
  . ' WHERE user_id = :user_id'
  . ' ORDER BY bbs_entries.created_at DESC'
);
$select_sth->execute([
  ':user_id' => $user_id,
]);
?>
<a href="./bbs.php">掲示板に戻る</a>

<div style="
  width: 100%; height: 15em;
  <?php if(!empty($user['cover_filename'])): ?>
  background: url('/image/<?= $user['cover_filename'] ?>') center;
  background-size: cover;
  <?php endif; ?>
"></div>

<div style="position: relative; height: 5em; margin-bottom: 1em;">
  <div style="position: absolute; top: -5em;">
    <div style="display: flex; align-items: end; justify-content: start;">
      <div style="margin: 0 1em; height: 10em; width: 10em; border: 3px solid white; border-radius: 50%;">
        <?php if(empty($user['icon_filename'])): ?>
        <div style="height: 100%; width: 100%; border-radius: 50%; background-color: lightgray; display: flex; justify-content: center; align-items: center;">
          <div>アイコン未設定</div>
        </div>
        <?php else: ?>
        <img src="/image/<?= $user['icon_filename'] ?>"
          style="height: 100%; width: 100%; border-radius: 50%; object-fit: cover;">
        <?php endif; ?>
      </div>
      <h1><?= htmlspecialchars($user['name']) ?></h1>
      <?php if ($age !== null): ?>
      <p style="font-size: 1.2em; margin-left: 1em;">(<?= $age ?>歳)</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<div>
</div>

<div>
  <?= nl2br(htmlspecialchars($user['introduction'] ?? '')) ?>
</div>

<hr>
<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt>日時</dt>
    <dd><?= $entry['created_at'] ?></dd>
    <dt>内容</dt>
    <dd>
      <?= htmlspecialchars($entry['body']) ?>
      <?php if(!empty($entry['image_filename'])): ?>
      <div>
        <img src="/image/<?= $entry['image_filename'] ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>
