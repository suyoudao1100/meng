<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

// 获取全部主播UID
$stmt = $db->query("SELECT uid FROM anchor_profile");
$count = 0;
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $uid = $row['uid'];

    // 计算主播日均收益
    $sql = "SELECT CASE WHEN COUNT(DISTINCT stat_date)>0
                 THEN SUM(reward)/COUNT(DISTINCT stat_date)
                 ELSE 0 END as avg_daily
            FROM anchor_daily_earning WHERE uid=?";
    $s2 = $db->prepare($sql); $s2->execute([$uid]);
    $avg_daily = round($s2->fetchColumn(), 2);

    // 根据区间打分
    if ($avg_daily >= 100) $score = 5;
    elseif ($avg_daily >= 50) $score = 4;
    elseif ($avg_daily >= 30) $score = 3;
    elseif ($avg_daily >= 10) $score = 2;
    elseif ($avg_daily > 0) $score = 1;
    else $score = 0;

    // 更新入表
    $db->prepare("UPDATE anchor_profile SET earning_score=? WHERE uid=?")
       ->execute([$score, $uid]);
    $count++;
    echo "主播 $uid 日均 $avg_daily 元，评分 $score\n";
}
echo "共更新 $count 位主播评分。\n";
