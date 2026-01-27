<?php
// セッションIDの取得(なければ新規で作成&設定)
$session_cookie_name = 'session_id';
$session_id = $_COOKIE[$session_cookie_name] ?? base64_encode(random_bytes(64));
if (!isset($_COOKIE[$session_cookie_name])) {
  setcookie($session_cookie_name, $session_id);
}

// 接続 (redisコンテナの6379番ポートに接続)
$redis = new Redis();
try {
    $redis->connect('redis', 6379);
} catch (RedisException $e) {
    die("Redis接続エラー: " . $e->getMessage());
}

// redisにセッション変数を保存しておくキーを決定します。
$redis_session_key = "session-" . $session_id;

// 既にセッション変数(の配列)が何かしら格納されていればそれを、
// なければ空の配列を $session_values変数に保存。
$session_values = $redis->exists($redis_session_key)
    ? json_decode($redis->get($redis_session_key), true)
    : [];

// 前回のアクセス日時を取得
$last_access_time = $session_values["last_access_time"] ?? 'firstaccess';

// 現在の日時を取得（日本時間でフォーマット）
date_default_timezone_set('Asia/Tokyo');
$current_time_display = date('Y年m月d日 H時i分s秒'); // 表示 
$current_time_storage = time(); // 保存

// カウンタ処理 (前回からの変更なし)
$access_count = $session_values["access_count"] ?? 0;
$access_count++;

// 値の保存
$session_values["access_count"] = $access_count;
$session_values["last_access_time"] = $current_time_display;

// 更新されたセッション変数配列をJSON形式でRedisに保存
$redis->set($redis_session_key, json_encode($session_values));


// 結果
echo "<p>このセッションでの <strong>{$access_count}回目</strong> のアクセスです！</p>";
echo "<p>前回のアクセス日時: <strong>{$last_access_time}</strong></p>";
echo "<p>今回のアクセス日時: {$current_time_display}</p>";

?>
