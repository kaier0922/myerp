<?php
/**
 * ============================================================================
 * 文件名: delivery_delete.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 送货单删除处理
 * 
 * 功能说明：
 * 1. 删除送货单记录（硬删除）
 * 2. 级联删除送货明细
 * 3. 级联删除日志记录
 * 4. 级联删除收款记录
 * 
 * POST/GET 参数：
 * - id: 送货单ID（必需）
 * 
 * 返回 JSON：
 * {
 *   "success": true/false,
 *   "message": "提示信息"
 * }
 * ============================================================================
 */

// ==================== 初始化 ====================
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ==================== 权限验证 ====================
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'success' => false,
        'message' => '请先登录'
    ], JSON_UNESCAPED_UNICODE));
}

$user_id = $_SESSION['user_id'];

// ==================== 获取参数 ====================
$delivery_id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($delivery_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '无效的送货单ID'
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 连接数据库 ====================
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
    die(json_encode([
        'success' => false,
        'message' => '数据库连接失败'
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 验证送货单是否存在 ====================
$check_sql = "SELECT id, delivery_no, status FROM deliveries WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('i', $delivery_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '送货单不存在'
    ], JSON_UNESCAPED_UNICODE));
}

$delivery = $check_result->fetch_assoc();
$check_stmt->close();

// ==================== 检查状态限制（可选） ====================
// 可以限制某些状态不能删除，例如已完成的
/*
if ($delivery['status'] === 'completed') {
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '已完成的送货单不能删除'
    ], JSON_UNESCAPED_UNICODE));
}
*/

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    // ==================== 删除关联数据 ====================
    
    // 1. 删除送货明细（外键会自动级联删除，但为了明确，这里手动删除）
    $delete_items_sql = "DELETE FROM delivery_items WHERE delivery_id = ?";
    $delete_items_stmt = $conn->prepare($delete_items_sql);
    $delete_items_stmt->bind_param('i', $delivery_id);
    $delete_items_stmt->execute();
    $deleted_items = $delete_items_stmt->affected_rows;
    $delete_items_stmt->close();
    
    // 2. 删除日志记录
    $delete_logs_sql = "DELETE FROM delivery_logs WHERE delivery_id = ?";
    $delete_logs_stmt = $conn->prepare($delete_logs_sql);
    $delete_logs_stmt->bind_param('i', $delivery_id);
    $delete_logs_stmt->execute();
    $deleted_logs = $delete_logs_stmt->affected_rows;
    $delete_logs_stmt->close();
    
    // 3. 删除收款记录
    $delete_payments_sql = "DELETE FROM delivery_payments WHERE delivery_id = ?";
    $delete_payments_stmt = $conn->prepare($delete_payments_sql);
    $delete_payments_stmt->bind_param('i', $delivery_id);
    $delete_payments_stmt->execute();
    $deleted_payments = $delete_payments_stmt->affected_rows;
    $delete_payments_stmt->close();
    
    // 4. 删除主表
    $delete_sql = "DELETE FROM deliveries WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $delivery_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('删除送货单失败: ' . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    
    error_log("[送货管理] 用户 {$user_id} 删除了送货单: {$delivery['delivery_no']} (ID: {$delivery_id})");
    error_log("  - 删除明细: {$deleted_items} 条");
    error_log("  - 删除日志: {$deleted_logs} 条");
    error_log("  - 删除收款: {$deleted_payments} 条");
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "送货单 {$delivery['delivery_no']} 已删除"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[送货删除错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>