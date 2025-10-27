<?php
/**
 * ============================================================================
 * 文件名: repair_delete.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 维修单删除处理
 * 
 * 功能说明：
 * 1. 删除维修单记录（硬删除）
 * 2. 级联删除配件明细
 * 3. 级联删除日志记录
 * 4. 级联删除收付款记录
 * 
 * POST/GET 参数：
 * - id: 维修单ID（必需）
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
$repair_id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($repair_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '无效的维修单ID'
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

// ==================== 验证维修单是否存在 ====================
$check_sql = "SELECT id, order_no, status FROM repair_orders WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('i', $repair_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '维修单不存在'
    ], JSON_UNESCAPED_UNICODE));
}

$repair = $check_result->fetch_assoc();
$check_stmt->close();

// ==================== 检查状态限制（可选） ====================
// 可以限制某些状态不能删除，例如已完成或已交付的
/*
if (in_array($repair['status'], ['completed', 'delivered'])) {
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '已完成或已交付的维修单不能删除'
    ], JSON_UNESCAPED_UNICODE));
}
*/

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    // ==================== 删除关联数据 ====================
    
    // 1. 删除配件明细（外键会自动级联删除，但为了明确，这里手动删除）
    $delete_parts_sql = "DELETE FROM repair_parts WHERE repair_id = ?";
    $delete_parts_stmt = $conn->prepare($delete_parts_sql);
    $delete_parts_stmt->bind_param('i', $repair_id);
    $delete_parts_stmt->execute();
    $deleted_parts = $delete_parts_stmt->affected_rows;
    $delete_parts_stmt->close();
    
    // 2. 删除日志记录
    $delete_logs_sql = "DELETE FROM repair_logs WHERE repair_id = ?";
    $delete_logs_stmt = $conn->prepare($delete_logs_sql);
    $delete_logs_stmt->bind_param('i', $repair_id);
    $delete_logs_stmt->execute();
    $deleted_logs = $delete_logs_stmt->affected_rows;
    $delete_logs_stmt->close();
    
    // 3. 删除收付款记录
    $delete_payments_sql = "DELETE FROM repair_payments WHERE repair_id = ?";
    $delete_payments_stmt = $conn->prepare($delete_payments_sql);
    $delete_payments_stmt->bind_param('i', $repair_id);
    $delete_payments_stmt->execute();
    $deleted_payments = $delete_payments_stmt->affected_rows;
    $delete_payments_stmt->close();
    
    // 4. 删除主表
    $delete_sql = "DELETE FROM repair_orders WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $repair_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('删除维修单失败: ' . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    
    error_log("[维修管理] 用户 {$user_id} 删除了维修单: {$repair['order_no']} (ID: {$repair_id})");
    error_log("  - 删除配件: {$deleted_parts} 条");
    error_log("  - 删除日志: {$deleted_logs} 条");
    error_log("  - 删除收付款: {$deleted_payments} 条");
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "维修单 {$repair['order_no']} 已删除"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[维修删除错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>