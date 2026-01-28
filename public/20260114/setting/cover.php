<?php
session_start();

if (empty($_SESSION['login_user_id'])) {
  header("HTTP/1.1 302 Found");
  header("Location: /login.php");
  return;
}

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');
$select_sth = $dbh->prepare("SELECT * FROM users WHERE id = :id");
$select_sth->execute([
    ':id' => $_SESSION['login_user_id'],
]);
$user = $select_sth->fetch();

if (isset($_POST['image_base64'])) {

  $image_filename = null;
  if (!empty($_POST['image_base64'])) {
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

    $image_binary = base64_decode($base64);

    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
    $filepath =  '/var/www/upload/image/' . $image_filename;
    file_put_contents($filepath, $image_binary);
  }

  $update_sth = $dbh->prepare("UPDATE users SET cover_filename = :cover_filename WHERE id = :id");
  $update_sth->execute([
      ':id' => $user['id'],
      ':cover_filename' => $image_filename,
  ]);

  header("HTTP/1.1 302 Found");
  header("Location: ./cover.php");
  return;
}
include_once('../header.php');
?>

<a href="./index.php">設定一覧に戻る</a>

<h1>カバー画像</h1>

<div>
  <?php if(empty($user['cover_filename'])): ?>
  現在未設定
  <?php else: ?>
  <img src="/image/<?= $user['cover_filename'] ?>"
    style="height: 5em; width: 10em; object-fit: cover;">
  <?php endif; ?>
</div>

<form method="POST" action="./cover.php">
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" name="image" id="imageInput">
  </div>
  <input id="imageBase64Input" type="hidden" name="image_base64">
  <canvas id="imageCanvas" style="display: none;"></canvas>
  <button type="submit">アップロード</button>
</form>

<hr>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) {
      return;
    }

    const file = imageInput.files[0];
    if (!file.type.startsWith('image/')){ 
      return;
    }

    // 画像縮小処理
    const imageBase64Input = document.getElementById("imageBase64Input"); 
    const canvas = document.getElementById("imageCanvas"); 
    const reader = new FileReader();
    const image = new Image();
    reader.onload = () => { // ファイルの読み込み完了
      image.onload = () => { // 画像として読み込み完了

        const originalWidth = image.naturalWidth; // 横幅
        const originalHeight = image.naturalHeight; // 高さ
        const maxLength = 1000; // 1000以下に縮小
        if (originalWidth <= maxLength && originalHeight <= maxLength) { 
            canvas.width = originalWidth;
            canvas.height = originalHeight;
        } else if (originalWidth > originalHeight) { 
            canvas.width = maxLength;
            canvas.height = maxLength * originalHeight / originalWidth;
        } else {
            canvas.width = maxLength * originalWidth / originalHeight;
            canvas.height = maxLength;
        }

        const context = canvas.getContext("2d");
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        imageBase64Input.value = canvas.toDataURL();
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
});
</script>
