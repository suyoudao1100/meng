<?php
require_once __DIR__ . '/db.php'; // 数据库连接

define('BASE_URL', 'https://api.xunyihn.com/artemis/api/user/profile/invite/users');
define('MAX_PAGES', 200); // 可增大页数保证采集全量（接口如果最多2千主播就100页；如1万主播就500页）
define('PAGE_SIZE', 20);  // 每页条数，接口支持多少填多少（比如100更快）

$params = [
    'sid' => '20WbRsS6IQo2QWz6Nvj5brzAGi0TI1eYUS9fGbdMmH6H3di0oysv0gi3i3',
    'cv' => 'DREAMBABY2.4.60_Android',
    'cc' => 'TG000112239',
    'ownid' => '55124537',
    'view_mode' => 'full_screen',
    '_t' => time() * 1000,
    'size' => PAGE_SIZE
];

$db = getDbConnection();
$platform = '梦宝宝';

// 1. 拉取接口所有页，汇总全量主播
$latestAnchors = [];
$page = 1;
$all_uid_set = []; // 防止接口分页返回重复主播

do {
    $params['page'] = $page;
    $params['_t'] = time() * 1000;
    $url = BASE_URL . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'User-Agent: Mozilla/5.0'
        ]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    $items = $data['data']['items'] ?? [];

    if (empty($items)) break;

    foreach ($items as $item) {
        $uid = $item['uid'];
        // 防止接口分页返回重复主播
        if (isset($all_uid_set[$uid])) continue;
        $all_uid_set[$uid] = 1;

        $latestAnchors[] = [
            'uid' => $uid,
            'nick' => $item['nick'],
            'avatar' => $item['portrait'] ?? '',
            'level' => $item['level'] ?? 1,
            'is_quality' => $item['is_quality'] ? 1 : 0,
            'promotion_rate' => $item['promotion_rate'] ?? 0,
            'promotion_status' => $item['promotion_status'] ?? 0,
            'total_reward' => floatval($item['reward']*2 ?? 0),
            'last_active_time' => !empty($item['time']) ? date('Y-m-d H:i:s', strtotime(str_replace('.', '-', $item['time']))) : null,
        ];
    }
    $page++;
    $hasMore = $data['data']['has_more'] ?? false;
} while ($page <= MAX_PAGES && $hasMore);

echo "✅ 本次采集接口主播总数：" . count($latestAnchors) . "<br>";

// 2. 拉取主表所有主播
$stmt = $db->prepare("SELECT uid, nick FROM anchor_profile WHERE platform = ?");
$stmt->execute([$platform]);
$dbAnchors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dbUIDs = array_column($dbAnchors, 'uid');
$dbUid2Nick = [];
foreach ($dbAnchors as $row) {
    $dbUid2Nick[$row['uid']] = $row['nick'];
}

// 3. 检查“流失/解绑主播”（即主表存在但接口不返回的主播，或已注销昵称）并写日志
$latestUIDs = array_column($latestAnchors, 'uid');
// A. 不在最新接口的UID
$unbindUIDs = array_diff($dbUIDs, $latestUIDs);
// B. 数据库中昵称含“已注销”
$unbindByName = [];
foreach ($dbAnchors as $row) {
    if (strpos($row['nick'], '已注销') !== false || strpos($row['nick'], '注销用户') !== false) {
        $unbindByName[] = $row['uid'];
    }
}
$allUnbindUIDs = array_unique(array_merge($unbindUIDs, $unbindByName));

// 只写首次流失记录（anchor_unbind_log UID唯一索引！）
foreach ($allUnbindUIDs as $uid) {
    $nick = $dbUid2Nick[$uid] ?? '';
    $note = (strpos($nick, '已注销') !== false) ? '自动检测流失【含已注销】' : '自动检测流失';
    $stmt = $db->prepare("INSERT IGNORE INTO anchor_unbind_log (uid, nick, event, occur_date, note, platform) VALUES (?, ?, 'unbind', CURDATE(), ?, ?)");
    $stmt->execute([$uid, $nick, $note, $platform]);
}

// 4. 检查“新增主播”并写日志
$newUIDs = array_diff($latestUIDs, $dbUIDs);
foreach ($latestAnchors as $a) {
    if (in_array($a['uid'], $newUIDs)) {
        $stmt = $db->prepare("INSERT IGNORE INTO anchor_unbind_log (uid, nick, event, occur_date, note, platform) VALUES (?, ?, 'new', CURDATE(), ?, ?)");
        $stmt->execute([$a['uid'], $a['nick'], '自动检测新增', $platform]);
    }
}

// 5. 写入主表 anchor_profile（全量 upsert，接口只要有的都同步）
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
$totalImported = 0;
foreach ($latestAnchors as $a) {
    $insertStmt->execute([
        $a['uid'], $a['nick'], $a['avatar'], $a['level'],
        $a['is_quality'], $a['promotion_rate'], $a['promotion_status'],
        $a['total_reward']*2, $a['last_active_time'], $platform
    ]);
    $totalImported++;
}

echo "<br>✅ 主播同步完成：本次同步主播数：{$totalImported} 位。<br>";

// 6. 简单统计：接口主播数、主表主播数、日志流失新增数
$stmt = $db->prepare("SELECT COUNT(*) FROM anchor_profile WHERE platform = ?");
$stmt->execute([$platform]);
$totalAnchor = $stmt->fetchColumn();
echo "<br>【接口主播总数】".count($latestAnchors)." ，【主表总数】{$totalAnchor} ，【流失日志累计】".count($allUnbindUIDs)." ，【新增日志累计】".count($newUIDs)." <br>";

?>
