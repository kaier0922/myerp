<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$id = intval($_POST['id'] ?? 0);
if (!$id) die('参数错误');

$customer_id   = intval($_POST['customer_id'] ?? 0);
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_address = trim($_POST['customer_address'] ?? '');
$customer_contact = trim($_POST['contact_person']   ?? '');
$customer_phone   = trim($_POST['contact_phone']    ?? '');
$own_name    = trim($_POST['own_name']    ?? '');
$own_address = trim($_POST['own_address'] ?? '');
$own_contact = trim($_POST['own_contact'] ?? '');
$own_phone   = trim($_POST['own_phone']   ?? '');
$contract_date = $_POST['contract_date'] ?? date('Y-m-d');
$status        = $_POST['status']        ?? 'draft';
$total_amount  = floatval($_POST['total_amount'] ?? 0);
$payment_terms = trim($_POST['payment_terms']    ?? '');
$delivery_terms= trim($_POST['delivery_terms']   ?? '');
$warranty_terms= trim($_POST['warranty_terms']   ?? '');
$breach_terms  = trim($_POST['breach_terms']     ?? '');
$dispute_terms = trim($_POST['dispute_terms']    ?? '');
$service_scope = trim($_POST['service_scope']    ?? '');
$work_start    = $_POST['work_start'] ?: null;
$work_end      = $_POST['work_end']   ?: null;
$acceptance_terms = trim($_POST['acceptance_terms'] ?? '');
$sla_terms     = trim($_POST['sla_terms']    ?? '');
$service_mode  = trim($_POST['service_mode'] ?? '');
$notes         = trim($_POST['notes']        ?? '');

$conn->begin_transaction();
try {
    $upd = $conn->prepare("UPDATE sales_contracts SET
        customer_id=?,customer_name=?,customer_address=?,customer_contact=?,customer_phone=?,
        own_name=?,own_address=?,own_contact=?,own_phone=?,
        contract_date=?,total_amount=?,
        payment_terms=?,delivery_terms=?,warranty_terms=?,breach_terms=?,dispute_terms=?,
        service_scope=?,work_start=?,work_end=?,acceptance_terms=?,
        sla_terms=?,service_mode=?,status=?,notes=?
        WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
    $upd->bind_param("isssssssssdsssssssssssssi",
        $customer_id,$customer_name,$customer_address,$customer_contact,$customer_phone,
        $own_name,$own_address,$own_contact,$own_phone,
        $contract_date,$total_amount,
        $payment_terms,$delivery_terms,$warranty_terms,$breach_terms,$dispute_terms,
        $service_scope,$work_start,$work_end,$acceptance_terms,
        $sla_terms,$service_mode,$status,$notes,$id);
    $upd->execute();

    // 删除明细前先确认合同归属当前用户
    $chk = $conn->query("SELECT id FROM sales_contracts WHERE id=$id AND user_id=" . intval($_SESSION['user_id']));
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success'=>false,'message'=>'无权限操作此合同']); exit;
    }
    $conn->query("DELETE FROM sales_contract_items WHERE contract_id=$id");
    $iins = $conn->prepare("INSERT INTO sales_contract_items
        (contract_id,item_no,product_name,specification,unit,quantity,price,amount)
        VALUES (?,?,?,?,?,?,?,?)");
    $items   = $_POST['items'] ?? [];
    $uid     = intval($_SESSION['user_id']);
    $no      = 1;
    $new_cnt = 0;
    foreach ($items as $item) {
        $custom = trim($item['custom_name'] ?? '');
        $name   = trim($item['product_name'] ?? '') ?: $custom;
        if (!$name) continue;
        // 自定义产品：检查是否已存在，不存在则插入产品库
        if ($custom !== '') {
            $chk = $conn->prepare("SELECT id FROM products WHERE name=? AND user_id=? LIMIT 1");
            $chk->bind_param("si", $custom, $uid); $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                $sku = 'CUST' . date('YmdHis') . rand(10,99);
                $conn->prepare("INSERT INTO products (sku,name,unit,cost_price,default_price,user_id,is_active,created_at)
                    VALUES (?,?,?,0,0,?,1,NOW())")
                    ->execute([$sku, $custom, trim($item['unit']??'项'), $uid]);
                $new_cnt++;
            }
            $chk->close();
        }
        $spec = trim($item['specification'] ?? '');
        $unit = trim($item['unit'] ?? '项');
        $qty  = floatval($item['quantity'] ?? 1);
        $prc  = floatval($item['price']    ?? 0);
        $amt  = floatval($item['amount']   ?? $qty*$prc);
        $iins->bind_param("iisssddd",$id,$no,$name,$spec,$unit,$qty,$prc,$amt);
        $iins->execute();
        $no++;
    }
    $conn->commit();
    writeLog($conn, 'contracts', 'update', '更新合同');
    header('Location: contract_view.php?id=' . $id . '&updated=1');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die('更新失败：' . $e->getMessage());
}
