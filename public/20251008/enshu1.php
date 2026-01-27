<?php

// セッションIDの取得(なければ新規で作成&設定)
$session_cookie_name = 'session_id';
// $_COOKIE['session_id'] があればそれを、なければランダムなIDを生成
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

// redisにセッション変数を保存しておくキーを決めておきます。
$redis_session_key = "session-" . $session_id;

// 既にセッション変数(の配列)が何かしら格納されていればそれを、
// なければ空の配列を $session_values変数に保存。
$session_values = $redis->exists($redis_session_key)
  ? json_decode($redis->get($redis_session_key), true)
  : [];

// アクセスカウンタ実装
$access_count = $session_values["access_count"] ?? 0;
$access_count++;
$session_values["access_count"] = $access_count;
$redis->set($redis_session_key, json_encode($session_values));

// 結果
echo "<h1>セッション別アクセスカウンタ</h1>";
echo "<p>このセッションでの <strong>{$access_count}回目</strong> のアクセスです！</p>";

?>
