<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

// 获取今日与昨日日期
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));

// 获取昨日收益数据
$stmt = $db->prepare("
    SELECT uid, nick, SUM(reward) as total 
    FROM anchor_daily_earning 
    WHERE stat_date = ? 
    GROUP BY uid
");
$stmt->execute([$yesterday]);
$yesterdayData = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $yesterdayData[$row['uid']] = floatval($row['total']);
}

// 获取今日数据并计算增长
$stmt2 = $db->prepare("
    SELECT uid, nick, SUM(reward) as total 
    FROM anchor_daily_earning 
    WHERE stat_date = ? 
    GROUP BY uid
");
$stmt2->execute([$today]);

$growth = [];
foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $uid = $row['uid'];
    $todayValue = floatval($row['total']);
    $yesterdayValue = $yesterdayData[$uid] ?? 0;
    $diff = $todayValue - $yesterdayValue;

    if ($diff > 0) {
        $growth[] = [
            'uid' => $uid,
            'nick' => $row['nick'],
            'today' => $todayValue,
            'yesterday' => $yesterdayValue,
            'growth' => $diff
        ];
    }
}

// 按增长额排序
usort($growth, fn($a, $b) => $b['growth'] <=> $a['growth']);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>收益增长榜</title>
</head>
<body>
    <h2>📈 收益增长榜（<?= $yesterday ?> → <?= $today ?>）</h2>
    <table border="1" cellpadding="6">
        <thead>
            <tr><th>昵称</th><th>昨日</th><th>今日</th><th>增长</th><th>详情</th></tr>
        </thead>
        <tbody>
            <?php foreach ($growth as $item): ?>
            <tr>
                <td><?= $item['nick'] ?></td>
                <td><?= $item['yesterday'] ?> 元</td>
                <td><?= $item['today'] ?> 元</td>
                <td style="color: green;">+<?= $item['growth'] ?> 元</td>
                <td><a href="anchor.php?uid=<?= $item['uid'] ?>" target="_blank">查看</a></td>
            </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</body>
</html>
