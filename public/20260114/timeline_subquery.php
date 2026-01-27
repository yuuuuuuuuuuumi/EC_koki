<?php

$dbh = new PDO('mysql:host=mysql;dbname=example_db', 'root', '');

session_start();
if (empty($_SESSION['login_user_id'])) { // 非ログインの場合利用不可
    header("HTTP/1.1 302 Found");
    header("Location: /20260114/login.php");
    return;
}

// 投稿処理
if (isset($_POST['body']) && !empty($_SESSION['login_user_id'])) {

    $image_filename = null;
    if (!empty($_POST['image_base64'])) {
        $base64 = preg_replace('/^data:.+base64,/', '', $_POST['image_base64']);

        // base64からバイナリにデコード
        $image_binary = base64_decode($base64);

        // ファイル名を決めてバイナリを出力
        $image_filename = strval(time()) . bin2hex(random_bytes(25)) . '.png';
        $filepath = '/var/www/upload/image/' . $image_filename;
        file_put_contents($filepath, $image_binary);
    }

    $insert_sth = $dbh->prepare("INSERT INTO bbs_entries (user_id, body, image_filename) VALUES (:user_id, :body, :image_filename)");
    $insert_sth->execute([
        ':user_id' => $_SESSION['login_user_id'], // 会員情報
        ':body' => $_POST['body'], // 投稿本文
        ':image_filename' => $image_filename, // 画像
    ]);

    // リダイレクト
    header("HTTP/1.1 303 See Other");
    header("Location: ./timeline_subquery.php"); // リダイレクト先を変更
    return;
}


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

<dl id="entryTemplate" style="display: none; margin-bottom: 1em; padding-bottom: 1em; border-bottom: 1px solid #ccc;">
  <dt>番号</dt>
  <dd data-role="entryIdArea"></dd>
  <dt>投稿者</dt>
  <dd>
    <a href="" data-role="entryUserAnchor">
      <img data-role="entryUserIconImage"
        style="height: 2em; width: 2em; border-radius: 50%; object-fit: cover;">
      <span data-role="entryUserNameArea"></span>
    </a>
  </dd>
  <dt>日時</dt>
  <dd data-role="entryCreatedAtArea"></dd>
  <dt>内容</dt>
  <dd data-role="entryBodyArea">
  </dd>
</dl>
<div id="entriesRenderArea"></div>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const entryTemplate = document.getElementById('entryTemplate');
  const entriesRenderArea = document.getElementById('entriesRenderArea');

  const request = new XMLHttpRequest();
  request.onload = (event) => {
    const response = event.target.response;
    response.entries.forEach((entry) => {
      // テンプレートとするものから要素をコピー
      const entryCopied = entryTemplate.cloneNode(true);

      // display: none を display: block に書き換える
      entryCopied.style.display = 'block';

      // 番号(ID)を表示
      entryCopied.querySelector('[data-role="entryIdArea"]').innerText = entry.id.toString();
      
      // アイコン画像が存在する場合は表示 なければimg要素ごと非表示に
      if (entry.user_icon_file_url !== undefined && entry.user_icon_file_url !== '') {
        entryCopied.querySelector('[data-role="entryUserIconImage"]').src = entry.user_icon_file_url;
      } else {
        entryCopied.querySelector('[data-role="entryUserIconImage"]').display = 'none';
      }

      // 名前を表示
      entryCopied.querySelector('[data-role="entryUserNameArea"]').innerText = entry.user_name;

      // 名前のところのリンク先(プロフィール)のURLを設定
      entryCopied.querySelector('[data-role="entryUserAnchor"]').href = entry.user_profile_url;

      // 投稿日時を表示
      entryCopied.querySelector('[data-role="entryCreatedAtArea"]').innerText = entry.created_at;

      // 本文を表示 (ここはHTMLなのでinnerHTMLで)
      entryCopied.querySelector('[data-role="entryBodyArea"]').innerHTML = entry.body;

      // 画像が存在する場合に本文の下部に画像を表示
      if (entry.image_file_url !== undefined && entry.image_file_url !== '') {
        const imageElement = new Image();
        imageElement.src = entry.image_file_url; // 画像URLを設定
        imageElement.style.display = 'block'; // ブロック要素にする (img要素はデフォルトではインライン要素のため)
        imageElement.style.marginTop = '1em'; // 画像上部の余白を設定
        imageElement.style.maxHeight = '300px'; // 画像を表示する最大サイズ(縦)を設定
        imageElement.style.maxWidth = '300px'; // 画像を表示する最大サイズ(横)を設定
        entryCopied.querySelector('[data-role="entryBodyArea"]').appendChild(imageElement); // 本文エリアに画像を追加
      }

      // 最後に実際の描画を行う
      entriesRenderArea.appendChild(entryCopied);
    });
  }
  request.open('GET', '/timeline_json.php', true); // timeline_json.php を叩く
  request.responseType = 'json';
  request.send();


 // 以下画像縮用
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
