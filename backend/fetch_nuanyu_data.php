<?php
require_once __DIR__ . '/db.php';  // 数据库连接

// 默认获取昨天的数据
$dates = [date('Y/m/d', strtotime('-1 day'))];

$page = 1;
$size = 200; // 支持最大200，看接口限制

// 暖遇固定参数
$sid = '20KcuWA2hn1e5crstJqcvxyN0YJMq9G03jfY9WNi17FTIhqIuGn5AIi3';
$cv = 'ZHIYU2.0.0_Android';
$cc = 'TG000108880';
$ownid = '20001124';

$db = getDbConnection();
$platform = '暖遇';

foreach ($dates as $date) {
    $url = "https://api.kuoyangzh.com/tomato/api/user/profile/invite/earnings_day_detail?" .
        "date=" . urlencode($date) .
        "&type=2&page=$page&size=$size&view_mode=full_screen" .
        "&sid=" . urlencode($sid) .
        "&cv=" . urlencode($cv) .
        "&cc=" . urlencode($cc) .
        "&ownid=" . urlencode($ownid) .
        "&padding_top=36.0&padding_bottom=0.0&iphone_island=0" .
        "&_t=" . (time()*1000);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (!empty($data['data']['items'])) {
        foreach ($data['data']['items'] as $v) {
            $uid = $v['uid'];
            $nick = trim($v['nick']);
            $avatar = $v['portrait'] ?? ''; // 没有可以留空
            $is_quality = $v['is_quality'] ?? 0;
            $reward = floatval($v['all_earning']);
            $video_reward = floatval($v['live_earning']);
            $gift_reward = floatval($v['gift_earning']);
            $text_reward = floatval($v['im_earning']);
            $stat_date = str_replace('/', '-', $date);

            $stmt = $db->prepare("
                INSERT INTO anchor_daily_earning (
                    uid, nick, avatar, reward, is_quality, stat_date, platform,
                    video_reward, gift_reward, text_reward
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    nick=VALUES(nick),
                    avatar=VALUES(avatar),
                    reward=VALUES(reward),
                    is_quality=VALUES(is_quality),
                    platform=VALUES(platform),
                    video_reward=VALUES(video_reward),
                    gift_reward=VALUES(gift_reward),
                    text_reward=VALUES(text_reward)
            ");

            $stmt->execute([
                $uid,
                $nick,
                $avatar,
                $reward,
                $is_quality,
                $stat_date,
                $platform,
                $video_reward,
                $gift_reward,
                $text_reward
            ]);

            echo "✅ 已写入：$nick ($uid) $stat_date $reward 元<br>";
        }
    } else {
        echo "日期 $date - 无数据<br>";
    }

    sleep(0.2); // 防止接口拉太快
}
?>
