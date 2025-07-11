<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

// 查询近 3 天的日期
$days = [];
for ($i = 0; $i < 3; $i++) {
    $days[] = date('Y-m-d', strtotime("-{$i} day"));
}

// 查询近3天内 所有主播的每日收益
$stmt = $db->prepare("
    SELECT uid, nick, stat_date, reward
    FROM anchor_daily_earning
    WHERE stat_date IN (?, ?, ?)
    ORDER BY uid, stat_date
");
$stmt->execute($days);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 汇总数据 [uid => [total => x, days => count]]
$map = [];
foreach ($rows as $row) {
    $uid = $row['uid'];
    if (!isset($map[$uid])) {
        $map[$uid] = [
            'uid' => $uid,
            'nick' => $row['nick'],
            'total' => 0,
            'days' => 0
        ];
    }
    $map[$uid]['total'] += floatval($row['reward']);
    $map[$uid]['days']++;
}

// 过滤出连续3日都有数据，且累计低于15元的主播
$lowList = array_filter($map, function($v) {
    return $v['days'] === 3 && $v['total'] < 15;
});
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>低效主播预警榜</title>
</head>
<body>
    <h2>🚨 低效主播预警榜（近3日累计收益 &lt; 15 元）</h2>
    <p>共 <?= count($lowList) ?> 人</p>
    <table border="1" cellpadding="6">
        <thead>
            <tr>
                <th>昵称</th><th>UID</th><th>近3日总收益</th><th>详情</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lowList as $a): ?>
            <tr>
                <td><?= htmlspecialchars($a['nick']) ?></td>
                <td><?= $a['uid'] ?></td>
                <td style="color:red"><?= $a['total'] ?> 元</td>
