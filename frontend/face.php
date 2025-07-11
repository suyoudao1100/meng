<?php
// require_once 'db.php';
require_once __DIR__ . '/../backend/db.php';

$db = getDbConnection();

// Face++ API配置
$api_key = '0WQoIf6GFi_jfa2sSykq_vvAfogPwT0d';
$api_secret = 'qoipAGjBwZYZaWrKUutPm4jbFnzWQlcU';
$url = 'https://api-cn.faceplusplus.com/facepp/v3/detect';

// 获取主播头像
$stmt = $db->query("SELECT uid, avatar FROM anchor_profile WHERE avatar != ''");
$anchors = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($anchors as $anchor) {
    $imageUrl = $anchor['avatar'];

    $postFields = [
        'api_key' => $api_key,
        'api_secret' => $api_secret,
        'image_url' => $imageUrl,
        'return_attributes' => 'beauty'
    ];

    // 请求 Face++ 接口
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $postFields
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['faces'][0]['attributes']['beauty'])) {
        $beauty = $result['faces'][0]['attributes']['beauty']['female_score']; //假设为女性主播

        // 根据分数定义等级
        $level = ($beauty >= 75) ? 3 : (($beauty >= 60) ? 2 : 1);

        // 更新数据库
        $updateStmt = $db->prepare("UPDATE anchor_profile SET level = ? WHERE uid = ?");
        $updateStmt->execute([$level, $anchor['uid']]);

        echo "主播UID: {$anchor['uid']}，颜值评分: {$beauty}，等级设为: {$level}\n";
    } else {
        echo "主播UID: {$anchor['uid']} 识别失败。\n";
    }
}
?>
