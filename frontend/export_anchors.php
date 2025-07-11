<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

// 复制 index.php 中参数处理、查询部分即可（建议抽函数共用）
// 这里只做简化，导出所有符合条件的数据，不分页
$platform = $_GET['platform'] ?? '梦宝宝';
$keyword = $_GET['remark_keyword'] ?? '';
$wechat_filter = $_GET['wechat_filter'] ?? 'all';
$active3_filter = $_GET['active3_filter'] ?? 'all';
$order_field = $_GET['order_field'] ?? 'total_reward';
$order_type = $_GET['order_type'] ?? 'desc';

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

$stmt = $db->prepare("
SELECT
    ap.uid, ap.nick, ap.avatar, ap.total_reward, ap.is_quality, ap.wechat, ap.remark, ap.level, ap.earning_score, ap.created_at,
    COALESCE(SUM(CASE WHEN ade.stat_date >= ? THEN ade.reward ELSE 0 END), 0) AS reward_3days,
    MAX(CASE WHEN ade.stat_date >= ? THEN 1 ELSE 0 END) AS is_active_3days,
    COUNT(DISTINCT ade.stat_date) AS active_days,
    CASE WHEN COUNT(DISTINCT ade.stat_date) > 0 THEN ROUND(SUM(ade.reward) / COUNT(DISTINCT ade.stat_date), 2) ELSE 0 END AS avg_daily
FROM anchor_profile ap
LEFT JOIN anchor_daily_earning ade ON ap.uid = ade.uid AND ade.platform = ap.platform
$where
GROUP BY ap.uid
ORDER BY $order_sql $order_type_sql
");
$stmt->execute([$three_days_ago, $three_days_ago, ...$paramArr]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 输出CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=anchors_'.date('Ymd_His').'.csv');
$output = fopen('php://output', 'w');
fputcsv($output, array('UID','昵称','头像','总收益','认证','微信','备注','颜值评分','收益评分','注册日期','近三日收益','近三日活跃','日均收益','活跃天数'));
foreach ($data as $a) {
    fputcsv($output, [
        $a['uid'],
        $a['nick'],
        $a['avatar'],
        $a['total_reward']*2,
        $a['is_quality'] ? '已认证' : '未认证',
        $a['wechat'],
        $a['remark'],
        $a['level'],
        $a['earning_score'],
        substr($a['created_at'],0,10),
        $a['reward_3days'],
        $a['is_active_3days'] ? '活跃' : '不活跃',
        $a['avg_daily'],
        $a['active_days']
    ]);
}
fclose($output);
exit;
?>
