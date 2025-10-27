<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

try {
    $conn = getDBConnection();
    
    // 测试1：检查数据库连接
    echo json_encode([
        'step' => '1. 数据库连接',
        'success' => true
    ]) . "\n";
    
    // 测试2：检查quote_items表结构
    $result = $conn->query("DESCRIBE quote_items");
    $fields = [];
    while ($row = $result->fetch_assoc()) {
        $fields[] = $row['Field'] . ' (' . $row['Type'] . ')';
    }
    
    echo json_encode([
        'step' => '2. quote_items表结构',
        'fields' => $fields
    ], JSON_UNESCAPED_UNICODE) . "\n";
    
    // 测试3：检查products表
    $result = $conn->query("SELECT COUNT(*) as count FROM products");
    $row = $result->fetch_assoc();
    
    echo json_encode([
        'step' => '3. products表检查',
        'count' => $row['count']
    ]) . "\n";
    
    // 测试4：尝试插入测试产品
    $stmt = $conn->prepare("
        INSERT INTO products 
        (category_id, product_type, sku, name, spec, supplier_name, unit, cost_price, default_price, stock_quantity, min_stock, is_active, created_at)
        VALUES (?, 'hardware', ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
    ");
    
    $cat_id = 19;
    $sku = 'TEST-' . time();
    $name = '测试产品';
    $spec = '测试规格';
    $supplier = '测试品牌';
    $unit = '个';
    $cost = 100.0;
    $price = 120.0;
    
    $stmt->bind_param('isssssdd', $cat_id, $sku, $name, $spec, $supplier, $unit, $cost, $price);
    
    if ($stmt->execute()) {
        $test_id = $conn->insert_id;
        echo json_encode([
            'step' => '4. 插入测试产品',
            'success' => true,
            'test_product_id' => $test_id
        ]) . "\n";
        
        // 清理测试数据
        $conn->query("DELETE FROM products WHERE id = {$test_id}");
    } else {
        echo json_encode([
            'step' => '4. 插入测试产品',
            'success' => false,
            'error' => $stmt->error
        ]) . "\n";
    }
    
    $conn->close();
    
    echo json_encode(['overall' => '所有测试完成']) . "\n";
    
} catch (Exception $e) {
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ]) . "\n";
}
?>