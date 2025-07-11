<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();
$today = date('Y-m-d');

$stmt = $db->prepare("SELECT * FROM anchor_daily_earning WHERE stat_date = ? ORDER BY reward DESC LIMIT 20");
$stmt->execute([$today]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>主播收益排行榜</title>
    <style>
        body { font-family: "微软雅黑"; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: center; }
        img { border-radius: 50%; }
    </style>
</head>
<body>
    <h2>📊 今日主播收益排行（<?= $today ?>）</h2>
    <table>
        <thead>
            <tr>
                <th>头像</th><th>昵称</th><th>UID</th><th>收益 (元)</th><th>优质</th><th>详情</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($data as $row): ?>
            <tr>
                <td><img src="<?= $row['avatar'] ?>" width="50"></td>
                <td><?= htmlspecialchars($row['nick']) ?></td>
                <td><?= $row['uid'] ?></td>
                <td><?= $row['reward'] ?></td>
                <td><?= $row['is_quality'] ? '✅' : '❌' ?></td>
                <td><a href="anchor.php?uid=<?= $row['uid'] ?>" target="_blank">查看</a></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</body>
</html>
