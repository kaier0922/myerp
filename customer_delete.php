<?php
/**
 * ============================================================================
 * 文件名: customer_delete.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 客户删除处理
 * 
 * 功能说明：
 * 1. 删除客户记录（硬删除）
 * 2. 检查是否有关联的报价单
 * 3. 防止删除有业务关联的客户
 * 
 * POST/GET 参数：
 * - id: 客户ID（必需）
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

// 错误处理配置
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 设置响应头
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
$customer_id = isset($_POST['id']) ? intval($_POST['id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

if ($customer_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '无效的客户ID'
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

// ==================== 验证客户是否存在 ====================
$check_sql = "SELECT id, company_name FROM customers WHERE id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('i', $customer_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '客户不存在'
    ], JSON_UNESCAPED_UNICODE));
}

$customer = $check_result->fetch_assoc();
$check_stmt->close();

// ==================== 检查是否有关联的报价单 ====================
$quote_check_sql = "SELECT COUNT(*) as quote_count FROM quotes WHERE customer_id = ?";
$quote_check_stmt = $conn->prepare($quote_check_sql);
$quote_check_stmt->bind_param('i', $customer_id);
$quote_check_stmt->execute();
$quote_check_result = $quote_check_stmt->get_result();
$quote_data = $quote_check_result->fetch_assoc();
$quote_count = $quote_data['quote_count'];
$quote_check_stmt->close();

if ($quote_count > 0) {
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => "该客户有 {$quote_count} 个关联的报价单，无法删除。请先删除或转移相关报价单。"
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    // ==================== 删除客户 ====================
    $delete_sql = "DELETE FROM customers WHERE id = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('i', $customer_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('删除客户失败: ' . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    
    error_log("[客户管理] 用户 {$user_id} 删除了客户: {$customer['company_name']} (ID: {$customer_id})");
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "客户 {$customer['company_name']} 已删除"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[客户删除错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>