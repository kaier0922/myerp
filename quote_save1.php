<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? 'create';

try {
    $conn = getDBConnection();
    $conn->begin_transaction();

    // 获取基本信息
    $template_type = $_POST['template_type'] ?? '';
    $customer_id = $_POST['customer_id'] ?? 0;
    $quote_date = $_POST['quote_date'] ?? date('Y-m-d');
    $valid_days = $_POST['valid_days'] ?? 15;
    $status = $_POST['status'] ?? '草稿';
    $final_amount = $_POST['final_amount'] ?? 0;
    $discount = $_POST['discount'] ?? 0;
    
    // 项目信息（施工类）
    $project_name = $_POST['project_name'] ?? null;
    $project_location = $_POST['project_location'] ?? null;
    $construction_period = $_POST['construction_period'] ?? null;
    $terms = $_POST['terms'] ?? '';
    
    // 生成报价单号
    $quote_no = $_POST['quote_no'] ?? '';
    if (empty($quote_no)) {
        $prefix = [
            'assembled_pc' => 'QT-PC',
            'brand_pc' => 'QT-BR',
            'weak_current' => 'QT-WC',
            'strong_current' => 'QT-SC'
        ][$template_type] ?? 'QT';
        
        $date_str = date('Ymd');
        $last_no = $conn->query("SELECT quote_no FROM quotes WHERE quote_no LIKE '{$prefix}-{$date_str}%' ORDER BY quote_no DESC LIMIT 1")->fetch_assoc();
        
        if ($last_no) {
            $last_num = intval(substr($last_no['quote_no'], -3));
            $new_num = $last_num + 1;
        } else {
            $new_num = 1;
        }
        
        $quote_no = $prefix . '-' . $date_str . '-' . str_pad($new_num, 3, '0', STR_PAD_LEFT);
    }
    
    // 插入报价单主表
    $stmt = $conn->prepare("
        INSERT INTO quotes 
        (user_id, customer_id, quote_no, template_type, project_name, project_location, 
         construction_period, quote_date, valid_days, status, final_amount, discount)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("iissssssisdd", 
        $user_id, $customer_id, $quote_no, $template_type, 
        $project_name, $project_location, $construction_period,
        $quote_date, $valid_days, $status, $final_amount, $discount
    );
    
    if (!$stmt->execute()) {
        throw new Exception('保存报价单失败：' . $stmt->error);
    }
    
    $quote_id = $conn->insert_id;
    
    // 插入报价单明细
    $items = $_POST['items'] ?? [];
    $seq = 0;
    
    foreach ($items as $item) {
        $seq++;
        
        $product_name = $item['product_name'] ?? '';
        $brand = $item['brand'] ?? '';
        $model = $item['model'] ?? '';
        $spec = $item['spec'] ?? '';
        $unit = $item['unit'] ?? '个';
        $warranty = $item['warranty'] ?? '';
        $quantity = intval($item['quantity'] ?? 1);
        $price = floatval($item['price'] ?? 0);
        $remark = $item['remark'] ?? '';
        
        $subtotal = $quantity * $price;
        $cost = 0; // 这里可以从产品表获取成本价
        $cost_subtotal = $quantity * $cost;
        
        $stmt = $conn->prepare("
            INSERT INTO quote_items 
            (quote_id, seq, product_name, brand, model, spec, unit, warranty, 
             quantity, price, cost, subtotal, cost_subtotal, remark)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("iissssssidddds",
            $quote_id, $seq, $product_name, $brand, $model, $spec, $unit, $warranty,
            $quantity, $price, $cost, $subtotal, $cost_subtotal, $remark
        );
        
        if (!$stmt->execute()) {
            throw new Exception('保存报价明细失败：' . $stmt->error);
        }
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '保存成功',
        'quote_id' => $quote_id,
        'quote_no' => $quote_no
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>