<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

// 分页与参数
$page = max(1, intval($_GET['page'] ?? 1));
$limit = intval($_GET['limit'] ?? 20);
$offset = ($page - 1) * $limit;

$platform = $_GET['platform'] ?? '梦宝宝';
$keyword = $_GET['remark_keyword'] ?? '';
$wechat_filter = $_GET['wechat_filter'] ?? 'all';
$level_sort = $_GET['level_sort'] ?? '';
$active3_filter = $_GET['active3_filter'] ?? 'all';
$order_field = $_GET['order_field'] ?? 'total_reward'; // 默认排序
$order_type = $_GET['order_type'] ?? 'desc';

// 批量保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uids'])) {
    foreach ($_POST['uids'] as $uid) {
        $wechat = trim($_POST['wechat'][$uid] ?? '');
        $remark = trim($_POST['remark'][$uid] ?? '');
        $stmt = $db->prepare("UPDATE anchor_profile SET wechat = ?, remark = ? WHERE uid = ?");
        $stmt->execute([$wechat, $remark, $uid]);
    }
    header("Location: index.php?" . http_build_query($_GET));
    exit;
}

// 构建where条件
$paramArr = [$platform];
$where = "WHERE ap.platform = ?";
if ($keyword) {
    $where .= " AND (ap.remark LIKE ? OR ap.nick LIKE ? OR ap.uid LIKE ?)";
    $paramArr[] = "%$keyword%";
    $paramArr[] = "%$keyword%";
    $paramArr[] = "%$keyword%";
}

if ($wechat_filter === 'has_wechat') {
    $where .= " AND ap.wechat IS NOT NULL AND ap.wechat != ''";
} elseif ($wechat_filter !== 'all' && $wechat_filter !== '') {
    $where .= " AND ap.wechat = ?";
    $paramArr[] = $wechat_filter;
}

$three_days_ago = date('Y-m-d', strtotime('-2 day'));
if ($active3_filter === 'active3') {
    $where .= " AND EXISTS (SELECT 1 FROM anchor_daily_earning ade2 WHERE ade2.uid = ap.uid AND ade2.platform = ap.platform AND ade2.stat_date >= ?)";
    $paramArr[] = $three_days_ago;
}
if ($active3_filter === 'inactive3') {
    $where .= " AND NOT EXISTS (SELECT 1 FROM anchor_daily_earning ade2 WHERE ade2.uid = ap.uid AND ade2.platform = ap.platform AND ade2.stat_date >= ?)";
    $paramArr[] = $three_days_ago;
}

// 支持排序字段
$allowed_fields = [
    'total_reward' => 'ap.total_reward',
    'reward_3days' => 'reward_3days',
    'avg_daily' => 'avg_daily',
    'active_days' => 'active_days',
    'level' => 'ap.level',
    'earning_score' => 'ap.earning_score',
    'created_at' => 'ap.created_at',
    'nick' => 'ap.nick',
    'uid' => 'ap.uid',
];
$order_sql = $allowed_fields[$order_field] ?? 'ap.total_reward';
$order_type_sql = ($order_type == 'asc') ? 'ASC' : 'DESC';

// 统计总数
$countStmt = $db->prepare("SELECT COUNT(*) FROM anchor_profile ap $where");
$countStmt->execute($paramArr);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// 主数据
$stmt = $db->prepare("
SELECT
    ap.uid,
    ap.nick,
    ap.avatar,
    ap.total_reward,
    ap.is_quality,
    ap.wechat,
    ap.remark,
    ap.level,
    ap.earning_score,
    ap.created_at,
    COALESCE(SUM(CASE WHEN ade.stat_date >= ? THEN ade.reward ELSE 0 END), 0) AS reward_3days,
    MAX(CASE WHEN ade.stat_date >= ? THEN 1 ELSE 0 END) AS is_active_3days,
    COUNT(DISTINCT ade.stat_date) AS active_days,
    CASE WHEN COUNT(DISTINCT ade.stat_date) > 0 THEN ROUND(SUM(ade.reward) / COUNT(DISTINCT ade.stat_date), 2) ELSE 0 END AS avg_daily
FROM anchor_profile ap
LEFT JOIN anchor_daily_earning ade ON ap.uid = ade.uid AND ade.platform = ap.platform
$where
GROUP BY ap.uid
ORDER BY $order_sql $order_type_sql
LIMIT $limit OFFSET $offset
");
$stmt->execute([$three_days_ago, $three_days_ago, ...$paramArr]);
$anchors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 工具函数：生成排序链接
function sort_link($name, $field, $order_field, $order_type) {
    $next_type = ($order_field == $field && $order_type == 'desc') ? 'asc' : 'desc';
    $arrow = '';
    if ($order_field == $field) {
        $arrow = $order_type == 'asc'
            ? ' <span class="sort-arrow active">▲</span>'
            : ' <span class="sort-arrow active">▼</span>';
    } else {
        $arrow = ' <span class="sort-arrow">↕</span>';
    }
    $query = $_GET;
    $query['order_field'] = $field;
    $query['order_type'] = $next_type;
    return "<a href='?" . http_build_query($query) . "'>{$name}{$arrow}</a>";
}

// 平台配置
$platforms = [
    '梦宝宝' => '#ff69b4',
    '暖遇' => '#36b0ff',
    '圈爱' => '#ffab2e',
];
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<title><?= $platform ?> 主播数据系统</title>
<style>
    body {
        font-family: "微软雅黑", "PingFang SC", "Arial", sans-serif;
        background: #f7f6fa;
        padding: 0 0 30px 0;
        margin: 0;
    }
    .container { max-width: 1680px; margin: 0 auto; }
    h1 {
        font-size: 32px; color: #fa2c50;
        margin: 0; padding: 36px 0 16px 0;
        font-weight: bold; letter-spacing: 2px;
        display: flex; align-items: center;
    }
    .h1-icon { font-size: 36px; margin-right: 10px;}
    .top-btns { margin-bottom: 18px;}
    .btn, button[type=submit] {
        display: inline-block; background: #ff69b4;
        color: #fff; font-size: 16px; padding: 12px 30px;
        border: none; border-radius: 999px; cursor: pointer;
        margin-right: 16px; margin-bottom: 6px; font-weight: bold;
        box-shadow: 0 2px 8px #ffd2eb52;
        transition: all .18s;
    }
    .btn:hover, button[type=submit]:hover { background: #fa2c50; }
    .platform-bar {
        margin-bottom: 20px; font-size: 0;
    }
    .platform-bar .plat {
        display: inline-block;
        background: #f8e9f3;
        color: #ff69b4;
        font-size: 18px;
        border-radius: 999px;
        padding: 7px 26px;
        margin-right: 15px;
        margin-bottom: 8px;
        font-weight: 500;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all .13s;
        text-decoration: none;
    }
    .platform-bar .plat.active {
        background: #ff69b4;
        color: #fff;
        border: 2px solid #fa2c50;
        box-shadow: 0 2px 10px #ffd4e6;
    }
    .filters {
        display: flex; flex-wrap: wrap;
        gap: 16px; margin: 0 0 20px 0;
        align-items: center;
        padding: 0 4px;
    }
    .filters input[type=text], .filters select {
        border: 1px solid #fa2c50;
        border-radius: 999px;
        padding: 8px 18px;
        font-size: 16px;
        outline: none;
        margin-right: 10px;
        background: #fff;
        transition: border-color .18s;
    }
    .filters button { padding: 10px 30px;}
    table {
        width: 100%; background: #fff;
        border-radius: 18px; box-shadow: 0 4px 18px #fa2c505d;
        border-collapse: separate; border-spacing: 0;
        overflow: hidden;
    }
    th, td {
        padding: 15px 8px; text-align: center;
        font-size: 16px;
    }
    thead th {
        background: #fff4fa;
        color: #fa2c50; font-weight: bold;
        border-bottom: 2px solid #fa2c5099;
        position: sticky; top: 0; z-index: 3;
    }
    tr { transition: background .12s;}
    tbody tr:hover td { background: #fff0f8;}
    td img { border-radius: 50%; width: 50px; }
    input[type=text] { width: 90%; padding: 4px;}
    .sort-arrow { font-size: 13px; color: #bbb;}
    .sort-arrow.active { color: #fa2c50;}
    .active-status { color: #19b262; font-weight: bold;}
    .inactive-status { color: #bbb; font-weight: bold;}
    .score-star { color: #fdcc46; font-size: 19px;}
    .score-none { color: #ccc; font-size: 15px;}
    .tag-h { color: #fa2c50;}
    .tag-m { color: #fd8906;}
    .tag-l { color: #aaa;}
    .table-btn { padding: 8px 12px; border-radius: 9px; background: #ffd3e6; color: #fa2c50; border: none; font-weight: bold;}
    /* 分页 */
    .pagination { margin: 22px 0 0 0; font-size: 17px;}
    .pagination a.btn { padding: 8px 20px; font-size: 16px; }
    @media (max-width: 950px) {
        .container {max-width:98vw;}
        table, th, td { font-size: 14px;}
        td img { width: 34px;}
        .btn, .pagination a.btn, button[type=submit] { font-size: 14px; padding: 8px 16px;}
    }
</style>
</head>
<body>
<div class="container">
    <!-- 顶部标题区 -->
    <h1><span class="h1-icon">🎀</span><?= $platform ?> 主播数据系统</h1>

    <!-- 顶部大屏入口和功能区 -->
    <div class="top-btns">
        <a class="btn" href="dashboard.php?platform=<?= urlencode($platform) ?>" target="_blank">昨日数据概览</a>
        <a class="btn" href="unbind_log.php" >主播解绑/新增日志</a>

        <a class="btn" href="rank.php?platform=<?= urlencode($platform) ?>">今日排行</a>
        <a class="btn" href="growth.php?platform=<?= urlencode($platform) ?>">收益增长榜</a>
        <a class="btn" href="low.php?platform=<?= urlencode($platform) ?>">低效主播榜</a>
    </div>
<!-- 导出按钮区，放在表单上方/右上 -->
<form method="get" action="export_anchors.php" style="display:inline;">
    <?php foreach ($_GET as $k=>$v): if($k=='page')continue; ?>
        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
    <?php endforeach ?>
    <button type="submit" class="btn" style="float:right;background:#19b262;">导出为Excel/CSV</button>
</form>

    <!-- 平台切换区 -->
    <div class="platform-bar">
        <?php foreach ($platforms as $plat => $color): ?>
            <a class="plat<?= $plat == $platform ? ' active' : '' ?>"
               style="<?= $plat == $platform ? "background: $color; color: #fff; border-color: $color;" : "" ?>"
               href="?platform=<?= urlencode($plat) ?>"><?= $plat ?></a>
        <?php endforeach ?>
    </div>

    <!-- 筛选区 -->
    <form class="filters" method="get">
        <input type="hidden" name="platform" value="<?= htmlspecialchars($platform) ?>">
        <input type="text" name="remark_keyword" placeholder="输入备注/昵称/UID" value="<?= htmlspecialchars($keyword) ?>">

        <select name="wechat_filter">
    <option value="all" <?= $wechat_filter == 'all' ? 'selected' : '' ?>>全部主播</option>
    <option value="李四" <?= $wechat_filter == '李四' ? 'selected' : '' ?>>李四</option>
    <option value="小明-男" <?= $wechat_filter == '小明-男' ? 'selected' : '' ?>>小明-男</option>
    <option value="小明-女" <?= $wechat_filter == '小明-女' ? 'selected' : '' ?>>小明-女</option>
    <option value="茶茶" <?= $wechat_filter == '茶茶' ? 'selected' : '' ?>>茶茶</option>
    <option value="小李" <?= $wechat_filter == '小李' ? 'selected' : '' ?>>小李</option>
    <option value="李李" <?= $wechat_filter == '李李' ? 'selected' : '' ?>>李李</option>
</select>

        <!--<select name="level_sort">-->
        <!--    <option value="">颜值默认排序</option>-->
        <!--    <option value="asc" <?= $level_sort == 'asc' ? 'selected' : '' ?>>颜值升序</option>-->
        <!--    <option value="desc" <?= $level_sort == 'desc' ? 'selected' : '' ?>>颜值降序</option>-->
        <!--</select>-->
        <select name="active3_filter">
            <option value="all" <?= $active3_filter == 'all' ? 'selected' : '' ?>>全部主播</option>
            <option value="active3" <?= $active3_filter == 'active3' ? 'selected' : '' ?>>仅近三日活跃</option>
            <option value="inactive3" <?= $active3_filter == 'inactive3' ? 'selected' : '' ?>>仅近三日不活跃</option>
        </select>
        <button type="submit" class="btn">筛选</button>
    </form>

    <!-- 主表格 -->
    <form method="post">
        <table>
            <thead>
                <tr>
                    <th>头像</th>
                    <th><?= sort_link('昵称', 'nick', $order_field, $order_type) ?></th>
                    <th><?= sort_link('UID', 'uid', $order_field, $order_type) ?></th>
                    <th><?= sort_link('注册日期', 'created_at', $order_field, $order_type) ?></th>
                    <th><?= sort_link('总收益', 'total_reward', $order_field, $order_type) ?></th>
                    <th>认证</th>
                    <th><?= sort_link('近三日活跃', 'is_active_3days', $order_field, $order_type) ?></th>
                    <th><?= sort_link('近三日收益', 'reward_3days', $order_field, $order_type) ?></th>
                    <th><?= sort_link('日均收益(活跃天数)', 'avg_daily', $order_field, $order_type) ?></th>
                    <th><?= sort_link('收益评分', 'earning_score', $order_field, $order_type) ?></th>
                    <th>微信联系</th>
                    <th>备注</th>
                    <th><?= sort_link('颜值评分', 'level', $order_field, $order_type) ?></th>
                    <th>详情</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anchors as $a): ?>
                <tr>
                    <td><img src="<?= $a['avatar'] ?>"></td>
                    <td><?= htmlspecialchars($a['nick']) ?></td>
                    <td><?= $a['uid'] ?><input type="hidden" name="uids[]" value="<?= $a['uid'] ?>"></td>
                    <td><?= htmlspecialchars(substr($a['created_at'], 0, 10)) ?></td>
                    <td style="color:#fa2c50;font-weight:bold;"><?= $a['total_reward'] ?> 元</td>
                    <td><?= $a['is_quality'] ? '<span style="color:#19b262;">✅已认证</span>' : '<span style="color:#bbb;">❌未认证</span>' ?></td>
                    <td>
                        <?= $a['is_active_3days'] ? '<span class="active-status">活跃</span>' : '<span class="inactive-status">不活跃</span>' ?>
                    </td>
                    <td><?= floatval($a['reward_3days']) ?> 元</td>
                    <td>
                        <?= floatval($a['avg_daily']) ?> 元
                        <span style="color:#888;">（<?= intval($a['active_days']) ?> 天）</span>
                    </td>
                    <td>
                        <?php
                            if ($a['earning_score'] > 0)
                                echo str_repeat('<span class="score-star">★</span>', intval($a['earning_score']));
                            else
                                echo '<span class="score-none">无</span>';
                        ?>
                    </td>
                    <td>
                        <input type="text" name="wechat[<?= $a['uid'] ?>]" value="<?= htmlspecialchars($a['wechat']) ?>">
                    </td>
                    <td>
                        <input type="text" name="remark[<?= $a['uid'] ?>]" value="<?= htmlspecialchars($a['remark']) ?>">
                    </td>
                    <td>
                        <?php
                            if ($a['level'] == 1) echo '<span class="tag-l">低</span>';
                            elseif ($a['level'] == 2) echo '<span class="tag-m">中</span>';
                            elseif ($a['level'] == 3) echo '<span class="tag-h">高</span>';
                            else echo '-';
                        ?>
                    </td>
                    <td><a class="table-btn" href="anchor.php?uid=<?= $a['uid'] ?>&platform=<?= urlencode($platform) ?>">详情</a></td>
                </tr>
                <?php endforeach ?>
            </tbody>
        </table>
<!-- 表格底部保存按钮 -->
<div style="margin: 25px 0 0 0; text-align:center;">
    <button type="submit" class="btn" style="background: #fa2c50; width: 260px; font-size:18px;">💾 保存修改</button>
</div>
</form>

<!-- 分页&条数选择合并一行，外部独立form，参数自动继承，UI美观 -->
<div class="pagination" style="display:flex;justify-content:center;align-items:center;gap:30px;margin:30px 0 10px 0;flex-wrap:wrap;">
    <form method="get" style="margin:0;display:flex;align-items:center;">
        <?php
        // 保留所有GET参数，除了page和limit（后面重写）
        foreach ($_GET as $k => $v) {
            if ($k !== 'limit' && $k !== 'page') {
                echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">';
            }
        }
        ?>
        <label style="font-weight:normal;color:#888;">每页</label>
        <select name="limit" onchange="this.form.submit()" style="border-radius:9px;padding:6px 13px;margin:0 5px;">
            <?php foreach ([10, 20, 50, 100] as $opt): ?>
                <option value="<?= $opt ?>" <?= $limit == $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach ?>
        </select>
        条
    </form>

    <!-- 上一页按钮 -->
    <?php if ($page > 1): ?>
        <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $page-1, 'limit'=>$limit])) ?>">上一页</a>
    <?php endif; ?>

    <!-- 页码显示 -->
    <span style="color:#888;">第 <?= $page ?> / <?= $totalPages ?> 页</span>

    <!-- 下一页按钮 -->
    <?php if ($page < $totalPages): ?>
        <a class="btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $page+1, 'limit'=>$limit])) ?>">下一页</a>
    <?php endif; ?>

    <span style="margin-left:10px;color:#aaa;">共 <?= $totalRows ?> 条</span>
</div>

