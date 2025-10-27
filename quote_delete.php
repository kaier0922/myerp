<?php
/**
 * =====================================================
 * 文件名：quote_delete.php
 * 功能：删除/作废报价单
 * 描述：将报价单状态标记为"已作废"，不会真正删除数据
 * 版本：2.0
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

// ==================== 获取报价单ID ====================
$quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quote_id <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '无效的报价单ID'
        ]);
    } else {
        $_SESSION['error_message'] = '无效的报价单ID';
        header('Location: quotes.php');
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
        header('Location: quotes.php');
    }
    exit;
}

// ==================== 查询报价单信息 ====================
$stmt = $conn->prepare("SELECT id, quote_no, status FROM quotes WHERE id = ?");
$stmt->bind_param("i", $quote_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '报价单不存在'
        ]);
    } else {
        $_SESSION['error_message'] = '报价单不存在';
        header('Location: quotes.php');
    }
    exit;
}

$quote = $result->fetch_assoc();
$quote_no = $quote['quote_no'];
$current_status = $quote['status'];
$stmt->close();

// ==================== 检查是否已经作废 ====================
if ($current_status === '已作废') {
    $conn->close();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '该报价单已经是作废状态'
        ]);
    } else {
        $_SESSION['error_message'] = '该报价单已经是作废状态';
        header('Location: quotes.php');
    }
    exit;
}

// ==================== 执行作废操作 ====================
// 所有状态都只标记为"已作废"，不真正删除数据（保留历史记录）
try {
    $stmt = $conn->prepare("UPDATE quotes SET status = '已作废' WHERE id = ?");
    $stmt->bind_param("i", $quote_id);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    $message = "报价单 {$quote_no} 已作废";
    
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
        header('Location: quotes.php');
    }
    exit;
    
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    
    // ==================== 返回错误结果 ====================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['ajax'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => '删除失败：' . $e->getMessage()
        ]);
    } else {
        $_SESSION['error_message'] = '删除失败：' . $e->getMessage();
        header('Location: quotes.php');
    }
    exit;
}
?>
