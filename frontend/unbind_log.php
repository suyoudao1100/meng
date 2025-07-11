<?php
require_once __DIR__ . '/../backend/db.php';
$db = getDbConnection();

$event = $_GET['event'] ?? '';
$order = $_GET['order'] ?? '';
$where = '';
$param = [];
$orderSql = "aul.occur_date DESC, aul.id DESC";
if ($order === 'total_reward_asc') $orderSql = "ap.total_reward ASC, aul.occur_date DESC";
if ($order === 'total_reward_desc') $orderSql = "ap.total_reward DESC, aul.occur_date DESC";

if ($event == 'unbind' || $event == 'new') {
    $where = "WHERE aul.event = ?";
    $param[] = $event;
}

// 处理微信、备注、跟进状态批量保存
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['uids'])) {
    foreach ($_POST['uids'] as $uid) {
        $wechat = trim($_POST['wechat'][$uid] ?? '');
        $remark = trim($_POST['remark'][$uid] ?? '');
        $follow_status = trim($_POST['follow_status'][$uid] ?? '未跟进');
        $stmt = $db->prepare("UPDATE anchor_profile SET wechat = ?, remark = ?, follow_status = ? WHERE uid = ?");
        $stmt->execute([$wechat, $remark, $follow_status, $uid]);
    }
    header("Location: unbind_log.php?" . http_build_query($_GET));
    exit;
}

// 主查询
$stmt = $db->prepare("
    SELECT 
        aul.*, 
        ap.avatar, ap.nick as profile_nick, ap.level, ap.wechat, ap.remark,
        ap.last_active_time, ap.total_reward, ap.earning_score, ap.follow_status
    FROM anchor_unbind_log aul
    LEFT JOIN anchor_profile ap ON aul.uid = ap.uid
    $where
    ORDER BY $orderSql
    LIMIT 200
");
$stmt->execute($param);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getFollowStatusList() {
    return ['未跟进', '跟进中', '跟进完毕'];
}

function showEventType($row) {
    if (strpos($row['nick'], '已注销') !== false || strpos($row['profile_nick'], '已注销') !== false) {
        return '<span style="color: #aaa;">已注销</span>';
    }
    if ($row['event'] == 'unbind') {
        return '<span style="color: #b7b7b7;">解绑/流失</span>';
    }
    if ($row['event'] == 'new') {
        return '<span style="color: #fa2c50;">新增</span>';
    }
    return '-';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>主播解绑/新增记录</title>
    <style>
        body { font-family: 微软雅黑, sans-serif; background: #f6f8fb; padding: 38px;}
        h2 { color: #fa2c50; }
        .nav { margin-bottom: 18px;}
        .nav a { padding: 7px 18px; background: #ffeff8; border-radius: 6px; color: #fa2c50; text-decoration: none; margin-right:8px;}
        .nav a.active { background: #fa2c50; color: #fff; }
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 10px; overflow: hidden;}
        th,td { padding: 10px 6px; border-bottom: 1px solid #eee; text-align: center;}
        th { background: #f9f3fb; }
        .unbind {color: #b7b7b7;}
        .new {color: #fa2c50;}
        img { border-radius: 50%; width: 40px; }
        a.sort-arrow { text-decoration: none; color: #fa2c50; margin-left: 3px;}
        select, input[type="text"] { padding: 4px; border: 1px solid #ccc; border-radius: 3px; width: 86%; }
        .btn-save { padding: 7px 16px; background: #fa2c50; color: #fff; border: none; border-radius: 4px; font-size: 15px; cursor: pointer;}
    </style>
</head>
<body>
    <h2>🔎 主播解绑/新增记录</h2>
    <div class="nav">
        <a href="unbind_log.php" class="<?= $event==''?'active':'' ?>">全部</a>
        <a href="unbind_log.php?event=unbind" class="<?= $event=='unbind'?'active':'' ?>">仅解绑/流失</a>
        <a href="unbind_log.php?event=new" class="<?= $event=='new'?'active':'' ?>">仅新增</a>
        <a href="index.php" style="float:right;background:#aaa;color:#fff;">返回首页</a>
    </div>
    <form method="post">
    <table>
        <tr>
            <th>日期</th>
            <th>头像</th>
            <th>昵称</th>
            <th>UID</th>
            <th>注册日期</th>
            <th>
              <a href="?<?= http_build_query(array_merge($_GET, ['order' => ($order === 'total_reward_desc' ? 'total_reward_asc' : 'total_reward_desc')])) ?>" style="color:#fa2c50;text-decoration:none;">
                总收益
                <?php if ($order === 'total_reward_desc'): ?>
                  ▼
                <?php elseif ($order === 'total_reward_asc'): ?>
                  ▲
                <?php endif; ?>
              </a>
            </th>
            <th>收益评分</th>
            <th>微信联系</th>
            <th>备注</th>
            <th>跟进状态</th>
            <th>事件类型</th>
            <th>操作</th>
        </tr>
        <?php foreach ($logs as $row): ?>
        <tr>
            <td><?= $row['occur_date'] ?></td>
            <td>
                <?php if ($row['avatar']): ?>
                    <img src="<?= htmlspecialchars($row['avatar']) ?>">
                <?php else: ?>
                    -
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($row['profile_nick'] ?: $row['nick']) ?></td>
            <td><?= $row['uid'] ?><input type="hidden" name="uids[]" value="<?= $row['uid'] ?>"></td>
            <td><?= $row['last_active_time'] ?: '-' ?></td>
            <td><?= $row['total_reward'] !== null ? ($row['total_reward']*2) . ' 元' : '-' ?></td>
            <td>
              <?php
                $score = intval($row['earning_score']);
                echo $score ? str_repeat('⭐️', $score) : '无';
              ?>
            </td>
            <td>
                <input type="text" name="wechat[<?= $row['uid'] ?>]" value="<?= htmlspecialchars($row['wechat']) ?>">
            </td>
            <td>
                <input type="text" name="remark[<?= $row['uid'] ?>]" value="<?= htmlspecialchars($row['remark']) ?>">
            </td>
            <td>
                <select name="follow_status[<?= $row['uid'] ?>]">
                  <?php foreach(getFollowStatusList() as $s): ?>
                    <option value="<?= $s ?>" <?= ($row['follow_status']??'未跟进')==$s ? 'selected' : '' ?>><?= $s ?></option>
                  <?php endforeach; ?>
                </select>
            </td>
            <td><?= showEventType($row) ?></td>
            <td>
              <a href="anchor.php?uid=<?= $row['uid'] ?>" style="color:#44aaf5;">详情</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div style="margin:20px 0;">
      <button type="submit" class="btn-save">保存修改</button>
    </div>
    </form>
</body>
</html>
