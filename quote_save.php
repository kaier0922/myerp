<?php
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// 验证登录
if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => '请先登录'], JSON_UNESCAPED_UNICODE));
}

$user_id = $_SESSION['user_id'];

// 验证必填参数
if (empty($_POST['customer_id'])) {
    die(json_encode(['success' => false, 'message' => '请选择客户'], JSON_UNESCAPED_UNICODE));
}

if (empty($_POST['items']) || !is_array($_POST['items'])) {
    die(json_encode(['success' => false, 'message' => '请添加产品明细'], JSON_UNESCAPED_UNICODE));
}

// 连接数据库
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
    die(json_encode(['success' => false, 'message' => '数据库连接失败'], JSON_UNESCAPED_UNICODE));
}

$conn->begin_transaction();

try {
    // ==================== 准备主表数据 ====================
    $customer_id = intval($_POST['customer_id']);
    $quote_date = $_POST['quote_date'] ?? date('Y-m-d');
    $valid_days = intval($_POST['valid_days'] ?? 15);
    $quote_no = trim($_POST['quote_no'] ?? '');
    $template_type = $_POST['template_type'] ?? 'assembled_pc';
    $status = $_POST['status'] ?? '草稿';
    $discount = floatval($_POST['discount'] ?? 0);
    $final_amount = floatval($_POST['final_amount'] ?? 0);
    $terms = $_POST['terms'] ?? '';
    $project_name = trim($_POST['project_name'] ?? '');
    $project_location = trim($_POST['project_location'] ?? '');
    $construction_period = trim($_POST['construction_period'] ?? '');
    
    // 生成报价单号
    if (empty($quote_no)) {
        $prefix = 'Q' . date('Ymd');
        $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM quotes WHERE quote_no LIKE ?");
        $search_pattern = $prefix . '%';
        $count_stmt->bind_param('s', $search_pattern);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_row = $count_result->fetch_assoc();
        $seq = str_pad($count_row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
        $quote_no = $prefix . $seq;
        $count_stmt->close();
    }
    
    // ==================== 插入 quotes 表 ====================
    // 13个字段（不含 id 和 created_at）
    $insert_quote_sql = "
        INSERT INTO quotes 
        (user_id, customer_id, project_name, project_location, construction_period, 
         quote_no, template_type, quote_date, valid_days, status, 
         final_amount, terms, discount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $conn->prepare($insert_quote_sql);
    if (!$stmt) {
        throw new Exception('准备SQL失败: ' . $conn->error);
    }
    
    // 参数类型：i i s s s s s s i s d s d (13个)
    $stmt->bind_param(
        'iissssssisdsd',
        $user_id,
        $customer_id,
        $project_name,
        $project_location,
        $construction_period,
        $quote_no,
        $template_type,
        $quote_date,
        $valid_days,
        $status,
        $final_amount,
        $terms,
        $discount
    );
    
    if (!$stmt->execute()) {
        throw new Exception('保存报价单失败: ' . $stmt->error);
    }
    
    $quote_id = $conn->insert_id;
    $stmt->close();
    
    error_log("[报价单] ID: {$quote_id}, 单号: {$quote_no}");
    
    // ==================== 处理明细 ====================
    $items = $_POST['items'];
    $seq = 1;
    $new_products_count = 0;
    
    foreach ($items as $item) {
        // 获取字段
        $product_id_raw = $item['product_id'] ?? '';
        $product_id = null;
        $category_id = intval($item['category_id'] ?? 19);
        $category = trim($item['category'] ?? '');
        $product_name = trim($item['product_name'] ?? '');
        $spec = trim($item['spec'] ?? '');
        $unit = trim($item['unit'] ?? '个');
        $quantity = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $brand = trim($item['brand'] ?? '');
        $model = trim($item['model'] ?? '');
        $warranty = trim($item['warranty'] ?? '');
        $remark = trim($item['remark'] ?? '');
        
        // 自定义输入
        $custom_name = trim($item['custom_name'] ?? '');
        $custom_supplier = trim($item['custom_supplier'] ?? '');
        
        // 处理产品ID
        if (!empty($product_id_raw) && $product_id_raw !== 'custom') {
            $product_id = intval($product_id_raw);
        }
        
        // ==================== 处理自定义产品 ====================
        if (!empty($custom_name) && ($product_id === null || $product_id_raw === 'custom')) {
            // 检查是否已存在
            $check_stmt = $conn->prepare("SELECT id FROM products WHERE name = ? AND is_active = 1 LIMIT 1");
            $check_stmt->bind_param('s', $custom_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $existing = $check_result->fetch_assoc();
                $product_id = $existing['id'];
                $product_name = $custom_name;
            } else {
                // 创建新产品
                $supplier_name = !empty($custom_supplier) ? $custom_supplier : '其他';
                
                // 生成SKU
                $sku_prefix = 'MANUAL-' . date('Ymd');
                $sku_count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM products WHERE sku LIKE ?");
                $sku_search = $sku_prefix . '%';
                $sku_count_stmt->bind_param('s', $sku_search);
                $sku_count_stmt->execute();
                $sku_count_result = $sku_count_stmt->get_result();
                $sku_count_row = $sku_count_result->fetch_assoc();
                $sku_seq = str_pad($sku_count_row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
                $sku = $sku_prefix . '-' . $sku_seq;
                $sku_count_stmt->close();
                
                // 插入产品
                $insert_product_sql = "
                    INSERT INTO products 
                    (category_id, product_type, sku, name, spec, supplier_name, unit, 
                     cost_price, default_price, stock_quantity, min_stock, is_active, created_at)
                    VALUES (?, 'hardware', ?, ?, ?, ?, ?, ?, ?, 0, 0, 1, NOW())
                ";
                
                $insert_product_stmt = $conn->prepare($insert_product_sql);
                if (!$insert_product_stmt) {
                    throw new Exception('准备插入产品SQL失败: ' . $conn->error);
                }
                
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
                    throw new Exception('创建产品失败: ' . $insert_product_stmt->error);
                }
                
                $product_id = $conn->insert_id;
                $product_name = $custom_name;
                $new_products_count++;
                $insert_product_stmt->close();
                
                error_log("[自定义] 新产品 ID: {$product_id}, SKU: {$sku}");
            }
            
            $check_stmt->close();
        }
        
        // 使用自定义名称
        if (empty($product_name) && !empty($custom_name)) {
            $product_name = $custom_name;
        }
        
        // 跳过空行
        if (empty($product_name)) {
            continue;
        }
        
        // ==================== 插入 quote_items ====================
        // 计算金额
        $subtotal = $quantity * $price;
        $cost = $price * 0.85;
        $cost_subtotal = $quantity * $cost;
        
        // custom_fields 设为 NULL
        $custom_fields = null;
        
        // 17个字段（不含 id）
        $insert_item_sql = "
            INSERT INTO quote_items 
            (quote_id, seq, category, product_id, product_name, 
             brand, model, spec, unit, warranty, 
             quantity, price, cost, subtotal, cost_subtotal, 
             custom_fields, remark)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $item_stmt = $conn->prepare($insert_item_sql);
        if (!$item_stmt) {
            throw new Exception('准备插入明细SQL失败: ' . $conn->error);
        }
        
        // 参数类型：i i s i s s s s s s i d d d d s s (17个)
        $item_stmt->bind_param(
            'iisissssssiddddss',
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
            $custom_fields,
            $remark
        );
        
        if (!$item_stmt->execute()) {
            throw new Exception('保存明细失败: ' . $item_stmt->error);
        }
        
        $item_stmt->close();
        error_log("[明细] seq: {$seq}, 产品: {$product_name}");
        
        $seq++;
    }
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    $message = '保存成功';
    if ($new_products_count > 0) {
        $message .= "，已自动添加 {$new_products_count} 个新产品到产品库";
    }
    
    error_log("[完成] 报价单 ID: {$quote_id}, 明细: " . ($seq - 1));
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'quote_id' => $quote_id,
        'quote_no' => $quote_no,
        'new_products' => $new_products_count
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log('[错误] ' . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>