<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

$all = [];
$stmt = $db->query("SELECT uid FROM anchor_profile");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $uid = $row['uid'];
    // 日均收益
    $sql = "SELECT 
                CASE WHEN COUNT(DISTINCT stat_date)>0 
                     THEN SUM(reward)/COUNT(DISTINCT stat_date)
                     ELSE 0 END as avg_daily 
            FROM anchor_daily_earning WHERE uid=?";
    $s2 = $db->prepare($sql); $s2->execute([$uid]);
    $avg_daily = round($s2->fetchColumn(), 2);
    $all[] = $avg_daily;
}

// 统计
sort($all);
$total = count($all);
$max = max($all);
$min = min($all);
$avg = round(array_sum($all)/$total, 2);
$p50 = $all[intval($total * 0.5)];
$p75 = $all[intval($total * 0.75)];
$p90 = $all[intval($total * 0.9)];
$p95 = $all[intval($total * 0.95)];

// 输出区间段分布（举例：<=10, 11-30, 31-50, 51-100, >100）
$sections = [10, 30, 50, 100, 99999];
$sectionCount = array_fill(0, count($sections), 0);

foreach ($all as $v) {
    foreach ($sections as $idx=>$sec) {
        if ($v <= $sec) {
            $sectionCount[$idx]++;
            break;
        }
    }
}

echo "主播总数：$total\n";
echo "日均收益\n";
echo "  最小值：$min\n";
echo "  最大值：$max\n";
echo "  均值：$avg\n";
echo "  50分位(P50)：$p50\n";
echo "  75分位(P75)：$p75\n";
echo "  90分位(P90)：$p90\n";
echo "  95分位(P95)：$p95\n";
echo "分布区间：\n";
$last = 0;
foreach ($sections as $i=>$sec) {
    if ($i == 0)
        echo "  0-{$sec} 元：{$sectionCount[$i]} 人\n";
    else
        echo "  " . ($sections[$i-1]+1) . "-{$sec} 元：{$sectionCount[$i]} 人\n";
    $last = $sec;
}

?>
