<?php
/**
 * ============================================================================
 * 文件名: delivery_payment.php
 * 版本: 1.0
 * 创建日期: 2025-10-17
 * 说明: 送货单收款登记处理
 * 
 * 功能说明：
 * 1. 登记收款记录
 * 2. 更新已收款金额
 * 3. 自动更新支付状态（未付款/部分付款/已付款）
 * 4. 记录操作日志
 * 
 * POST 参数：
 * - delivery_id: 送货单ID（必需）
 * - amount: 收款金额（必需）
 * - payment_method: 支付方式（必需）
 * - payee: 收款人
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
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$payee = isset($_POST['payee']) ? trim($_POST['payee']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// ==================== 数据验证 ====================
if ($delivery_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '无效的送货单ID'
    ], JSON_UNESCAPED_UNICODE));
}

if ($amount <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '收款金额必须大于0'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($payment_method)) {
    die(json_encode([
        'success' => false,
        'message' => '请选择支付方式'
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
$check_sql = "SELECT id, delivery_no, total_amount, paid_amount FROM deliveries WHERE id = ?";
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

// ==================== 验证收款金额 ====================
$total_amount = floatval($delivery['total_amount']);
$paid_amount = floatval($delivery['paid_amount']);
$unpaid_amount = $total_amount - $paid_amount;

if ($amount > $unpaid_amount + 0.01) {  // 允许0.01的误差
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => "收款金额不能超过未收款金额 ¥" . number_format($unpaid_amount, 2)
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    // ==================== 插入收款记录 ====================
    $payment_sql = "
        INSERT INTO delivery_payments (
            delivery_id,
            payment_type,
            amount,
            payment_method,
            payment_date,
            payee,
            notes,
            created_by,
            created_at
        ) VALUES (?, 'received', ?, ?, NOW(), ?, ?, ?, NOW())
    ";
    
    $payment_stmt = $conn->prepare($payment_sql);
    $payment_stmt->bind_param('idsssi', 
        $delivery_id, 
        $amount, 
        $payment_method, 
        $payee, 
        $notes, 
        $user_id
    );
    
    if (!$payment_stmt->execute()) {
        throw new Exception('插入收款记录失败: ' . $payment_stmt->error);
    }
    
    $payment_stmt->close();
    
    // ==================== 更新送货单的已付金额和支付状态 ====================
    $new_paid_amount = $paid_amount + $amount;
    
    // 计算新的支付状态
    if ($new_paid_amount >= $total_amount - 0.01) {  // 允许0.01的误差
        $payment_status = 'paid';  // 已付款
    } elseif ($new_paid_amount > 0) {
        $payment_status = 'partial';  // 部分付款
    } else {
        $payment_status = 'unpaid';  // 未付款
    }
    
    $update_sql = "
        UPDATE deliveries 
        SET paid_amount = ?,
            payment_status = ?,
            payment_method = ?,
            updated_at = NOW()
        WHERE id = ?
    ";
    
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('dssi', $new_paid_amount, $payment_status, $payment_method, $delivery_id);
    
    if (!$update_stmt->execute()) {
        throw new Exception('更新送货单失败: ' . $update_stmt->error);
    }
    
    $update_stmt->close();
    
    // ==================== 记录操作日志 ====================
    $log_description = "收款 ¥" . number_format($amount, 2);
    $log_description .= "，支付方式：{$payment_method}";
    
    if (!empty($payee)) {
        $log_description .= "，收款人：{$payee}";
    }
    
    if (!empty($notes)) {
        $log_description .= "，备注：{$notes}";
    }
    
    $log_sql = "
        INSERT INTO delivery_logs (
            delivery_id, 
            status, 
            description, 
            operator, 
            created_at
        ) VALUES (?, 'payment', ?, ?, NOW())
    ";
    
    $log_stmt = $conn->prepare($log_sql);
    $log_stmt->bind_param('iss', $delivery_id, $log_description, $user_name);
    
    if (!$log_stmt->execute()) {
        throw new Exception('记录日志失败: ' . $log_stmt->error);
    }
    
    $log_stmt->close();
    
    // ==================== 记录日志 ====================
    error_log("[送货管理] 用户 {$user_id} 为送货单 {$delivery['delivery_no']} 登记收款 ¥{$amount}");
    error_log("  - 支付方式: {$payment_method}");
    error_log("  - 原已付金额: ¥{$paid_amount}");
    error_log("  - 新已付金额: ¥{$new_paid_amount}");
    error_log("  - 支付状态: {$payment_status}");
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    $message = "收款登记成功！本次收款 ¥" . number_format($amount, 2);
    
    if ($payment_status === 'paid') {
        $message .= "，订单已全额付款";
    } else {
        $remaining = $total_amount - $new_paid_amount;
        $message .= "，还需收款 ¥" . number_format($remaining, 2);
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[送货收款错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>