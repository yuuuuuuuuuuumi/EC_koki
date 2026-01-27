<?php

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) { // 非ログインの場合利用不可
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
}

// 投稿処理 (timeline.phpと同じロジックをそのまま使用)
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {

    $image_filename = null;
    if (!empty($_POST['image_base64'])) {
        // 先頭の data:~base64, のところは削る
        $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

        // base64からバイナリにデコードする
        $image_binary = base64_decode($base64);

        // 新しいファイル名を決めてバイナリを出力する
        $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
        $filepath = '/var/www/upload/image/' . $image_filename;
        file_put_contents($filepath, $image_binary);
    }

    // insertする
    $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
    $insert_sth->execute([
        ':user_id' => $_SESSION['login_user_id'], // ログインしている会員情報の主キー
        ':body' => $_POST['body'], // フォームから送られてきた投稿本文
        ':image_filename' => $image_filename, // 保存した画像の名前 (nullの場合もある)
    ]);

    // 処理が終わったらリダイレクトする
    header("HTTP/1.1 303 See Other");
    header("Location: ./timeline_subquery.php"); // ★リダイレクト先を自身に変更
    return;
}


// 投稿データを取得。
$sql = 'SELECT bbs_entries.*, users.name AS user_name, users.icon_filename AS user_icon_filename'
    . ' FROM bbs_entries'
    . ' INNER JOIN users ON bbs_entries.user_id = users.id'
    // WHERE句でサブクエリを使用
    . ' WHERE bbs_entries.user_id IN ('
    // サブクエリ開始: 自身とフォローしているユーザーのIDリストを取得
    . '  SELECT followee_user_id FROM user_relationships WHERE follower_user_id = :login_user_id'
    . '  UNION SELECT :login_user_id' // 自身のIDもリストに含める
    . ' )'
    . ' ORDER BY bbs_entries.created_at DESC';

$select_sth = $dbh->prepare($sql);
$select_sth->execute([
    ':login_user_id' => $_SESSION['login_user_id'],
]);

// bodyのHTMLを出力するための関数を用意する
function bodyFilter (string $body): string
{
    $body = htmlspecialchars($body); // エスケープ処理
    $body = nl2br($body); // 改行文字を<br>要素に変換
    // >>1 といった文字列を該当番号の投稿へのページ内リンクとする (レスアンカー機能)
    // 「>」(半角の大なり記号)は htmlspecialchars() でエスケープされているため注意
    $body = preg_replace('/&gt;&gt;(\d+)/', '<a href="#entry$1">&gt;&gt;$1</a>', $body);
    return $body;
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>タイムライン (サブクエリ版)</title>
</head>
<body>

<?php if(empty($_SESSION['login_user_id'])): ?>
  投稿するには<a href="/20251210/login.php">ログイン</a>が必要です。
<?php else: ?>
  現在ログイン中 (<a href="/setting/index.php">設定画面はこちら</a>)
  <form method="POST">
    <textarea name="body" required></textarea>
    <div style="margin: 1em 0;">
      <input type="file" accept="image/*" name="image" id="imageInput">
    </div>
    <input id="imageBase64Input" type="hidden" name="image_base64"><canvas id="imageCanvas" style="display: none;"></canvas><button type="submit">送信</button>
  </form>
<?php endif; ?>
<hr>

<h2>タイムライン (サブクエリ版)</h2>

<?php foreach($select_sth as $entry): ?>
  <dl style="margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
    <dt id="entry<?= htmlspecialchars($entry['id']) ?>">
      番号
    </dt>
    <dd>
      <?= htmlspecialchars($entry['id']) ?>
    </dd>
    <dt>
      投稿者
    </dt>
    <dd>
      <a href="/profile.php?user_id=<?= $entry['user_id'] ?>">
        <?php if(!empty($entry['user_icon_filename'])): // アイコン画像がある場合は表示 ?>
        <img src="/image/<?= htmlspecialchars($entry['user_icon_filename']) ?>"
          style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
        <?php endif; ?>

        <?= htmlspecialchars($entry['user_name']) ?>
        (ID: <?= htmlspecialchars($entry['user_id']) ?>)
      </a>
    </dd>
    <dt>日時</dt>
    <dd><?= htmlspecialchars($entry['created_at']) ?></dd>
    <dt>内容</dt>
    <dd>
      <?= bodyFilter($entry['body']) ?>
      <?php if(!empty($entry['image_filename'])): ?>
      <div>
        <img src="/image/<?= htmlspecialchars($entry['image_filename']) ?>" style="max-height: 10em;">
      </div>
      <?php endif; ?>
    </dd>
  </dl>
<?php endforeach ?>

<script>
document.addEventListener("DOMContentLoaded", () => {
// ... (JavaScriptコードは省略せず、そのまま記述してください) ...
  const imageInput = document.getElementById("imageInput");
  imageInput.addEventListener("change", () => {
    if (imageInput.files.length < 1) {
      // 未選択の場合
      return;
    }

    const file = imageInput.files[0];
    if (!file.type.startsWith('image/')){ // 画像でなければスキップ
      return;
    }

    // 画像縮小処理
    const imageBase64Input = document.getElementById("imageBase64Input"); // base64を送るようのinput
    const canvas = document.getElementById("imageCanvas"); // 描画するcanvas
    const reader = new FileReader();
    const image = new Image();
    reader.onload = () => { // ファイルの読み込み完了したら動く処理を指定
      image.onload = () => { // 画像として読み込み完了したら動く処理を指定

        // 元の縦横比を保ったまま縮小するサイズを決めてcanvasの縦横に指定する
        const originalWidth = image.naturalWidth; // 元画像の横幅
        const originalHeight = image.naturalHeight; // 元画像の高さ
        const maxLength = 1000; // 横幅も高さも1000以下に縮小するものとする
        if (originalWidth <= maxLength && originalHeight <= maxLength) { // どちらもmaxLength以下の場合そのまま
            canvas.width = originalWidth;
            canvas.height = originalHeight;
        } else if (originalWidth > originalHeight) { // 横長画像の場合
            canvas.width = maxLength;
            canvas.height = maxLength * originalHeight / originalWidth;
        } else { // 縦長画像の場合
            canvas.width = maxLength * originalWidth / originalHeight;
            canvas.height = maxLength;
        }

        // canvasに実際に画像を描画 (canvasはdisplay:noneで隠れているためわかりにくいが...)
        const context = canvas.getContext("2d");
        context.drawImage(image, 0, 0, canvas.width, canvas.height);

        // canvasの内容をbase64に変換しinputのvalueに設定
        imageBase64Input.value = canvas.toDataURL();
      };
      image.src = reader.result;
    };
    reader.readAsDataURL(file);
  });
});
</script>
</body>
</html>
