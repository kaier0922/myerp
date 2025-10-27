<?php
/**
 * ============================================================================
 * 文件名: product_save.php
 * 版本: 3.1
 * 创建日期: 2025-10-17
 * 说明: 产品保存处理 - 修复参数绑定错误
 * ============================================================================
 */

// 临时开启错误显示（调试用）
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'config.php';

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
$product_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// 映射表单字段到数据库字段
$name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 1;
$product_type = isset($_POST['product_type']) ? trim($_POST['product_type']) : 'hardware';
$sku = isset($_POST['sku']) ? trim($_POST['sku']) : '';
$spec = isset($_POST['spec']) ? trim($_POST['spec']) : '';
$supplier_name = isset($_POST['supplier']) ? trim($_POST['supplier']) : '';
$unit = isset($_POST['unit']) ? trim($_POST['unit']) : '个';
$cost_price = isset($_POST['purchase_price']) ? floatval($_POST['purchase_price']) : 0;
$default_price = isset($_POST['selling_price']) ? floatval($_POST['selling_price']) : 0;
$stock_quantity = isset($_POST['stock_quantity']) ? intval($_POST['stock_quantity']) : 0;
$min_stock = isset($_POST['min_stock']) ? intval($_POST['min_stock']) : 10;
$is_active = 1;  // 默认激活

// ==================== 数据验证 ====================
if (empty($name)) {
    die(json_encode([
        'success' => false,
        'message' => '产品名称不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($sku)) {
    die(json_encode([
        'success' => false,
        'message' => 'SKU编码不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 连接数据库 ====================
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
    die(json_encode([
        'success' => false,
        'message' => '数据库连接失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    if ($action === 'add') {
        // ==================== 添加产品 ====================
        
        // 检查SKU是否重复
        $check_sku_sql = "SELECT id FROM products WHERE sku = ?";
        $check_sku_stmt = $conn->prepare($check_sku_sql);
        $check_sku_stmt->bind_param('s', $sku);
        $check_sku_stmt->execute();
        $check_sku_result = $check_sku_stmt->get_result();
        
        if ($check_sku_result->num_rows > 0) {
            $check_sku_stmt->close();
            $conn->close();
            die(json_encode([
                'success' => false,
                'message' => "SKU编码 {$sku} 已存在，请使用其他编码"
            ], JSON_UNESCAPED_UNICODE));
        }
        $check_sku_stmt->close();
        
        $insert_sql = "
            INSERT INTO products (
                name,
                category_id,
                product_type,
                sku,
                spec,
                supplier_name,
                unit,
                cost_price,
                default_price,
                stock_quantity,
                min_stock,
                is_active,
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        
        if (!$insert_stmt) {
            throw new Exception('准备SQL语句失败: ' . $conn->error);
        }
        
        // 绑定参数：13个参数
        // s i s s s s s d d i i i i
        $insert_stmt->bind_param(
            'sisssssddiii',
            $name,
            $category_id,
            $product_type,
            $sku,
            $spec,
            $supplier_name,
            $unit,
            $cost_price,
            $default_price,
            $stock_quantity,
            $min_stock,
            $is_active,
            $user_id
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception('添加产品失败: ' . $insert_stmt->error);
        }
        
        $new_product_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        error_log("[产品管理] 用户 {$user_id} 添加了产品: {$name} (SKU: {$sku}, ID: {$new_product_id})");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "产品 {$name} 添加成功",
            'product_id' => $new_product_id
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'update') {
        // ==================== 更新产品 ====================
        
        if ($product_id <= 0) {
            throw new Exception('无效的产品ID');
        }
        
        // 验证产品是否存在
        $check_sql = "SELECT id, name, sku FROM products WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $product_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            $conn->close();
            die(json_encode([
                'success' => false,
                'message' => '产品不存在'
            ], JSON_UNESCAPED_UNICODE));
        }
        
        $product = $check_result->fetch_assoc();
        $old_sku = $product['sku'];
        $check_stmt->close();
        
        // 如果SKU改变，检查新SKU是否重复
        if ($sku !== $old_sku) {
            $check_sku_sql = "SELECT id FROM products WHERE sku = ? AND id != ?";
            $check_sku_stmt = $conn->prepare($check_sku_sql);
            $check_sku_stmt->bind_param('si', $sku, $product_id);
            $check_sku_stmt->execute();
            $check_sku_result = $check_sku_stmt->get_result();
            
            if ($check_sku_result->num_rows > 0) {
                $check_sku_stmt->close();
                $conn->close();
                die(json_encode([
                    'success' => false,
                    'message' => "SKU编码 {$sku} 已存在，请使用其他编码"
                ], JSON_UNESCAPED_UNICODE));
            }
            $check_sku_stmt->close();
        }
        
        $update_sql = "
            UPDATE products SET
                name = ?,
                category_id = ?,
                product_type = ?,
                sku = ?,
                spec = ?,
                supplier_name = ?,
                unit = ?,
                cost_price = ?,
                default_price = ?,
                stock_quantity = ?,
                min_stock = ?,
                is_active = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        
        if (!$update_stmt) {
            throw new Exception('准备SQL语句失败: ' . $conn->error);
        }
        
        // 绑定参数：13个参数
        // s i s s s s s d d i i i i
        // 注意：这里是13个参数，最后一个是 product_id
        $update_stmt->bind_param(
            'sississddiiii',
            $name,
            $category_id,
            $product_type,
            $sku,
            $spec,
            $supplier_name,
            $unit,
            $cost_price,
            $default_price,
            $stock_quantity,
            $min_stock,
            $is_active,
            $product_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception('更新产品失败: ' . $update_stmt->error);
        }
        
        $update_stmt->close();
        
        error_log("[产品管理] 用户 {$user_id} 更新了产品: {$name} (SKU: {$sku}, ID: {$product_id})");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "产品 {$name} 更新成功"
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('无效的操作类型');
    }
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[产品管理错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>