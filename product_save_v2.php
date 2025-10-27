<?php
/**
 * 文件名: quote_save_v2.php
 * 版本: v2.1
 * 说明: 保存报价单，支持手动输入产品自动保存到产品库
 * 修复: SQL参数绑定错误
 */

session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
$conn->begin_transaction();

try {
    // 1. 保存报价单主表
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $quote_date = $_POST['quote_date'] ?? date('Y-m-d');
    $valid_days = intval($_POST['valid_days'] ?? 15);
    $quote_no = $_POST['quote_no'] ?? '';
    $template_type = $_POST['template_type'] ?? 'assembled_pc';
    $status = $_POST['status'] ?? '草稿';
    $discount = floatval($_POST['discount'] ?? 0);
    $final_amount = floatval($_POST['final_amount'] ?? 0);
    $terms = $_POST['terms'] ?? '';
    
    // 生成报价单号
    if (empty($quote_no)) {
        $prefix = 'Q' . date('Ymd');
        $result = $conn->query("SELECT COUNT(*) as count FROM quotes WHERE quote_no LIKE '{$prefix}%'");
        $row = $result->fetch_assoc();
        $seq = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
        $quote_no = $prefix . $seq;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO quotes 
        (quote_no, customer_id, quote_date, valid_days, template_type, status, discount, final_amount, terms, created_by, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    
    $stmt->bind_param('sissssdds', $quote_no, $customer_id, $quote_date, $valid_days, $template_type, $status, $discount, $final_amount, $terms, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception('保存报价单失败: ' . $stmt->error);
    }
    
    $quote_id = $conn->insert_id;
    
    // 2. 处理报价单明细
    $items = $_POST['items'] ?? [];
    $line_no = 1;
    $newProductsCount = 0;
    
    foreach ($items as $item) {
        $product_id = !empty($item['product_id']) && $item['product_id'] !== 'custom' ? intval($item['product_id']) : null;
        $category_id = !empty($item['category_id']) ? intval($item['category_id']) : null;
        $category = $item['category'] ?? '';
        $product_name = $item['product_name'] ?? '';
        $spec = $item['spec'] ?? '';
        $unit = $item['unit'] ?? '个';
        $quantity = floatval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        
        // 检查是否为自定义输入
        $custom_name = trim($item['custom_name'] ?? '');
        $custom_supplier = trim($item['custom_supplier'] ?? '');
        
        // 如果是自定义输入且没有选择产品库的产品
        if (!empty($custom_name) && empty($product_id)) {
            // 检查产品是否已存在
            $check_stmt = $conn->prepare("
                SELECT id FROM products 
                WHERE name = ? AND is_active = 1
                LIMIT 1
            ");
            $check_stmt->bind_param('s', $custom_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // 使用现有产品
                $existing = $check_result->fetch_assoc();
                $product_id = $existing['id'];
                $product_name = $custom_name;
            } else {
                // 创建新产品
                $supplier_name = !empty($custom_supplier) ? $custom_supplier : '其他';
                
                // 生成SKU
                $sku_prefix = 'MANUAL-' . date('Ymd');
                $sku_result = $conn->query("SELECT COUNT(*) as count FROM products WHERE sku LIKE '{$sku_prefix}%'");
                $sku_row = $sku_result->fetch_assoc();
                $sku_seq = str_pad($sku_row['count'] + 1, 4, '0', STR_PAD_LEFT);
                $sku = $sku_prefix . '-' . $sku_seq;
                
                // 确定分类ID
                if (empty($category_id)) {
                    $category_id = 19; // 其他配件
                }
                
                // 插入新产品
                $insert_product_stmt = $conn->prepare("
                    INSERT INTO products 
                    (category_id, product_type, sku, name, spec, supplier_name, unit, cost_price, default_price, stock_quantity, min_stock, is_active, created_at)
                    VALUES (?, 'hardware', ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
                ");
                
                $cost_price = $price * 0.85;
                $stock_quantity = 0;
                
                $insert_product_stmt->bind_param(
                    'isssssddd',
                    $category_id,
                    $sku,
                    $custom_name,
                    $spec,
                    $supplier_name,
                    $unit,
                    $cost_price,
                    $price
                );
                
                if (!$insert_product_stmt->execute()) {
                    throw new Exception('保存手动输入的产品失败: ' . $insert_product_stmt->error);
                }
                
                $product_id = $conn->insert_id;
                $product_name = $custom_name;
                $newProductsCount++;
                
                error_log("✅ 自动创建产品: ID={$product_id}, SKU={$sku}, Name={$custom_name}");
            }
        }
        
        // 使用自定义名称（如果有）
        if (empty($product_name) && !empty($custom_name)) {
            $product_name = $custom_name;
        }
        
        // 跳过空行
        if (empty($product_name) && empty($product_id)) {
            continue;
        }
        
        // 保存报价单明细
        $subtotal = $quantity * $price;
        
        $stmt_item = $conn->prepare("
            INSERT INTO quote_items 
            (quote_id, line_no, product_id, category, product_name, spec, unit, quantity, price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_item->bind_param(
            'iiissssdd d',
            $quote_id,
            $line_no,
            $product_id,
            $category,
            $product_name,
            $spec,
            $unit,
            $quantity,
            $price,
            $subtotal
        );
        
        if (!$stmt_item->execute()) {
            throw new Exception('保存报价单明细失败: ' . $stmt_item->error);
        }
        
        $line_no++;
    }
    
    // 提交事务
    $conn->commit();
    
    $message = '保存成功';
    if ($newProductsCount > 0) {
        $message .= "，已自动添加 {$newProductsCount} 个新产品到产品库";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'quote_id' => $quote_id,
        'quote_no' => $quote_no,
        'new_products' => $newProductsCount
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('❌ 保存报价单错误: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>