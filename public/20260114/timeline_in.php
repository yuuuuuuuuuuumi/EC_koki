<?php
$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) { 
  header("HTTP/1.1 302 Found");
  header("Location: /20260114/login.php");
  return;
}

// ログイン情報
$user_select_sth = $dbh->prepare("SELECT * from users WHERE id = :id");
$user_select_sth->execute([':id' => $_SESSION['login_user_id']]);
$user = $user_select_sth->fetch();

// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {

  $image_filename = null;
  if (!empty($_POST['image_base64'])) {
    $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

    $image_binary = base64_decode($base64);

    $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
    $filepath =  '/var/www/upload/image/' . $image_filename;
    file_put_contents($filepath, $image_binary);
  }

  // 投稿本文保存
  $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body) VALUES (:user_id, :body)");
  $insert_sth->execute([
      ':user_id' => $_SESSION['login_user_id'],
      ':body' => $_POST['body'],
  ]);

  // 投稿ID
  $entryId = $dbh->lastInsertId();

  // 画像
  if (!empty($_POST['image_base64_array']) && is_array($_POST['image_base64_array'])) {
      $image_insert_sth = $dbh->prepare("INSERT INTO entry_images (entry_id, image_filename) VALUES (:entry_id, :image_filename)");
    
      //最大4枚
      $images = array_slice($_POST['image_base64_array'], 0, 4);
    
      foreach ($images as $base64_data) {
          if (empty($base64_data)) continue;

          $base64 = preg_replace('/^data:.+base64,/', '', $base64_data);
          $image_binary = base64_decode($base64);
          $image_filename = strval(time()) . bin2hex(random_bytes(10)) . '.png';
          $filepath = '/var/www/upload/image/' . $image_filename;
        
          if (file_put_contents($filepath, $image_binary)) {
              $image_insert_sth->execute([
                  ':entry_id' => $entryId,
                  ':image_filename' => $image_filename,
              ]);
          }
      }
  }

  header("HTTP/1.1 303 See Other");
  header("Location: ./timeline_in.php");
  return;
}

$limit = 10; 
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
    . ' FROM bbs_entries'
    . ' INNER JOIN users ON bbs_entries.user_id = users.id'
    . ' LEFT OUTER JOIN user_relationships ON bbs_entries.user_id = user_relationships.followee_user_id'
    . ' WHERE user_relationships.follower_user_id = :login_user_id OR bbs_entries.user_id = :login_user_id'
    . ' GROUP BY bbs_entries.id'
    . ' ORDER BY bbs_entries.created_at DESC'
    . ' LIMIT :limit'; 

$select_sth = $dbh->prepare($sql);
$select_sth->bindValue(':login_user_id', $_SESSION['login_user_id'], PDO::PARAM_INT);
$select_sth->bindValue(':limit', $limit, PDO::PARAM_INT);
$select_sth->execute();

function bodyFilter (string $body): string
{
  $body = htmlspecialchars($body); 
  $body = nl2br($body); 

  $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);

  return $body;
}
include_once('header.php');
?>

<div>
  現在 <?= htmlspecialchars($user['name']) ?> (ID: <?= $user['id'] ?>) さんでログイン中
</div>
<div style="margin-bottom: 1em;">
  <a href="/20260114/setting/index.php">設定画面</a>
  /
  <a href="./users.php">会員一覧画面</a>
</div>

<form method="POST" action="">
  <textarea name="body" required></textarea>
  <div style="margin: 1em 0;">
    <input type="file" accept="image/*" id="imageInput" multiple>
  </div>
  <div id="base64Container"></div>
  <canvas id="imageCanvas" style="display: none;"></canvas>
  <button type="submit">送信</button>
</form>
<hr>

<div id="entryContainer"> 
    <?php foreach($select_sth as $entry): ?>
        <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
            <dt id="entry<?= htmlspecialchars($entry['id']) ?>">番号</dt>
            <dd><?= htmlspecialchars($entry['id']) ?></dd>
            <dt>投稿者</dt>
            <dd>
                <a href="./profile.php?user_id=<?= $entry['user_id'] ?>">
                    <?php if(!empty($entry['user_icon_filename'])): ?>
                        <img src="/image/<?= $entry['user_icon_filename'] ?>" style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
                    <?php endif; ?>
                    <?= htmlspecialchars($entry['user_name']) ?> (ID: <?= htmlspecialchars($entry['user_id']) ?>)
                </a>
            </dd>
            <dt>日時</dt>
            <dd><?= $entry['created_at'] ?></dd>
            <dt>内容</dt>
            <dd>
                <?= bodyFilter($entry['body']) ?>
                <?php
                $img_sth = $dbh->prepare("SELECT image_filename FROM entry_images WHERE entry_id = :entry_id");
                $img_sth->execute([':entry_id' => $entry['id']]);
                ?>
                <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px;">
                    <?php foreach ($img_sth as $img): ?>
                        <img src="/image/<?= htmlspecialchars($img['image_filename']) ?>" style="max-height: 10em; border: 1px solid #ccc; border-radius: 5px;">
                    <?php endforeach; ?>
                </div>
            </dd>
        </dl>
    <?php endforeach ?>
</div> <div id="bottomObserver" style="height: 10px;"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", async () => {
    const base64Container = document.getElementById("base64Container");
    base64Container.innerHTML = "";

    // 先頭4枚だけ取り出す
    const files = Array.from(imageInput.files).slice(0, 4);

    for (const file of files) {
      if (!file.type.startsWith('image/')) continue;

      // 画像の縮小が終わるまで待機
      const base64String = await new Promise((resolve) => {
        const reader = new FileReader();
        const image = new Image();

        reader.onload = () => {
          image.onload = () => {
            const canvas = document.getElementById("imageCanvas");
	    const context = canvas.getContext("2d");
	    const maxLength = 1000;
	    let width = image.naturalWidth;
	    let height = image.naturalHeight;

	    // サイズ計算
	    if (width > maxLength || height > maxLength) {
	      if (width > height) {
		height = maxLength * height / width;
		width = maxLength;
	      } else {
		width = maxLength * width / height;
		height = maxLength;
	      }
	    }
	    canvas.width = width;
	    canvas.height = height;
	    context.drawImage(image, 0, 0, width, height);
	    resolve(canvas.toDataURL("image/png"));
	  };
	  image.src = reader.result;
	};
	reader.readAsDataURL(file);
      });

      const hiddenInput = document.createElement("input");
      hiddenInput.type = "hidden";
      hiddenInput.name = "image_base64_array[]"; 
      hiddenInput.value = base64String;
      base64Container.appendChild(hiddenInput);
    }
  });
// 無限スクロール用設定
    let offset = 10; 
    let isLoading = false; 

    const observer = new IntersectionObserver(async (entries) => {
        if (entries[0].isIntersecting && !isLoading) {
            isLoading = true; 

            // 次のデータを取得
            const response = await fetch(`./timeline_json.php?offset=${offset}`);
            const data = await response.json();

            if (data.entries.length > 0) {
                const container = document.getElementById('entryContainer');
                
                data.entries.forEach(entry => {
                    let imagesHtml = '';
                    entry.image_filenames.forEach(fname => {
                        imagesHtml += `<img src="/image/${fname}" style="max-height: 10em; border: 1px solid #ccc; border-radius: 5px;">`;
                    });

                    // 新しい投稿
                    const html = `
                        <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
                            <dt id="entry${entry.id}">番号</dt>
                            <dd>${entry.id}</dd>
                            <dt>投稿者</dt>
                            <dd>
                                <a href="./profile.php?user_id=${entry.user_id}">
                                    ${entry.user_icon_file_url ? `<img src="${entry.user_icon_file_url}" style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">` : ''}
                                    ${entry.user_name} (ID: ${entry.user_id})
                                </a>
                            </dd>
                            <dt>日時</dt><dd>${entry.created_at}</dd>
                            <dt>内容</dt>
                            <dd>
                                ${entry.body}
                                <div style="display: flex; gap: 5px; flex-wrap: wrap; margin-top: 10px;">${imagesHtml}</div>
                            </dd>
                        </dl>`;
                    
                    container.insertAdjacentHTML('beforeend', html);
                });

                offset += data.entries.length; 
                isLoading = false; 
            }
        }
    }, { threshold: 1.0 }); 

    observer.observe(document.getElementById('bottomObserver'));
});
</script>

