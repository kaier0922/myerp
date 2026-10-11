<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_super_admin();

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$role = $_SESSION['role'];
if (false) {
    echo json_encode(['success' => false, 'message' => '无权限']);
    exit;
}

$log_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($log_id === 0) {
    echo json_encode(['success' => false, 'message' => '无效的ID']);
    exit;
}

$conn = getDBConnection();

$sql = "SELECT * FROM login_logs WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $log_id);
$stmt->execute();
$result = $stmt->get_result();

if ($log = $result->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'log' => $log
    ]);
} else {
    echo json_encode(['success' => false, 'message' => '记录不存在']);
}

$stmt->close();
$conn->close();
?>
