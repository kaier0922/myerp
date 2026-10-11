<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => '非法访问']);
    exit;
}

$conn = getDBConnection();
$id = intval($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => '无效的合同ID']);
    exit;
}

$conn->begin_transaction();

try {
    // 删除明细
    $stmt1 = $conn->prepare("DELETE FROM sales_contract_items WHERE contract_id = ?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    
    // 删除主表
    $stmt2 = $conn->prepare("DELETE FROM sales_contracts WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    
    if ($stmt2->affected_rows > 0) {
        $conn->commit();
        writeLog($conn, 'contracts', 'delete', '删除合同');
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('合同不存在或已被删除');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
