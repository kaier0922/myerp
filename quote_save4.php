<?php
/**
 * 文件名: quote_save_v2.php
 * 版本: v2.3
 * 说明: 保存报价单，支持手动输入产品自动保存到产品库
 * 修复: 匹配实际的 quote_items 表结构（使用 seq 而非 line_no）
 */

session_start();
require_once 'config.php';

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '未登录'], JSON_UNESCAPED_UNICODE);
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
    $quote_no = trim($_POST['quote_no'] ?? '');
    $template_type = $_POST['template_type'] ?? 'assembled_pc';
    $status = $_POST['status'] ?? '草稿';
    $discount = floatval($_POST['discount'] ?? 0);
    $final_amount = floatval($_POST['final_amount'] ?? 0);
    
    // 检查 quotes 表是否有 terms 字段
    $columns_result = $conn->query("SHOW COLUMNS FROM quotes LIKE 'terms'");
    $has_terms = $columns_result->num_rows > 0;
    
    // 生成报价单号
    if (empty($quote_no)) {
        $prefix = 'Q' . date('Ymd');
        $result = $conn->query("SELECT COUNT(*) as count FROM quotes WHERE quote_no LIKE '{$prefix}%'");
        $row = $result->fetch_assoc();
        $seq = str_pad($row['count'] + 1, 4, '0', STR_PAD_LEFT);
        $quote_no = $prefix . $seq;
    }
    
    // 根据是否有 terms 字段动态构建 SQL
    if ($has_terms) {
        $terms = $_POST['terms'] ?? '';
        $stmt = $conn->prepare("
            INSERT INTO quotes 
            (quote_no, customer_id, quote_date, valid_days, template_type, status, discount, final_amount, terms, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param('sissssdds', $quote_no, $customer_id, $quote_date, $valid_days, $template_type, $status, $discount, $final_amount, $terms, $user_id);
    } else {
        $stmt = $conn->prepare("
            INSERT INTO quotes 
            (quote_no, customer_id, quote_date, valid_days, template_type, status, discount, final_amount, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param('sissssdd i', $quote_no, $customer_id, $quote_date, $valid_days, $template_type, $status, $discount, $final_amount, $user_id);
    }
    
    if (!$stmt->execute()) {
        throw new Exception('保存报价单失败: ' . $stmt->error);
    }
    
    $quote_id = $conn->insert_id;
    $stmt->close();
    
    error_log("✓ 创建报价单: quote_id={$quote_id}, quote_no={$quote_no}");
    
    // 2. 处理报价单明细
    $items = $_POST['items'] ?? [];
    $seq = 1;
    $newProductsCount = 0;
    
    foreach ($items as $item) {
        // 获取基本字段
        $product_id_raw = $item['product_id'] ?? '';
        $product_id = (!empty($product_id_raw) && $product_id_raw !== 'custom') ? intval($product_id_raw) : null;
        $category_id = !empty($item['category_id']) ? intval($item['category_id']) : 19;
        $category = trim($item['category'] ?? '');
        $product_name = trim($item['product_name'] ?? '');
        $spec = trim($item['spec'] ?? '');
        $unit = trim($item['unit'] ?? '个');
        $quantity = floatval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        
        // 品牌整机模板的额外字段
        $brand = trim($item['brand'] ?? '');
        $model = trim($item['model'] ?? '');
        $warranty = trim($item['warranty'] ?? '');
        $remark = trim($item['remark'] ?? '');
        
        // 检查是否为自定义输入
        $custom_name = trim($item['custom_name'] ?? '');
        $custom_supplier = trim($item['custom_supplier'] ?? '');
        
        // 🔥 如果是自定义输入
        if (!empty($custom_name) && ($product_id === null || $product_id_raw === 'custom')) {
            error_log("🔍 检测到自定义输入: {$custom_name}");
            
            // 检查产品是否已存在
            $check_stmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND is_active = 1 LIMIT 1");
            $check_stmt->bind_param('s', $custom_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing = $check_result->fetch_assoc();
                $product_id = $existing['id'];
                $product_name = $custom_name;
                error_log("✓ 使用现有产品 ID: {$product_id}");
            } else {
                // 创建新产品
                $supplier_name = !empty($custom_supplier) ? $custom_supplier : '其他';
                
                // 生成SKU
                $sku_prefix = 'MANUAL-' . date('Ymd');
                $sku_result = $conn->query("SELECT COUNT(*) as count FROM products WHERE sku LIKE '{$sku_prefix}%'");
                $sku_row = $sku_result->fetch_assoc();
                $sku_seq = str_pad($sku_row['count'] + 1, 4, '0', STR_PAD_LEFT);
                $sku = $sku_prefix . '-' . $sku_seq;
                
                // 插入新产品
                $insert_product_stmt = $conn->prepare("
                    INSERT INTO products 
                    (category_id, product_type, sku, name, spec, supplier_name, unit, cost_price, default_price, stock_quantity, min_stock, is_active, created_at)
                    VALUES (?, 'hardware', ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
                ");
                
                $cost_price = $price * 0.85;
                $default_price = $price;
                
                $insert_product_stmt->bind_param(
                    'isssssdd',
                    $category_id,
                    $sku,
                    $custom_name,
                    $spec,
                    $supplier_name,
                    $unit,
                    $cost_price,
                    $default_price
                );
                
                if (!$insert_product_stmt->execute()) {
                    throw new Exception('创建新产品失败: ' . $insert_product_stmt->error);
                }
                
                $product_id = $conn->insert_id;
                $product_name = $custom_name;
                $newProductsCount++;
                
                $insert_product_stmt->close();
                error_log("✅ 创建新产品: ID={$product_id}, SKU={$sku}, Name={$custom_name}");
            }
            
            $check_stmt->close();
        }
        
        // 使用自定义名称（如果有）
        if (empty($product_name) && !empty($custom_name)) {
            $product_name = $custom_name;
        }
        
        // 跳过空行
        if (empty($product_name) && ($product_id === null || $product_id === 0)) {
            error_log("⊙ 跳过空行: seq={$seq}");
            continue;
        }
        
        // 计算小计和成本
        $subtotal = $quantity * $price;
        $cost = $price * 0.85; // 默认成本为售价的85%
        $cost_subtotal = $quantity * $cost;
        
        // 🔥 匹配实际的 quote_items 表结构
        // 字段顺序：quote_id, seq, category, product_id, product_name, brand, model, spec, unit, warranty, quantity, price, cost, subtotal, cost_subtotal, remark
        $stmt_item = $conn->prepare("
            INSERT INTO quote_items 
            (quote_id, seq, category, product_id, product_name, brand, model, spec, unit, warranty, quantity, price, cost, subtotal, cost_subtotal, remark)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // 参数类型：i i s i s s s s s s i d d d d s
        // 16个参数
        $stmt_item->bind_param(
            'iisisssssidddds',
            $quote_id,
            $seq,
            $category,
            $product_id,
            $product_name,
            $brand,
            $model,
            $spec,
            $unit,
            $warranty,
            $quantity,
            $price,
            $cost,
            $subtotal,
            $cost_subtotal,
            $remark
        );
        
        if (!$stmt_item->execute()) {
            throw new Exception('保存报价单明细失败: ' . $stmt_item->error);
        }
        
        $stmt_item->close();
        error_log("✓ 保存明细: seq={$seq}, product={$product_name}, price={$price}, subtotal={$subtotal}");
        
        $seq++;
    }
    
    // 提交事务
    $conn->commit();
    
    $message = '保存成功';
    if ($newProductsCount > 0) {
        $message .= "，已自动添加 {$newProductsCount} 个新产品到产品库";
    }
    
    error_log("🎉 报价单保存完成: quote_id={$quote_id}, 明细数=" . ($seq - 1));
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'quote_id' => $quote_id,
        'quote_no' => $quote_no,
        'new_products' => $newProductsCount
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('❌ 保存报价单错误: ' . $e->getMessage());
    error_log('❌ 错误堆栈: ' . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => '保存失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>