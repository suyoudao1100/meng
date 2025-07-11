<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

// 获取当前主播UID
$uid = $_GET['uid'] ?? 0;

// 处理备注和微信保存请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wechat = trim($_POST['wechat'] ?? '');
    $remark = trim($_POST['remark'] ?? '');
    $stmt = $db->prepare("UPDATE anchor_profile SET remark = ?, wechat = ? WHERE uid = ?");
    $stmt->execute([$remark, $wechat, $uid]);
    header("Location: anchor.php?uid={$uid}");
    exit;
}

// 查询主播基础信息
$stmt = $db->prepare("SELECT * FROM anchor_profile WHERE uid = ?");
$stmt->execute([$uid]);
$anchor = $stmt->fetch(PDO::FETCH_ASSOC);

// 查询主播近10日收益（倒序后正序输出）
$stat = $db->prepare("SELECT stat_date, reward FROM anchor_daily_earning WHERE uid = ? ORDER BY stat_date DESC LIMIT 10");
$stat->execute([$uid]);
$history = array_reverse($stat->fetchAll(PDO::FETCH_ASSOC));

// 颜值分级转文字
function level_label($level) {
    if ($level == 3) return '<span style="color:#fa2c50;font-weight:bold;">高</span>';
    if ($level == 2) return '<span style="color:#e6a23c;font-weight:bold;">中</span>';
    if ($level == 1) return '<span style="color:#aaa;font-weight:bold;">低</span>';
    return '-';
}

// 查询主播统计数据
$today = date('Y-m-d');
$three_days_ago = date('Y-m-d', strtotime('-2 day'));
$stat_info = $db->prepare("
    SELECT
        -- 近三日收益
        COALESCE(SUM(CASE WHEN stat_date >= ? THEN reward ELSE 0 END), 0) AS reward_3days,
        -- 近三日活跃
        MAX(CASE WHEN stat_date >= ? THEN 1 ELSE 0 END) AS is_active_3days,
        -- 活跃天数
        COUNT(DISTINCT stat_date) AS active_days,
        -- 历史日均收益
        CASE WHEN COUNT(DISTINCT stat_date) > 0 THEN ROUND(SUM(reward) / COUNT(DISTINCT stat_date), 2) ELSE 0 END AS avg_daily
    FROM anchor_daily_earning
    WHERE uid = ?
");
$stat_info->execute([$three_days_ago, $three_days_ago, $uid]);
$anchor_stat = $stat_info->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($anchor['nick']) ?> - 主播详情</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.jsdelivr.net/npm/echarts/dist/echarts.min.js"></script>
    <style>
        body { background:#f8f8f8; font-family:'微软雅黑',sans-serif; color:#222; margin:0; padding:0; }
        .container { max-width: 600px; margin: 32px auto 0; background:#fff; border-radius:16px; box-shadow:0 4px 24px #eee; padding:32px 32px 24px 32px; }
        h2 { margin-top: 0; color: #fa2c50;}
        .avatar { width: 100px; height:100px; border-radius:50%; margin-bottom: 10px; border:4px solid #fff; box-shadow: 0 2px 8px #eee;}
        .profile-list { list-style:none; padding:0; margin:0 0 18px 0;}
        .profile-list li { margin:10px 0; font-size:16px;}
        .tag { display:inline-block; padding:2px 8px; border-radius:8px; font-size:14px;}
        .tag.cert {background:#e8f6ea;color:#3bb46e;}
        .tag.uncert {background:#fbeaea;color:#d9534f;}
        .info-form {margin:18px 0 20px 0; padding:18px 20px; border-radius:8px; background:#f7f7fb;}
        label {color:#666;}
        input[type="text"], textarea {
            width:90%; padding:7px 10px; font-size:15px; border-radius:6px; border:1px solid #e6e6e6; margin-bottom: 10px;
        }
        button[type="submit"] {
            padding:8px 22px; background:#fa2c50; border:none; color:#fff; border-radius:6px; font-size:16px; font-weight:bold; cursor:pointer;
        }
        #chart {width:100%; height:350px;}
        .back-btn {display:inline-block; background:#eee; color:#666; padding:6px 14px; border-radius:7px; text-decoration:none; margin-bottom: 18px;}
        @media (max-width: 700px) { .container {padding:16px;} }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-btn">← 返回列表</a>
        <h2><?= htmlspecialchars($anchor['nick']) ?>（UID: <?= $anchor['uid'] ?>）</h2>
        <img class="avatar" src="<?= $anchor['avatar'] ?>" alt="主播头像">
        <ul class="profile-list">
            <li>累计收益：<b style="color:#fa2c50;"><?= $anchor['total_reward'] ?> 元</b></li>
            <li>注册日期：<?= $anchor['created_at'] ? date('Y-m-d', strtotime($anchor['created_at'])) : '-' ?></li>
            <li>微信：<?= $anchor['wechat'] ? htmlspecialchars($anchor['wechat']) : '<span style="color:#bbb;">未填写</span>' ?></li>
            <li>颜值评分：<?= level_label($anchor['level']) ?></li>
            <li>认证状态：
                <?= $anchor['is_quality']
                    ? '<span class="tag cert">✅ 已认证</span>'
                    : '<span class="tag uncert">❌ 未认证</span>' ?>
            </li>
            <li>备注：<?= $anchor['remark'] ? htmlspecialchars($anchor['remark']) : '<span style="color:#bbb;">未填写</span>' ?></li>
        </ul>
<li>近三日活跃：<?= $anchor_stat['is_active_3days'] ? '<span style="color:#52c41a;">活跃</span>' : '<span style="color:#aaa;">不活跃</span>' ?></li>
<li>近三日收益：<b><?= floatval($anchor_stat['reward_3days']) ?> 元</b></li>
<li>历史日均收益：<b><?= floatval($anchor_stat['avg_daily']) ?> 元</b>
    <span style="color:#888;">（<?= intval($anchor_stat['active_days']) ?> 天）</span>
</li>

        <!-- 编辑微信/备注表单 -->
        <form method="post" class="info-form">
            <label>微信号：</label><br>
            <input type="text" name="wechat" value="<?= htmlspecialchars($anchor['wechat'] ?? '') ?>"><br>
            <label>主播备注：</label><br>
            <textarea name="remark" rows="3"><?= htmlspecialchars($anchor['remark'] ?? '') ?></textarea><br>
            <button type="submit">保存信息</button>
        </form>

        <h3>📈 近10日收益趋势</h3>
        <div id="chart"></div>
    </div>

    <script>
        var chart = echarts.init(document.getElementById('chart'));
        chart.setOption({
            title: { text: '近10日收益趋势', left: 'center', textStyle: { fontSize: 18 } },
            tooltip: { trigger: 'axis' },
            grid: { left: '3%', right: '3%', bottom: '6%', containLabel: true },
            xAxis: {
                type: 'category',
                data: <?= json_encode(array_column($history, 'stat_date')) ?>,
                axisLabel: { fontSize: 13 }
            },
            yAxis: { type: 'value', minInterval: 1, name: '收益(元)' },
            series: [{
                name: '收益',
                type: 'line',
                smooth: true,
                symbolSize: 8,
                lineStyle: { width: 3 },
                data: <?= json_encode(array_map('floatval', array_column($history, 'reward'))) ?>
            }]
        });
        window.addEventListener('resize', function() { chart.resize(); });
    </script>
</body>
</html>
