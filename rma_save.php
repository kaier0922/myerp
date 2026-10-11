<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
$input = json_decode(file_get_contents('php://input'), true);
$conn  = getDBConnection();
$id           = intval($input['id'] ?? 0);
$supplier_id  = intval($input['supplier_id'] ?? 0) ?: null;
$customer_id  = intval($input['customer_id'] ?? 0) ?: null;
$product_id   = intval($input['product_id']  ?? 0) ?: null;
$serial_number   = trim($input['serial_number']   ?? '');
$fault_desc      = trim($input['fault_desc']      ?? '');
$send_date       = $input['send_date']       ?: null;
$expected_return = $input['expected_return'] ?: null;
$status          = $input['status']          ?? 'pending';
$result          = $input['result']          ?: null;
$freight_fee     = floatval($input['freight_fee'] ?? 0);
$repair_fee      = floatval($input['repair_fee']  ?? 0);
$notes           = trim($input['notes'] ?? '');
if (!$fault_desc) { echo json_encode(['success'=>false,'message'=>'故障描述不能为空']); exit; }
try {
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE rma_orders SET supplier_id=?,customer_id=?,product_id=?,serial_number=?,fault_desc=?,send_date=?,expected_return=?,status=?,result=?,freight_fee=?,repair_fee=?,notes=?,updated_at=NOW() WHERE id=? AND created_by=" . intval($_SESSION['user_id']));
        $stmt->bind_param("iiissssssddsi", $supplier_id,$customer_id,$product_id,$serial_number,$fault_desc,$send_date,$expected_return,$status,$result,$freight_fee,$repair_fee,$notes,$id);
        $stmt->execute();
        writeLog($conn, 'rma', 'update', '更新RMA工单');
        echo json_encode(['success'=>true,'message'=>'更新成功']);
    } else {
        $rma_no = 'RMA' . date('Ymd') . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
        $stmt = $conn->prepare("INSERT INTO rma_orders (rma_no,supplier_id,customer_id,product_id,serial_number,fault_desc,send_date,expected_return,status,result,freight_fee,repair_fee,notes,created_by,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("siiissssssddsii", $rma_no,$supplier_id,$customer_id,$product_id,$serial_number,$fault_desc,$send_date,$expected_return,$status,$result,$freight_fee,$repair_fee,$notes,$_SESSION['user_id'],$_SESSION['user_id']);
        $stmt->execute();
        echo json_encode(['success'=>true,'message'=>'创建成功']);
    }
} catch (Exception $e) { echo json_encode(['success'=>false,'message'=>$e->getMessage()]); }
$conn->close();
?>