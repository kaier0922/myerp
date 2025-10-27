<?php
/**
 * ============================================================================
 * 文件名: delivery_status_update.php
 * 版本: 1.0
 * 创建日期: 2025-10-17
 * 说明: 送货单状态更新处理
 * 
 * 功能说明：
 * 1. 更新送货单状态
 * 2. 签收确认（待送货→配送中→已完成）
 * 3. 取消送货
 * 4. 标记失败
 * 5. 记录操作日志
 * 
 * POST 参数：
 * - delivery_id: 送货单ID（必需）
 * - status: 新状态（delivering/completed/failed/cancelled）
 * - recipient_name: 收货人（完成时必需）
 * - actual_delivery_time: 实际送达时间（完成时）
 * - notes: 备注
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
$user_name = $_SESSION['username'] ?? '系统用户';

// ==================== 获取参数 ====================
$delivery_id = isset($_POST['delivery_id']) ? intval($_POST['delivery_id']) : 0;
$new_status = isset($_POST['status']) ? trim($_POST['status']) : '';
$recipient_name = isset($_POST['recipient_name']) ? trim($_POST['recipient_name']) : '';
$actual_delivery_time = isset($_POST['actual_delivery_time']) ? trim($_POST['actual_delivery_time']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// ==================== 数据验证 ====================
if ($delivery_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '无效的送货单ID'
    ], JSON_UNESCAPED_UNICODE));
}

$allowed_status = ['delivering', 'completed', 'failed', 'cancelled'];
if (!in_array($new_status, $allowed_status)) {
    die(json_encode([
        'success' => false,
        'message' => '无效的状态值'
    ], JSON_UNESCAPED_UNICODE));
}

// 完成状态必须填写收货人
if ($new_status === 'completed' && empty($recipient_name)) {
    die(json_encode([
        'success' => false,
        'message' => '签收确认时必须填写收货人'
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
$check_sql = "SELECT id, delivery_no, status, customer_name FROM deliveries WHERE id = ?";
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

// ==================== 验证状态转换是否合法 ====================
$current_status = $delivery['status'];

// 定义允许的状态转换
$allowed_transitions = [
    'pending' => ['delivering', 'cancelled'],
    'delivering' => ['completed', 'failed'],
    'completed' => [],  // 已完成不能再转换
    'failed' => ['delivering'],  // 失败可以重新配送
    'cancelled' => []  // 已取消不能再转换
];

if (!in_array($new_status, $allowed_transitions[$current_status] ?? [])) {
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => "不能从状态 \"{$current_status}\" 转换到 \"{$new_status}\""
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    // ==================== 更新送货单状态 ====================
    
    if ($new_status === 'completed') {
        // 完成时需要更新收货人和实际送达时间
        $update_sql = "
            UPDATE deliveries 
            SET status = ?, 
                recipient_name = ?,
                actual_delivery_time = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        // 如果没有提供时间，使用当前时间
        if (empty($actual_delivery_time)) {
            $actual_delivery_time = date('Y-m-d H:i:s');
        }
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('sssi', $new_status, $recipient_name, $actual_delivery_time, $delivery_id);
        
    } else {
        // 其他状态只更新状态字段
        $update_sql = "
            UPDATE deliveries 
            SET status = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('si', $new_status, $delivery_id);
    }
    
    if (!$update_stmt->execute()) {
        throw new Exception('更新送货单状态失败: ' . $update_stmt->error);
    }
    
    $update_stmt->close();
    
    // ==================== 生成日志描述 ====================
    $status_names = [
        'delivering' => '开始配送',
        'completed' => '签收完成',
        'failed' => '配送失败',
        'cancelled' => '取消送货'
    ];
    
    $log_description = $status_names[$new_status] ?? "更新状态为 {$new_status}";
    
    if ($new_status === 'completed') {
        $log_description .= "，收货人：{$recipient_name}";
    }
    
    if (!empty($notes)) {
        $log_description .= "，备注：{$notes}";
    }
    
    // ==================== 记录操作日志 ====================
    $log_sql = "
        INSERT INTO delivery_logs (
            delivery_id, 
            status, 
            description, 
            operator, 
            created_at
        ) VALUES (?, ?, ?, ?, NOW())
    ";
    
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param('isss', $delivery_id, $new_status, $log_description, $user_name);
    
    if (!$log_stmt->execute()) {
        throw new Exception('记录日志失败: ' . $log_stmt->error);
    }
    
    $log_stmt->close();
    
    // ==================== 记录日志 ====================
    error_log("[送货管理] 用户 {$user_id} 将送货单 {$delivery['delivery_no']} 状态从 {$current_status} 更新为 {$new_status}");
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    $status_label = $status_names[$new_status] ?? $new_status;
    echo json_encode([
        'success' => true,
        'message' => "状态已更新为：{$status_label}"
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[送货状态更新错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>