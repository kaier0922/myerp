<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $product_id = $_POST['id'] ?? 0;
    $is_edit = $product_id > 0;
    
    // 获取表单数据
    $product_type = $_POST['product_type'] ?? '';
    $category_id = $_POST['category_id'] ?? 0;
    $sku = trim($_POST['sku'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $spec = trim($_POST['spec'] ?? '');
    $unit = trim($_POST['unit'] ?? '个');
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $default_price = floatval($_POST['default_price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $min_stock = intval($_POST['min_stock'] ?? 10);
    
    // 验证必填项
    if (empty($product_type) || empty($category_id) || empty($sku) || empty($name)) {
        echo json_encode(['success' => false, 'message' => '请填写所有必填项']);
        exit;
    }
    
    // 检查SKU是否重复
    if ($is_edit) {
        $check_stmt = $conn->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $check_stmt->bind_param("si", $sku, $product_id);
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM products WHERE sku = ?");
        $check_stmt->bind_param("s", $sku);
    }
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'SKU编号已存在，请使用其他编号']);
        exit;
    }
    
    if ($is_edit) {
        // 更新产品
        $stmt = $conn->prepare("
            UPDATE products SET
                product_type = ?,
                category_id = ?,
                sku = ?,
                name = ?,
                spec = ?,
                unit = ?,
                supplier_name = ?,
                cost_price = ?,
                default_price = ?,
                stock_quantity = ?,
                min_stock = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        
        $stmt->bind_param("sissssddiii",
            $product_type, $category_id, $sku, $name, $spec, $unit,
            $supplier_name, $cost_price, $default_price,
            $stock_quantity, $min_stock, $product_id
        );
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => '产品更新成功',
                'product_id' => $product_id
            ]);
        } else {
            throw new Exception('更新失败：' . $stmt->error);
        }
        
    } else {
        // 添加新产品
        $stmt = $conn->prepare("
            INSERT INTO products
            (product_type, category_id, sku, name, spec, unit, supplier_name,
             cost_price, default_price, stock_quantity, min_stock, is_active,created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->bind_param("sisssssddii",
            $product_type, $category_id, $sku, $name, $spec, $unit,
            $supplier_name, $cost_price, $default_price,
            $stock_quantity, $min_stock
        );
        
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'message' => '产品添加成功',
                'product_id' => $new_id
            ]);
        } else {
            throw new Exception('添加失败：' . $stmt->error);
        }
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>