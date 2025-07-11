<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();
$platform = $_GET['platform'] ?? '梦宝宝';

$yesterday = date('Y-m-d', strtotime('-1 day'));
$day2 = date('Y-m-d', strtotime('-2 day'));
$day3 = date('Y-m-d', strtotime('-3 day'));

// 排序参数
$order_field = $_GET['order'] ?? 'yesterday_reward';
$order_dir = $_GET['dir'] ?? 'desc';

// 顶部统计数据
$stmt = $db->prepare("SELECT SUM(reward) FROM anchor_daily_earning WHERE stat_date = ? AND platform = ?");
$stmt->execute([$yesterday, $platform]);
$totalReward = $stmt->fetchColumn() ?? 0;

$stmt = $db->prepare("SELECT COUNT(DISTINCT uid) FROM anchor_daily_earning WHERE stat_date = ? AND platform = ?");
$stmt->execute([$yesterday, $platform]);
$newAnchors = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM anchor_profile WHERE is_quality = 1 AND platform = ?");
$stmt->execute([$platform]);
$certifiedCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM anchor_profile WHERE platform = ?");
$stmt->execute([$platform]);
$totalAnchorCount = $stmt->fetchColumn();
$certifiedRate = $totalAnchorCount > 0 ? round($certifiedCount / $totalAnchorCount * 100, 1) : 0;

// 1. 查出所有昨日主播数据（带关联字段）
$stmt = $db->prepare("
    SELECT
        ade.uid, ap.nick, ap.avatar, ap.is_quality, ap.wechat, ap.remark, ap.level, ap.earning_score, ap.total_reward,
        ade.reward as yesterday_reward,
        (SELECT reward FROM anchor_daily_earning WHERE uid=ade.uid AND stat_date=? AND platform=?) as reward2,
        (SELECT reward FROM anchor_daily_earning WHERE uid=ade.uid AND stat_date=? AND platform=?) as reward3,
        (SELECT SUM(reward) FROM anchor_daily_earning WHERE uid=ade.uid AND platform=?) as total_earning,
        (SELECT COUNT(DISTINCT stat_date) FROM anchor_daily_earning WHERE uid=ade.uid AND platform=?) as active_days
    FROM anchor_daily_earning ade
    LEFT JOIN anchor_profile ap ON ade.uid = ap.uid AND ap.platform = ade.platform
    WHERE ade.stat_date = ? AND ade.platform = ?
");
$stmt->execute([$day2, $platform, $day3, $platform, $platform, $platform, $yesterday, $platform]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. 补充字段（PHP里算好 ratio）
foreach ($rows as &$a) {
    $a['r1'] = floatval($a['yesterday_reward']);
    $a['r2'] = floatval($a['reward2']);
    $a['r3'] = floatval($a['reward3']);
    $a['active_days'] = intval($a['active_days']);
    $a['avg_daily'] = $a['active_days'] > 0 ? round(floatval($a['total_earning']) / $a['active_days'], 2) : 0;
    $a['ratio'] = $a['avg_daily'] > 0 ? round($a['r1'] / $a['avg_daily'] * 100, 1) : 0;
}
unset($a);

// 3. 排序（仅两种方式）
if ($order_field == 'ratio') {
    usort($rows, function($a, $b) use ($order_dir) {
        return $order_dir == 'asc'
            ? ($a['ratio'] <=> $b['ratio'])
            : ($b['ratio'] <=> $a['ratio']);
    });
} else {
    // 默认按昨日收益降序
    usort($rows, function($a, $b) use ($order_dir) {
        return $order_dir == 'asc'
            ? ($a['r1'] <=> $b['r1'])
            : ($b['r1'] <=> $a['r1']);
    });
}

// 4. 分页
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$totalRows = count($rows);
$totalPages = ceil($totalRows / $limit);
$rows = array_slice($rows, ($page-1)*$limit, $limit);

// 排序按钮函数
function sort_link($label, $field, $current, $dir) {
    $icon = '';
    if ($current == $field) {
        $icon = $dir == 'asc' ? '↑' : '↓';
        $next_dir = $dir == 'asc' ? 'desc' : 'asc';
    } else {
        $next_dir = 'desc';
    }
    $q = $_GET;
    $q['order'] = $field;
    $q['dir'] = $next_dir;
    return "<a class='sort-btn ".($current==$field?'active':'')."' href='?".http_build_query($q)."'>$label $icon</a>";
}
?>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>昨日数据大屏</title>
    <style>
        body { font-family: "微软雅黑", sans-serif; background: #f6f8fb; padding: 40px; }
        h1 { color: #e92e90; font-size: 30px; font-weight: bold; }
        .cards { display: flex; gap: 24px; margin-bottom: 34px; }
        .card { background: #fff; padding: 20px 32px; border-radius: 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.07); flex: 1; }
        .card h2 { margin: 0 0 10px; font-size: 20px; color: #888; }
        .card p { font-size: 26px; font-weight: bold; color: #fa2c50; margin:0; }
        .card .desc { font-size:14px;color:#aaa; margin-top:4px;}
        .dash-table { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 12px; border-bottom: 1px solid #f2f2f2; text-align: center; }
        th { background: #fcfcfc; font-weight: 600; color: #e92e90; font-size:16px;}
        tr:last-child td { border-bottom: none;}
        .sort-btn { color: #e92e90; text-decoration:none; padding: 0 2px; font-size:16px;}
        .sort-btn.active {font-weight:bold; color:#fa2c50;}
        .avatar {border-radius:50%;width:38px;height:38px;object-fit:cover;}
    </style>
</head>
<body>
    <a class="btn" style="background:#888;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;display:inline-block;margin-bottom:20px;font-weight:bold;" 
href="index.php?platform=<?= urlencode($platform) ?>&remark_keyword=<?= urlencode($keyword ?? '') ?>">← 返回首页</a>

    <h1>✨ 昨日数据概览（<?= $yesterday ?>）</h1>
    <div class="cards">
        <div class="card">
            <h2>主播总收益</h2>
            <p>¥ <?= number_format($totalReward*2, 2) ?></p>
            <div class="desc">昨日全部主播总收益</div>
        </div>
        <div class="card">
            <h2>新增主播</h2>
            <p><?= $newAnchors ?> 位</p>
            <div class="desc">昨日首次出现的主播</div>
        </div>
        <div class="card">
            <h2>已认证主播</h2>
            <p><?= $certifiedCount ?> 位 / <?= $certifiedRate ?>%</p>
            <div class="desc">认证比例</div>
        </div>
        <div class="card">
            <h2>主播总数</h2>
            <p><?= $totalAnchorCount ?> 位</p>
            <div class="desc">历史累计</div>
        </div>
    </div>

    <h2 style="margin-top:32px; margin-bottom: 0;">昨日主播收益（<?= $yesterday ?>，仅女性）</h2>
    <div class="dash-table">
    <table>
        <thead>
            <tr>
                <th>头像</th>
                <th>昵称</th>
                <th>UID</th>
                <th>总收益</th>
                <th>认证</th>
                <th><?= $yesterday ?>收益</th>
                <th><?= $day2 ?>收益</th>
                <th><?= $day3 ?>收益</th>
                <th>日均收益<br>(活跃天数)</th>
                <th>
                    <?= sort_link('昨日/日均占比', 'ratio', $order_field, $order_dir) ?>
                </th>
                <th>收益评分</th>
                <th>详情</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $a): ?>
            <tr>
                <td>
                    <?php if ($a['avatar']): ?>
                        <img src="<?= htmlspecialchars($a['avatar']) ?>" class="avatar">
                    <?php else: ?>
                        <span style="color:#bbb;">无</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($a['nick']) ?></td>
                <td><?= $a['uid'] ?></td>
                <td style="color:#e92e90;"><?= number_format($a['total_reward'], 2) ?> 元</td>
                <td><?= $a['is_quality'] ? '✅ 已认证' : '❌ 未认证' ?></td>
                <td style="color:#009688;"><?= floatval($a['r1']) ?> 元</td>
                <td style="color:#09c;"><?= floatval($a['r2']) ?> 元</td>
                <td style="color:#09c;"><?= floatval($a['r3']) ?> 元</td>
                <td><?= floatval($a['avg_daily']) ?> 元
                    <span style="color:#888;">（<?= intval($a['active_days']) ?> 天）</span>
                </td>
                <td>
                    <?php
                        if ($a['avg_daily'] > 0) {
                            $ratio = $a['ratio'];
                            echo $ratio >= 100
                                ? "<span style='color:#fa2c50'>{$ratio}% ↑</span>"
                                : "<span style='color:#888'>{$ratio}% ↓</span>";
                        } else {
                            echo '-';
                        }
                    ?>
                </td>
                <td><?= str_repeat('⭐️', intval($a['earning_score'])) ?: '无'; ?></td>
                <td>
                    <a href="anchor.php?uid=<?= $a['uid'] ?>" target="_blank" style="color:#e92e90; text-decoration:underline;">详情</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="margin: 20px 0;">
        <?php if ($page > 1): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page-1])) ?>" style="padding: 8px 18px; background: #eee; margin-right:8px; text-decoration:none; border-radius:5px;">上一页</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['page'=>$page+1])) ?>" style="padding: 8px 18px; background: #eee; text-decoration:none; border-radius:5px;">下一页</a>
        <?php endif; ?>
        <span style="margin-left:10px;color:#888;">第 <?= $page ?> / <?= $totalPages ?> 页</span>
    </div>
</body>
</html>
