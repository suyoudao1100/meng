<?php
require_once __DIR__ . '/db.php'; // 引入数据库连接
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, 'date=') === 0) {
            $_GET['date'] = substr($arg, 5);
        }
    }
}
// 抓取目标日期（支持 ?date=2025-07-06 参数）
$targetDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$targetDateStr = str_replace('-', '.', $targetDate); // 接口格式如 2025.07.06

// 请求参数
$params = [
    'page' => 1,
    'size' => 20,
    'sid' => '20WbRsS6IQo2QWz6Nvj5brzAGi0TI1eYUS9fGbdMmH6H3di0oysv0gi3i3',
    'cv' => 'DREAMBABY2.4.60_Android',
    'cc' => 'TG000112239',
    'ownid' => '55124537',
    'view_mode' => 'full_screen',
    '_t' => time() * 1000
];

$page = 1;
$maxPages = 100;
$db = getDbConnection();

do {
    $params['page'] = $page;
    $params['_t'] = time() * 1000;
    $url = 'https://api.xunyihn.com/artemis/api/user/profile/invite/earnings?' . http_build_query($params);

    // 请求接口
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $items = $data['data']['items'] ?? [];

    foreach ($items as $item) {
        if ($item['create_time'] != $targetDateStr) continue;

        $uid = $item['uid'];
        $nick = $item['nick'];
        $avatar = $item['portrait'];
        $is_quality = $item['is_quality'] ? 1 : 0;
        $reward_str = str_replace('元', '', $item['reward_str']);
        $reward = floatval($reward_str);
        $date = str_replace('.', '-', $item['create_time']);

        // 插入数据库（防止重复）
        $stmt = $db->prepare("
            INSERT IGNORE INTO anchor_daily_earning (uid, nick, avatar, reward, is_quality, stat_date)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$uid, $nick, $avatar, $reward*2, $is_quality, $date]);

        echo "✅ 插入成功：{$nick} ({$uid}) - {$reward} 元 - {$date}<br>";
    }

    $page++;
} while ($page <= $maxPages && count($items) >= $params['size']);
