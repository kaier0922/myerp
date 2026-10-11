<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
header('Content-Type: application/json; charset=utf-8');
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false,'message'=>'未登录']); exit; }

$uid    = intval($_SESSION['user_id']);
$pid    = intval($_POST['id']    ?? 0);
$qty    = intval($_POST['qty']   ?? -1);
$type   = $_POST['type']   ?? 'set';
$change = intval($_POST['change'] ?? 0);
$remark = trim($_POST['remark'] ?? '盘点调整');

if ($pid <= 0 || $qty < 0) {
    echo json_encode(['success'=>false,'message'=>'参数错误']); exit;
}

$conn = getDBConnection();
$conn->begin_transaction();
try {
    // 查当前库存
    $s = $conn->prepare("SELECT stock_quantity, name FROM products WHERE id=? AND (user_id=0 OR user_id=?)");
    $s->bind_param('ii', $pid, $uid);
    $s->execute();
    $p = $s->get_result()->fetch_assoc();
    $s->close();
    if (!$p) throw new Exception('产品不存在');

    $old_qty = intval($p['stock_quantity']);

    // 更新库存
    $u = $conn->prepare("UPDATE products SET stock_quantity=? WHERE id=? AND (user_id=0 OR user_id=?)");
    $u->bind_param('iii', $qty, $pid, $uid);
    $u->execute();
    $u->close();

    // 写系统日志
    $diff = $qty - $old_qty;
    $diff_str = $diff >= 0 ? "+{$diff}" : "{$diff}";
    $log_msg = "库存调整：{$p['name']} {$old_qty}→{$qty}（{$diff_str}），原因：{$remark}";
    $op = $_SESSION['nickname'] ?? '管理员';
    $l = $conn->prepare("INSERT INTO system_logs (user_id, module, action, detail, created_at) VALUES (?,?,?,?,NOW())");
    $module = 'product';
    $action = 'stock_adjust';
    $l->bind_param('isss', $uid, $module, $action, $log_msg);
    $l->execute();
    $l->close();

    $conn->commit();
    echo json_encode(['success'=>true,'message'=>'库存已更新','old'=>$old_qty,'new'=>$qty]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
$conn->close();
