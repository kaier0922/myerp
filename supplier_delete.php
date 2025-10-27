<?php
/**
 * =====================================================
 * 文件名：supplier_delete.php
 * 功能：删除/停用供应商
 * 描述：将供应商标记为停用状态，不会真正删除数据
 * 版本：1.0
 * 更新日期：2025-10-22
 * =====================================================
 */

session_start();
require_once 'config.php';

// ==================== 检查登录状态 ====================
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '请先登录'
        ]);
    } else {
        header('Location: login.php');
    }
    exit;
}

$user_id = $_SESSION['user_id'];

// ==================== 获取供应商ID ====================
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($supplier_id <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '无效的供应商ID'
        ]);
    } else {
        $_SESSION['error_message'] = '无效的供应商ID';
        header('Location: suppliers.php');
    }
    exit;
}

// ==================== 连接数据库 ====================
$conn = getDBConnection();

if (!$conn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '数据库连接失败'
        ]);
    } else {
        $_SESSION['error_message'] = '数据库连接失败';
        header('Location: suppliers.php');
    }
    exit;
}

// ==================== 查询供应商信息 ====================
$stmt = $conn->prepare("SELECT id, supplier_code, company_name, is_active FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '供应商不存在'
        ]);
    } else {
        $_SESSION['error_message'] = '供应商不存在';
        header('Location: suppliers.php');
    }
    exit;
}

$supplier = $result->fetch_assoc();
$company_name = $supplier['company_name'];
$is_active = $supplier['is_active'];
$stmt->close();

// ==================== 检查是否已停用 ====================
if ($is_active == 0) {
    $conn->close();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '该供应商已经是停用状态'
        ]);
    } else {
        $_SESSION['error_message'] = '该供应商已经是停用状态';
        header('Location: suppliers.php');
    }
    exit;
}

// ==================== 执行停用操作 ====================
// 供应商删除采用软删除：只标记为停用，不真正删除数据（保留历史记录）
try {
    $stmt = $conn->prepare("UPDATE suppliers SET is_active = 0, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    $message = "供应商 {$company_name} 已停用";
    
    // ==================== 返回成功结果 ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        // AJAX 请求：返回 JSON
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => $message
        ]);
    } else {
        // 普通请求：跳转回列表页
        $_SESSION['success_message'] = $message;
        header('Location: suppliers.php');
    }
    exit;
    
} catch (Exception $e) {
    $conn->close();
    
    // ==================== 返回错误结果 ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '停用失败：' . $e->getMessage()
        ]);
    } else {
        $_SESSION['error_message'] = '停用失败：' . $e->getMessage();
        header('Location: suppliers.php');
    }
    exit;
}
?>
