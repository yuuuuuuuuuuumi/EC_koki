# 後期課題

課題の内容  
**[要件]**
* 会員登録&ログインした人のみが投稿できるサービスであること
* 会員同士のフォロー機能があること
* 自身がフォローしている人の投稿のみが時系列で表示される画面(=タイムライン)があること
* 投稿には自由に画像を投稿できること（大きい画像もブラウザ側で自動で縮小してからサーバーにアップロードすること）
 
**[追加実装]**
* 投稿に対して画像を1枚だけではなく複数枚(最大4枚)付けれるようにすること
* CSSを使って、スマートフォンでも見やすいデザイン
* タイムラインを無限スクロールにする

### タイムラインへのログイン方法
AWS Academy Learner Labを起動した後に、*powershell*でEC2インスタンスとつなげる。

```
ssh ec2-user@{IPアドレス} -i {秘密鍵ファイルのパス}
```

任意のWebブラウザにサイトのURLを入力する。

```
http://54.210.216.36/20260114/login.php
```

ユーザ情報がない場合は、*新規登録*を行い、ログインする。


## 会員登録&ログインした人のみが投稿できる
```timeline_in.php```の**5~9行目**でログインしていない人は強制的にログイン画面へ移動する。
```
if (empty($_SESSION['login_user_id'])) { 
    header("HTTP/1.1 302 Found");
    header("Location: /login.php");
    return;
 }
```


## 会員同士のフォロー機能があること
ユーザーとユーザーのフォロー関係を管理するテーブルのため、```user_relationships```という名前でテーブルを作成する。
```
CREATE TABLE `user_relationships` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `followee_user_id` INT UNSIGNED NOT NULL,
    `follower_user_id` INT UNSIGNED NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
);
```
```followee_user_id```にはフォローされる人のidを、```follower_user_id```にはフォローする人のidを保存するテーブル構成にする。

```follow.php```を作成する。

[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/follow.php](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/follow.php)

プロフィールページから遷移できるようにし、すでにフォローしている場合はそのことを表示するようにする。

[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/profile.php](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/profile.php)


## 自身がフォローしている人の投稿のみが時系列で表示される画面(=タイムライン)があること
```timeline_in.php```にフォローしている人と自分だけが表示されるようにして、時系列に表示されるようにする。
```
WHERE user_relationships.follower_user_id = :login_user_id 
   OR bbs_entries.user_id = :login_user_id
ORDER BY bbs_entries.created_at DESC
```

## 投稿には自由に画像を投稿できる
```timeline_in.php```で```maxLength```と```context.drawImage(image, 0, 0, width, height);```の部分を実装することで、
画像のサイズを計算し、縮小させる処理を行う。

```timeline_in.php```の```multiple```で画像を選択できるようになっており、```nginx/conf.d/default.conf```の```client_max_body_size 20M;```の設定により、20MBまでのデータであれば送信できるようにしている。

timeline_in.php:[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/timeline_in.php](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/timeline_in.php)
nginx/conf.d/default.conf:[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/nginx/conf.d/default.conf](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/nginx/conf.d/default.conf)

## 投稿に対して画像を1枚だけではなく複数枚(最大4枚)付けれるようにする
画像専用の```entry_images```テーブルを作成する。
```
CREATE TABLE entry_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_id INT UNSIGNED NOT NULL,  -- 親投稿のID（型を一致させる）
    image_filename VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_entry_id FOREIGN KEY (entry_id) 
        REFERENCES bbs_entries(id) ON DELETE CASCADE
);
```
既存の```bbs_entries```テーブルと```entry_images テーブル```を**entry_id**で紐付ける。
また、投稿が消えたら画像データも消えるようにする。

```nginx/conf.d/default.conf```に、複数枚の画像をサーバーが拒否しないように追記する。
```
client_max_body_size 20M;
```
```timeline_in.php```に複数枚の画像を縮小して一括送信する処理と投稿一覧のループ内で、各投稿に紐付く画像を```SELECT```して表示する処理などを実装する。
```php.ini```のデータサイズの制限を引き上げる
```
post_max_size = 20M
upload_max_filesize = 20M
```
ファイルの設定を反映させるため、Dockerコンテナを再起動させる。
```
docker-compose up -d --build
```

## CSSを使って、スマートフォンでも見やすいデザイン
```header.php```を作成し、```viewport```設定とデザインを整える```style.css```を読み込むようにする。
```
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/20260114/style.css">
```
```style.css```の実装

style.css:[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/style.css](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/style.css)

## タイムラインを無限スクロールにする
```timeline_json.php```の中身を、投稿IDごとに```entry_images```テーブルを検索し、```image_filenames```としてまとめるようにする。

```LIMIT```と```OFFSET```を実装し、取得件数と開始位置の制御を行う。

timeline_json.php:[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/timeline_json.php](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/timeline_json.php)

```timeline_in.php```の実装

初期表示数を```Limit```で10件までに制限したので、11件目からの取得を、タイムラインの一番下に```bottomObserver```を配置し、画面内に見えたときに、次の投稿を読み込むようにする。

```fetch```を使い、画面を維持したまま、新しいデータを一番下に貼り付けるようにする。

timeline_in.php:[https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/timeline_in.php](https://github.com/yuuuuuuuuuuumi/EC_koki/blob/main/public/20260114/timeline_in.php)
