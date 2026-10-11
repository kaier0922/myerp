<?php
/**
 * 产品删除功能 - 增强版（超级稳定）
 * 功能：安全删除产品，检查关联使用情况
 */

// 严格的输出控制
while (ob_get_level()) ob_end_clean();
ob_start();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// 错误处理函数
function sendError($message) {
    while (ob_get_level()) ob_end_clean();
    ob_start();
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

function sendSuccess($message) {
    while (ob_get_level()) ob_end_clean();
    ob_start();
    writeLog($conn, 'products', 'delete', '批量删除产品');
    echo json_encode(['success' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
    ob_end_flush();
    exit;
}

// 设置错误处理器
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    sendError("系统错误: $errstr");
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        sendError("致命错误: {$error['message']}");
    }
});

// ========== 步骤1：验证请求方法 ==========
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('请求方法错误，必须使用POST');
}

// ========== 步骤2：获取并验证产品ID ==========
$product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
if ($product_id <= 0) {
    sendError('产品ID无效');
}

// ========== 步骤3：加载配置和连接数据库 ==========
try {
    if (!file_exists('config.php')) {
        sendError('config.php 文件不存在');
    }
    require_once 'config.php';
    require_once __DIR__ . '/log_helper.php';
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user_id'])) sendError('请先登录', 401);
    
    if (!function_exists('getDBConnection')) {
        sendError('getDBConnection 函数不存在');
    }
    
    $conn = getDBConnection();
    
    if (!$conn) {
        sendError('数据库连接失败');
    }
    
    if ($conn->connect_error) {
        sendError('数据库连接错误: ' . $conn->connect_error);
    }
} catch (Exception $e) {
    sendError('配置加载失败: ' . $e->getMessage());
}

// ========== 步骤4：检查产品是否存在 ==========
$stmt = $conn->prepare("SELECT id, name, sku FROM products WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
if (!$stmt) {
    $conn->close();
    sendError('SQL准备失败: ' . $conn->error);
}

$stmt->bind_param('i', $product_id);
if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    sendError('查询失败: ' . $stmt->error);
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    $stmt->close();
    $conn->close();
    sendError('产品不存在（ID: ' . $product_id . '）');
}

$product = $result->fetch_assoc();
$stmt->close();

// ========== 步骤5：检查报价单使用情况 ==========
$quote_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM quote_items WHERE product_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $product_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $quote_count = $row['count'];
    }
    $stmt->close();
}

if ($quote_count > 0) {
    $conn->close();
    sendError("该产品已被 {$quote_count} 个报价单使用，无法删除。\n建议：在产品编辑页面停用该产品。");
}

// ========== 步骤6：检查交付单使用情况 ==========
$delivery_count = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM delivery_items WHERE product_id = ?");
if ($stmt) {
    $stmt->bind_param('i', $product_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $delivery_count = $row['count'];
    }
    $stmt->close();
}

if ($delivery_count > 0) {
    $conn->close();
    sendError("该产品已被 {$delivery_count} 个交付单使用，无法删除。\n建议：在产品编辑页面停用该产品。");
}

// ========== 步骤7：执行软删除 ==========
$stmt = $conn->prepare("UPDATE products SET is_active = 0 WHERE id = ? AND user_id = " . intval($_SESSION['user_id']));
if (!$stmt) {
    $conn->close();
    sendError('删除SQL准备失败: ' . $conn->error);
}

$stmt->bind_param('i', $product_id);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    $conn->close();
    sendError('删除执行失败: ' . $error);
}

$affected_rows = $stmt->affected_rows;
$stmt->close();
$conn->close();

// ========== 步骤8：返回成功 ==========
if ($affected_rows > 0) {
    sendSuccess("产品 [{$product['sku']}] {$product['name']} 已成功删除");
} else {
    sendError('删除失败：未影响任何记录');
}
?>
