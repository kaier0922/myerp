<?php
/**
 * ============================================================================
 * 文件名: delivery_save.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 送货单保存/更新处理
 * 
 * 功能说明：
 * 1. 添加新送货单
 * 2. 更新现有送货单
 * 3. 保存送货明细
 * 4. 自动生成送货单号
 * 5. 计算费用汇总
 * 6. 创建应收记录
 * 
 * POST 参数：
 * - action: add=添加, update=更新
 * - id: 送货单ID（更新时必需）
 * - quote_id: 报价单ID（可选）
 * - customer_id: 客户ID（可选）
 * - customer_name: 客户名称（必需）
 * - contact_name: 联系人（必需）
 * - contact_phone: 联系电话（必需）
 * - delivery_address: 送货地址（必需）
 * - delivery_date: 送货日期（必需）
 * - delivery_time: 送货时间段
 * - delivery_person: 送货人
 * - vehicle_no: 车牌号
 * - goods_amount: 货物金额
 * - freight_fee: 运费
 * - payment_method: 支付方式
 * - collect_on_delivery: 是否货到付款
 * - items[]: 送货明细数组
 * 
 * 返回 JSON：
 * {
 *   "success": true/false,
 *   "message": "提示信息",
 *   "delivery_id": 123
 * }
 * ============================================================================
 */

// ==================== 初始化 ====================
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

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
$delivery_id = isset($_POST['id']) ? intval($_POST['id']) : 0;

// 基本信息
$quote_id = isset($_POST['quote_id']) ? intval($_POST['quote_id']) : null;
$customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
$customer_name = isset($_POST['customer_name']) ? trim($_POST['customer_name']) : '';
$contact_name = isset($_POST['contact_name']) ? trim($_POST['contact_name']) : '';
$contact_phone = isset($_POST['contact_phone']) ? trim($_POST['contact_phone']) : '';
$delivery_address = isset($_POST['delivery_address']) ? trim($_POST['delivery_address']) : '';

// 送货信息
$delivery_date = isset($_POST['delivery_date']) ? trim($_POST['delivery_date']) : '';
$delivery_time = isset($_POST['delivery_time']) ? trim($_POST['delivery_time']) : '';
$delivery_person = isset($_POST['delivery_person']) ? trim($_POST['delivery_person']) : '';
$vehicle_no = isset($_POST['vehicle_no']) ? trim($_POST['vehicle_no']) : '';

// 费用信息
$goods_amount = isset($_POST['goods_amount']) ? floatval($_POST['goods_amount']) : 0;
$freight_fee = isset($_POST['freight_fee']) ? floatval($_POST['freight_fee']) : 0;
$total_amount = $goods_amount + $freight_fee;

// 支付信息
$payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
$collect_on_delivery = isset($_POST['collect_on_delivery']) ? 1 : 0;

// 备注
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// 送货明细
$items = isset($_POST['items']) ? $_POST['items'] : [];

// ==================== 数据验证 ====================
if (empty($customer_name)) {
    die(json_encode([
        'success' => false,
        'message' => '客户名称不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($contact_name)) {
    die(json_encode([
        'success' => false,
        'message' => '联系人不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($contact_phone)) {
    die(json_encode([
        'success' => false,
        'message' => '联系电话不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($delivery_address)) {
    die(json_encode([
        'success' => false,
        'message' => '送货地址不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($delivery_date)) {
    die(json_encode([
        'success' => false,
        'message' => '送货日期不能为空'
    ], JSON_UNESCAPED_UNICODE));
}

if (empty($items)) {
    die(json_encode([
        'success' => false,
        'message' => '至少需要一个送货产品'
    ], JSON_UNESCAPED_UNICODE));
}

// 验证明细
foreach ($items as $item) {
    if (empty($item['product_name'])) {
        die(json_encode([
            'success' => false,
            'message' => '产品名称不能为空'
        ], JSON_UNESCAPED_UNICODE));
    }
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

// ==================== 生成送货单号 ====================
function generateDeliveryNo($conn) {
    $prefix = 'SH';
    $date = date('Ymd');
    
    // 查询今天最大的序号
    $sql = "SELECT delivery_no FROM deliveries WHERE delivery_no LIKE ? ORDER BY delivery_no DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $pattern = $prefix . $date . '%';
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_no = $row['delivery_no'];
        $sequence = intval(substr($last_no, -4)) + 1;
    } else {
        $sequence = 1;
    }
    
    $stmt->close();
    
    return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// ==================== 处理操作 ====================
$conn->begin_transaction();

try {
    if ($action === 'add') {
        // ==================== 添加新送货单 ====================
        
        // 生成送货单号
        $delivery_no = generateDeliveryNo($conn);
        
        // 插入主表
        $insert_sql = "
            INSERT INTO deliveries (
                delivery_no,
                quote_id,
                customer_id,
                customer_name,
                contact_name,
                contact_phone,
                delivery_address,
                delivery_date,
                delivery_time,
                delivery_person,
                vehicle_no,
                status,
                goods_amount,
                freight_fee,
                total_amount,
                payment_method,
                paid_amount,
                payment_status,
                collect_on_delivery,
                notes,
                created_by,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending',
                ?, ?, ?, ?, 0, 'unpaid', ?, ?, ?, NOW()
            )
        ";
        
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param(
            'siissssssssdddsisi',
            $delivery_no,
            $quote_id,
            $customer_id,
            $customer_name,
            $contact_name,
            $contact_phone,
            $delivery_address,
            $delivery_date,
            $delivery_time,
            $delivery_person,
            $vehicle_no,
            $goods_amount,
            $freight_fee,
            $total_amount,
            $payment_method,
            $collect_on_delivery,
            $notes,
            $user_id
        );
        
        if (!$insert_stmt->execute()) {
            throw new Exception('添加送货单失败: ' . $insert_stmt->error);
        }
        
        $new_delivery_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        // 保存送货明细
        foreach ($items as $item) {
            if (empty($item['product_name'])) continue;
            
            $product_name = trim($item['product_name']);
            $product_spec = isset($item['product_spec']) ? trim($item['product_spec']) : '';
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $unit = isset($item['unit']) ? trim($item['unit']) : '件';
            $unit_price = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
            $subtotal = $quantity * $unit_price;
            
            $item_sql = "
                INSERT INTO delivery_items (
                    delivery_id, product_name, product_spec, quantity, unit, 
                    unit_price, subtotal, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->bind_param(
                'issisdd',
                $new_delivery_id,
                $product_name,
                $product_spec,
                $quantity,
                $unit,
                $unit_price,
                $subtotal
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception('保存送货明细失败: ' . $item_stmt->error);
            }
            
            $item_stmt->close();
        }
        
        // 创建初始日志
        $log_sql = "
            INSERT INTO delivery_logs (delivery_id, status, description, operator, created_at)
            VALUES (?, 'pending', '创建送货单', ?, NOW())
        ";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param('is', $new_delivery_id, $contact_name);
        $log_stmt->execute();
        $log_stmt->close();
        
        // 创建应收记录
        if ($total_amount > 0) {
            $payment_sql = "
                INSERT INTO delivery_payments (
                    delivery_id, 
                    payment_type, 
                    amount, 
                    payment_date, 
                    notes, 
                    created_by, 
                    created_at
                ) VALUES (?, 'receivable', ?, NOW(), '送货费用应收', ?, NOW())
            ";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param('idi', $new_delivery_id, $total_amount, $user_id);
            $payment_stmt->execute();
            $payment_stmt->close();
        }
        
        error_log("[送货管理] 用户 {$user_id} 创建了送货单: {$delivery_no} (ID: {$new_delivery_id})");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "送货单 {$delivery_no} 创建成功",
            'delivery_id' => $new_delivery_id
        ], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'update') {
        // ==================== 更新现有送货单 ====================
        
        if ($delivery_id <= 0) {
            throw new Exception('无效的送货单ID');
        }
        
        // 验证送货单是否存在
        $check_sql = "SELECT id, delivery_no FROM deliveries WHERE id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param('i', $delivery_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $check_stmt->close();
            $conn->close();
            die(json_encode([
                'success' => false,
                'message' => '送货单不存在'
            ], JSON_UNESCAPED_UNICODE));
        }
        
        $delivery = $check_result->fetch_assoc();
        $delivery_no = $delivery['delivery_no'];
        $check_stmt->close();
        
        // 更新主表
        $update_sql = "
            UPDATE deliveries SET
                quote_id = ?,
                customer_id = ?,
                customer_name = ?,
                contact_name = ?,
                contact_phone = ?,
                delivery_address = ?,
                delivery_date = ?,
                delivery_time = ?,
                delivery_person = ?,
                vehicle_no = ?,
                goods_amount = ?,
                freight_fee = ?,
                total_amount = ?,
                payment_method = ?,
                collect_on_delivery = ?,
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param(
            'iissssssssdddsisi',
            $quote_id,
            $customer_id,
            $customer_name,
            $contact_name,
            $contact_phone,
            $delivery_address,
            $delivery_date,
            $delivery_time,
            $delivery_person,
            $vehicle_no,
            $goods_amount,
            $freight_fee,
            $total_amount,
            $payment_method,
            $collect_on_delivery,
            $notes,
            $delivery_id
        );
        
        if (!$update_stmt->execute()) {
            throw new Exception('更新送货单失败: ' . $update_stmt->error);
        }
        
        $update_stmt->close();
        
        // 删除旧明细
        $delete_items_sql = "DELETE FROM delivery_items WHERE delivery_id = ?";
        $delete_items_stmt = $conn->prepare($delete_items_sql);
        $delete_items_stmt->bind_param('i', $delivery_id);
        $delete_items_stmt->execute();
        $delete_items_stmt->close();
        
        // 重新保存明细
        foreach ($items as $item) {
            if (empty($item['product_name'])) continue;
            
            $product_name = trim($item['product_name']);
            $product_spec = isset($item['product_spec']) ? trim($item['product_spec']) : '';
            $quantity = isset($item['quantity']) ? intval($item['quantity']) : 1;
            $unit = isset($item['unit']) ? trim($item['unit']) : '件';
            $unit_price = isset($item['unit_price']) ? floatval($item['unit_price']) : 0;
            $subtotal = $quantity * $unit_price;
            
            $item_sql = "
                INSERT INTO delivery_items (
                    delivery_id, product_name, product_spec, quantity, unit, 
                    unit_price, subtotal, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $item_stmt = $conn->prepare($item_sql);
            $item_stmt->bind_param(
                'issisdd',
                $delivery_id,
                $product_name,
                $product_spec,
                $quantity,
                $unit,
                $unit_price,
                $subtotal
            );
            
            if (!$item_stmt->execute()) {
                throw new Exception('保存送货明细失败: ' . $item_stmt->error);
            }
            
            $item_stmt->close();
        }
        
        // 更新日志
        $log_sql = "
            INSERT INTO delivery_logs (delivery_id, status, description, operator, created_at)
            VALUES (?, 'updated', '更新送货单信息', ?, NOW())
        ";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param('is', $delivery_id, $contact_name);
        $log_stmt->execute();
        $log_stmt->close();
        
        error_log("[送货管理] 用户 {$user_id} 更新了送货单: {$delivery_no} (ID: {$delivery_id})");
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "送货单 {$delivery_no} 更新成功"
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('无效的操作类型');
    }
    
} catch (Exception $e) {
    // ==================== 错误处理 ====================
    $conn->rollback();
    
    $error_msg = $e->getMessage();
    error_log("[送货管理错误] {$error_msg}");
    
    echo json_encode([
        'success' => false,
        'message' => $error_msg
    ], JSON_UNESCAPED_UNICODE);
}

// ==================== 关闭连接 ====================
$conn->close();
?>