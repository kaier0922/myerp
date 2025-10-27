<?php
/**
 * ============================================================================
 * 文件名: customer_save.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 客户保存/更新处理
 * 
 * 功能说明：
 * 1. 添加新客户
 * 2. 更新现有客户
 * 3. 数据验证和安全处理
 * 
 * POST 参数：
 * - action: add=添加, update=更新
 * - id: 客户ID（更新时必需）
 * - company_name: 公司名称（必需）
 * - contact_name: 联系人（必需）
 * - phone: 电话（必需）
 * - email: 邮箱（可选）
 * - address: 地址（可选）
 * - notes: 备注（可选）
 * 
 * 返回 JSON：
 * {
 *   "success": true/false,
 *   "message": "提示信息",
 *   "customer_id": 123  // 仅添加时返回
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
$action = isset($_POST['action']) ? $_POST['action'] : '';
$customer_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
$contact_name = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$address = isset($_POST['address']) ? trim($_POST['address']) : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// ==================== 数据验证 ====================
if (empty($company_name)) {
    die(json_encode([
        'success' => false,
        'message' => '公司名称不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($contact_name)) {
    die(json_encode([
        'success' => false,
        'message' => '联系人不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($phone)) {
    die(json_encode([
        'success' => false,
        'message' => '联系电话不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

// 验证电话格式（可选）
if (!preg_match('/^1[3-9]\d{9}$/', $phone) && !preg_match('/^\d{3,4}-\d{7,8}$/', $phone)) {
    // 允许手机号或座机号
    // 手机：13800138000
    // 座机：010-12345678 或 0571-87654321
}

// 验证邮箱格式（如果填写）
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode([
        'success' => false,
        'message' => '邮箱格式不正确'
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

// ==================== 处理操作 ====================
$conn->begin_transaction();

try {
    if ($action === 'add') {
        // ==================== 添加新客户 ====================
        
        // 检查公司名称是否重复
        $check_sql = "SELECT id FROM customers WHERE company_name = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('s', $company_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $check_stmt->close();
            $conn->close();
            die(json_encode([
                'success' => false,
                'message' => '公司名称已存在，请使用其他名称'
            ], JSON_UNESCAPED_UNICODE));
        }
        $check_stmt->close();
        
        // 插入新客户
        $insert_sql = "
            INSERT INTO customers (
                company_name, 
                contact_name, 
                phone, 
                email, 
                address, 
                notes, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param('ssssss', 
            $company_name, 
            $contact_name, 
            $phone, 
            $email, 
            $address, 
            $notes
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception('添加客户失败: ' . $insert_stmt->error);
        }
        
        $new_customer_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        error_log("[客户管理] 用户 {$user_id} 添加了客户: {$company_name} (ID: {$new_customer_id})");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "客户 {$company_name} 添加成功",
            'customer_id' => $new_customer_id
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'update') {
        // ==================== 更新现有客户 ====================
        
        if ($customer_id <= 0) {
            throw new Exception('无效的客户ID');
        }
        
        // 验证客户是否存在
        $check_sql = "SELECT id FROM customers WHERE id = ?";
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
        $check_stmt->close();
        
        // 检查公司名称是否与其他客户重复
        $check_name_sql = "SELECT id FROM customers WHERE company_name = ? AND id != ?";
        $check_name_stmt = $conn->prepare($check_name_sql);
        $check_name_stmt->bind_param('si', $company_name, $customer_id);
        $check_name_stmt->execute();
        $check_name_result = $check_name_stmt->get_result();
        
        if ($check_name_result->num_rows > 0) {
            $check_name_stmt->close();
            $conn->close();
            die(json_encode([
                'success' => false,
                'message' => '公司名称已被其他客户使用'
            ], JSON_UNESCAPED_UNICODE));
        }
        $check_name_stmt->close();
        
        // 更新客户信息
        $update_sql = "
            UPDATE customers SET
                company_name = ?,
                contact_name = ?,
                phone = ?,
                email = ?,
                address = ?,
                notes = ?
            WHERE id = ?
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ssssssi', 
            $company_name, 
            $contact_name, 
            $phone, 
            $email, 
            $address, 
            $notes,
            $customer_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception('更新客户失败: ' . $update_stmt->error);
        }
        
        $update_stmt->close();
        
        error_log("[客户管理] 用户 {$user_id} 更新了客户: {$company_name} (ID: {$customer_id})");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "客户 {$company_name} 更新成功"
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('无效的操作类型');
    }
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[客户管理错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>