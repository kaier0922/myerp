<?php
/**
 * repair_save.php - 终极修复版
 */

// 🔥 第一步：立即开启输出缓冲
ob_start();

session_start();

// 🔥 第二步：引入配置（确保 config.php 没有 BOM 和多余输出）
require_once 'config.php';

// 关闭所有错误显示
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// 🔥 第三步：清空之前所有输出
while (ob_get_level() > 1) {
    ob_end_clean();
}
ob_clean();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    // 权限验证
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('请先登录');
    }

    $user_id = $_SESSION['user_id'];

    // 获取参数
    $action = $_POST['action'] ?? '';
    $repair_id = intval($_POST['id'] ?? 0);

    // 基本信息
    $repair_type = trim($_POST['repair_type'] ?? 'inshop');
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $customer_name = trim($_POST['customer_name'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_address = trim($_POST['contact_address'] ?? '');

    // 设备信息
    $device_type = trim($_POST['device_type'] ?? '');
    $device_brand = trim($_POST['device_brand'] ?? '');
    $device_model = trim($_POST['device_model'] ?? '');
    $device_sn = trim($_POST['device_sn'] ?? '');
    $appearance_check = trim($_POST['appearance_check'] ?? '');
    $accessories = trim($_POST['accessories'] ?? '');

    // 维修信息
    $fault_description = trim($_POST['fault_description'] ?? '');
    $technician = trim($_POST['technician'] ?? '');
    $expected_finish_date = !empty($_POST['expected_finish_date']) ? trim($_POST['expected_finish_date']) : null;

    // 费用信息
    $service_fee = floatval($_POST['service_fee'] ?? 0);
    $parts_fee = floatval($_POST['parts_fee'] ?? 0);
    $other_fee = floatval($_POST['other_fee'] ?? 0);
    $total_fee = $service_fee + $parts_fee + $other_fee;

    // 备注和配件
    $notes = trim($_POST['notes'] ?? '');
    $parts = $_POST['parts'] ?? [];

    // 数据验证
    if (empty($customer_name)) throw new Exception('客户姓名不能为空');
    if (empty($contact_phone)) throw new Exception('联系电话不能为空');
    if (empty($fault_description)) throw new Exception('故障描述不能为空');
    if ($repair_type === 'onsite' && empty($contact_address)) throw new Exception('上门服务必须填写联系地址');
    if ($repair_type === 'inshop' && empty($device_type)) throw new Exception('带回维修必须填写设备类型');

    // 连接数据库
    $conn = getDBConnection();
    $conn->begin_transaction();

    if ($action === 'add') {
        // ==================== 添加 ====================
        
        // 生成单号
        $prefix = 'WX' . date('Ymd');
        $sql = "SELECT order_no FROM repair_orders WHERE order_no LIKE ? ORDER BY order_no DESC LIMIT 1";
        $stmt = $conn->prepare($sql);
        $pattern = $prefix . '%';
        $stmt->bind_param('s', $pattern);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $sequence = intval(substr($row['order_no'], -4)) + 1;
        } else {
            $sequence = 1;
        }
        $stmt->close();
        
        $order_no = $prefix . str_pad($sequence, 4, '0', STR_PAD_LEFT);
        
        // 插入主表
        $sql = "INSERT INTO repair_orders (
            order_no, repair_type, customer_id, customer_name, contact_phone,
            contact_address, device_type, device_brand, device_model, device_sn,
            fault_description, appearance_check, accessories, receive_date,
            expected_finish_date, status, technician, service_fee, parts_fee,
            other_fee, total_fee, paid_amount, payment_status, notes, created_by
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, 
            'pending', ?, ?, ?, ?, ?, 0.00, 'unpaid', ?, ?
        )";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('准备语句失败: ' . $conn->error);
        
        $stmt->bind_param('ssissssssssssssddddsi',
            $order_no, $repair_type, $customer_id, $customer_name, $contact_phone,
            $contact_address, $device_type, $device_brand, $device_model, $device_sn,
            $fault_description, $appearance_check, $accessories, $expected_finish_date,
            $technician, $service_fee, $parts_fee, $other_fee, $total_fee, $notes, $user_id
        );
        
        if (!$stmt->execute()) throw new Exception('执行失败: ' . $stmt->error);
        
        $new_id = $stmt->insert_id;
        $stmt->close();
        
        // 保存配件
        if (!empty($parts) && $repair_type === 'inshop') {
            foreach ($parts as $part) {
                if (empty($part['name'])) continue;
                
                $sql = "INSERT INTO repair_parts (repair_id, part_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                $qty = intval($part['quantity'] ?? 1);
                $price = floatval($part['price'] ?? 0);
                $subtotal = $qty * $price;
                
                $stmt->bind_param('isidd', $new_id, trim($part['name']), $qty, $price, $subtotal);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // 添加日志
        $sql = "INSERT INTO repair_logs (repair_id, status, description, operator) VALUES (?, 'pending', '创建维修单', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $new_id, $customer_name);
        $stmt->execute();
        $stmt->close();
        
        // 创建应收
        if ($total_fee > 0) {
            $sql = "INSERT INTO repair_payments (repair_id, payment_type, amount, payment_date, notes, created_by) VALUES (?, 'receivable', ?, NOW(), '维修费用应收', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('idi', $new_id, $total_fee, $user_id);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        $conn->close();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => "维修单 {$order_no} 创建成功", 'repair_id' => $new_id], JSON_UNESCAPED_UNICODE);
        
    } elseif ($action === 'update') {
        // ==================== 更新 ====================
        
        if ($repair_id <= 0) throw new Exception('无效的维修单ID');
        
        // 验证存在
        $sql = "SELECT order_no FROM repair_orders WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $repair_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) throw new Exception('维修单不存在');
        
        $order_no = $result->fetch_assoc()['order_no'];
        $stmt->close();
        
        // 更新主表
        $sql = "UPDATE repair_orders SET
            repair_type=?, customer_id=?, customer_name=?, contact_phone=?,
            contact_address=?, device_type=?, device_brand=?, device_model=?,
            device_sn=?, fault_description=?, appearance_check=?, accessories=?,
            expected_finish_date=?, technician=?, service_fee=?, parts_fee=?,
            other_fee=?, total_fee=?, notes=?
        WHERE id=?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('准备语句失败: ' . $conn->error);
        
        // 🔥 关键修复：20个参数，无空格
        $stmt->bind_param('sisssssssssssddddsi',
            $repair_type, $customer_id, $customer_name, $contact_phone,
            $contact_address, $device_type, $device_brand, $device_model,
            $device_sn, $fault_description, $appearance_check, $accessories,
            $expected_finish_date, $technician, $service_fee, $parts_fee,
            $other_fee, $total_fee, $notes, $repair_id
        );
        
        if (!$stmt->execute()) throw new Exception('执行失败: ' . $stmt->error);
        $stmt->close();
        
        // 删除旧配件
        $sql = "DELETE FROM repair_parts WHERE repair_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $repair_id);
        $stmt->execute();
        $stmt->close();
        
        // 保存新配件
        if (!empty($parts) && $repair_type === 'inshop') {
            foreach ($parts as $part) {
                if (empty($part['name'])) continue;
                
                $sql = "INSERT INTO repair_parts (repair_id, part_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                
                $qty = intval($part['quantity'] ?? 1);
                $price = floatval($part['price'] ?? 0);
                $subtotal = $qty * $price;
                
                $stmt->bind_param('isidd', $repair_id, trim($part['name']), $qty, $price, $subtotal);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        // 添加日志
        $sql = "INSERT INTO repair_logs (repair_id, status, description, operator) VALUES (?, 'updated', '更新维修单', ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $repair_id, $customer_name);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $conn->close();
        
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => "维修单 {$order_no} 更新成功"], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('无效的操作');
    }
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

exit;