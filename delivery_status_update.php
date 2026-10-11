<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$delivery_id = intval($_POST['delivery_id'] ?? 0);
$status      = trim($_POST['status'] ?? '');
$allowed     = ['pending','delivering','completed','failed','cancelled'];

if (!$delivery_id || !in_array($status, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'参数错误'], JSON_UNESCAPED_UNICODE); exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare("UPDATE deliveries SET status=?, updated_at=NOW() WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("si", $status, $delivery_id);
$stmt->execute();

$labels = ['pending'=>'待送货','delivering'=>'配送中','completed'=>'已完成','failed'=>'配送失败','cancelled'=>'已取消'];
writeLog($conn, 'deliveries', 'status', '更新送货状态');
echo json_encode(['success'=>true,'message'=>'状态已更新为「'.$labels[$status].'」'], JSON_UNESCAPED_UNICODE);
$conn->close();
?>
