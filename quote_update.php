<?php
/**
 * ============================================================================
 * 文件名: quote_update.php
 * 版本: 1.0
 * 创建日期: 2025-10-12
 * 说明: 报价单更新处理脚本
 * 
 * 功能说明：
 * 1. 接收 quote_edit.php 提交的表单数据
 * 2. 验证用户权限
 * 3. 更新 quotes 主表
 * 4. 删除旧的明细数据
 * 5. 插入新的明细数据
 * 6. 处理自定义输入的新产品
 * 7. 使用事务确保数据一致性
 * 
 * POST 参数：
 * - quote_id: 报价单ID（必需）
 * - customer_id: 客户ID
 * - quote_date: 报价日期
 * - valid_days: 有效期
 * - discount: 折扣
 * - final_amount: 最终金额
 * - terms: 条款说明
 * - items[]: 产品明细数组
 * 
 * 返回 JSON：
 * {
 *   "success": true/false,
 *   "message": "提示信息",
 *   "quote_id": 报价单ID,
 *   "new_products": 新增产品数量
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

// 设置响应头为 JSON
header('Content-Type: application/json; charset=utf-8');

// ==================== 权限验证 ====================
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'success' => false,
        'message' => '请先登录'
    ], JSON_UNESCAPED_UNICODE));
}

$user_id = $_SESSION['user_id'];

// ==================== 验证必填参数 ====================
if (empty($_POST['quote_id'])) {
    die(json_encode([
        'success' => false,
        'message' => '报价单ID不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

$quote_id = intval($_POST['quote_id']);

if (empty($_POST['customer_id'])) {
    die(json_encode([
        'success' => false,
        'message' => '请选择客户'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($_POST['items']) || !is_array($_POST['items'])) {
    die(json_encode([
        'success' => false,
        'message' => '请添加产品明细'
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

// ==================== 验证报价单权限 ====================
$check_sql = "SELECT id FROM quotes WHERE id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param('ii', $quote_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $check_stmt->close();
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '报价单不存在或无权限修改'
    ], JSON_UNESCAPED_UNICODE));
}
$check_stmt->close();

// ==================== 开启事务 ====================
$conn->begin_transaction();

try {
    // ==================== 准备更新数据 ====================
    $customer_id = intval($_POST['customer_id']);
    $quote_date = $_POST['quote_date'] ?? date('Y-m-d');
    $valid_days = intval($_POST['valid_days'] ?? 15);
    $template_type = $_POST['template_type'] ?? 'assembled_pc';
    $discount = floatval($_POST['discount'] ?? 0);
    $final_amount = floatval($_POST['final_amount'] ?? 0);
    $terms = $_POST['terms'] ?? '';
    $project_name = trim($_POST['project_name'] ?? '');
    $project_location = trim($_POST['project_location'] ?? '');
    $construction_period = trim($_POST['construction_period'] ?? '');
    
    // ==================== 更新 quotes 主表 ====================
    $update_quote_sql = "
        UPDATE quotes 
        SET customer_id = ?,
            project_name = ?,
            project_location = ?,
            construction_period = ?,
            template_type = ?,
            quote_date = ?,
            valid_days = ?,
            final_amount = ?,
            terms = ?,
            discount = ?
        WHERE id = ? AND user_id = ?
    ";
    
    $update_stmt = $conn->prepare($update_quote_sql);
    if (!$update_stmt) {
        throw new Exception('准备更新SQL失败: ' . $conn->error);
    }
    
    // 参数类型：i s s s s s i d s d i i (12个)
    $update_stmt->bind_param(
        'isssssidsdii',
        $customer_id,
        $project_name,
        $project_location,
        $construction_period,
        $template_type,
        $quote_date,
        $valid_days,
        $final_amount,
        $terms,
        $discount,
        $quote_id,
        $user_id
    );
    
    if (!$update_stmt->execute()) {
        throw new Exception('更新报价单失败: ' . $update_stmt->error);
    }
    
    $update_stmt->close();
    error_log("[更新] 报价单 ID: {$quote_id}");
    
    // ==================== 删除旧的明细数据 ====================
    $delete_items_sql = "DELETE FROM quote_items WHERE quote_id = ?";
    $delete_stmt = $conn->prepare($delete_items_sql);
    $delete_stmt->bind_param('i', $quote_id);
    
    if (!$delete_stmt->execute()) {
        throw new Exception('删除旧明细失败: ' . $delete_stmt->error);
    }
    
    $delete_stmt->close();
    error_log("[删除] 报价单 {$quote_id} 的旧明细");
    
    // ==================== 插入新的明细数据 ====================
    $items = $_POST['items'];
    $seq = 1;
    $new_products_count = 0;
    
    foreach ($items as $item) {
        // 获取字段值
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
        
        // 自定义输入字段
        $custom_name = trim($item['custom_name'] ?? '');
        $custom_supplier = trim($item['custom_supplier'] ?? '');
        
        // 处理产品ID
        if (!empty($product_id_raw) && $product_id_raw !== 'custom') {
            $product_id = intval($product_id_raw);
        }
        
        // ==================== 处理自定义输入产品 ====================
        if (!empty($custom_name) && ($product_id === null || $product_id_raw === 'custom')) {
            error_log("[自定义] 产品名: {$custom_name}");
            
            // 检查产品是否已存在
            $check_product_sql = "SELECT id FROM products WHERE name = ? AND is_active = 1 LIMIT 1";
            $check_product_stmt = $conn->prepare($check_product_sql);
            $check_product_stmt->bind_param('s', $custom_name);
            $check_product_stmt->execute();
            $check_product_result = $check_product_stmt->get_result();
            
            if ($check_product_result->num_rows > 0) {
                // 使用现有产品
                $existing_product = $check_product_result->fetch_assoc();
                $product_id = $existing_product['id'];
                $product_name = $custom_name;
                error_log("[自定义] 使用现有产品 ID: {$product_id}");
            } else {
                // 创建新产品
                $supplier_name = !empty($custom_supplier) ? $custom_supplier : '其他';
                
                // 生成SKU
                $sku_prefix = 'MANUAL-' . date('Ymd');
                $sku_count_sql = "SELECT COUNT(*) as cnt FROM products WHERE sku LIKE ?";
                $sku_count_stmt = $conn->prepare($sku_count_sql);
                $sku_search = $sku_prefix . '%';
                $sku_count_stmt->bind_param('s', $sku_search);
                $sku_count_stmt->execute();
                $sku_count_result = $sku_count_stmt->get_result();
                $sku_count_row = $sku_count_result->fetch_assoc();
                $sku_seq_num = str_pad($sku_count_row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
                $sku = $sku_prefix . '-' . $sku_seq_num;
                $sku_count_stmt->close();
                
                // 插入新产品
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
                
                // 参数类型：i s s s s s d d (8个)
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
            
            $check_product_stmt->close();
        }
        
        // 使用自定义名称
        if (empty($product_name) && !empty($custom_name)) {
            $product_name = $custom_name;
        }
        
        // 跳过空行
        if (empty($product_name)) {
            error_log("[跳过] 空行 seq: {$seq}");
            continue;
        }
        
        // ==================== 插入明细 ====================
        $subtotal = $quantity * $price;
        $cost = $price * 0.85;
        $cost_subtotal = $quantity * $cost;
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
            throw new Exception('插入明细失败: ' . $item_stmt->error);
        }
        
        $item_stmt->close();
        error_log("[明细] seq: {$seq}, 产品: {$product_name}");
        
        $seq++;
    }
    
    // ==================== 提交事务 ====================
    $conn->commit();
    
    $message = '更新成功';
    if ($new_products_count > 0) {
        $message .= "，已自动添加 {$new_products_count} 个新产品到产品库";
    }
    
    error_log("[完成] 报价单 ID: {$quote_id}, 明细数: " . ($seq - 1) . ", 新产品数: {$new_products_count}");
    
    // ==================== 返回成功响应 ====================
    echo json_encode([
        'success' => true,
        'message' => $message,
        'quote_id' => $quote_id,
        'new_products' => $new_products_count
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[错误] 更新报价单失败: {$error_msg}");
    error_log("[错误] 堆栈: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>