<?php
require_once __DIR__ . '/db.php'; // 改成你的数据库连接

define('BASE_URL', 'https://api.kuoyangzh.com/tomato/api/user/profile/invite/users');
define('DEFAULT_SIZE', 50);
define('MAX_RETRIES', 3);
$platform = '暖遇';

$params = [
    'view_mode' => 'full_screen',
    'sid' => '20KcuWA2hn1e5crstJqcvxyN0YJMq9G03jfY9WNi17FTIhqIuGn5AIi3',
    'cv' => 'ZHIYU2.0.0_Android',
    'cc' => 'TG000108880',
    'ownid' => '20001124',
    '_t' => time() * 1000
];

$db = getDbConnection();

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$page = 1;
$size = DEFAULT_SIZE;
$totalImported = 0;

$insertStmt = $db->prepare("
    INSERT INTO anchor_profile (
        uid, nick, avatar, level, is_quality, promotion_rate, promotion_status, total_reward, last_active_time, platform
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        nick=VALUES(nick),
        avatar=VALUES(avatar),
        level=VALUES(level),
        is_quality=VALUES(is_quality),
        promotion_rate=VALUES(promotion_rate),
        promotion_status=VALUES(promotion_status),
        total_reward=VALUES(total_reward),
        last_active_time=VALUES(last_active_time),
        platform=VALUES(platform)
");

do {
    $query = http_build_query($params + ['page' => $page, 'size' => $size]);
    $retry = 0; $items = [];

    do {
        curl_setopt($ch, CURLOPT_URL, BASE_URL.'?'.$query);
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        $items = $data['data']['items'] ?? [];
    } while (++$retry <= MAX_RETRIES && curl_errno($ch) && empty($items));

    foreach ($items as $user) {
        $insertStmt->execute([
            $user['uid'],
            $user['nick'],
            $user['portrait'] ?? '',
            $user['level'] ?? 1,
            $user['is_quality'] ? 1 : 0,
            $user['promotion_rate'] ?? 0,
            $user['promotion_status'] ?? 0,
            floatval($user['reward'] ?? 0),
            !empty($user['time']) ? date('Y-m-d H:i:s', strtotime(str_replace('.', '-', $user['time']))) : null,
            $platform
        ]);
        $totalImported++;
    }
    $page++;
    $actualSize = count($items);
} while ($actualSize >= $size);

curl_close($ch);

echo "<br>✅ {$platform} 主播同步完成，本次导入：{$totalImported} 位。<br>";
?>
