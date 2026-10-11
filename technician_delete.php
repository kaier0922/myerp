<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'error'=>'未登录']);
    exit;
}

$id   = intval($_POST['id'] ?? 0);
$conn = getDBConnection();

$stmt = $conn->prepare("DELETE FROM technicians WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("i", $id);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    writeLog($conn, 'technicians', 'delete', '删除技师');
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'删除失败或记录不存在']);
}
$conn->close();
