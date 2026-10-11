<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();

$contract_type = $_POST['contract_type'] ?? 'sales';
$contract_no   = trim($_POST['contract_no']   ?? '');
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

// 手动输入客户时自动查找或创建
if (empty($customer_id) && !empty($customer_name)) {
    $chk = $conn->prepare("SELECT id FROM customers WHERE company_name=? LIMIT 1");
    $chk->bind_param("s", $customer_name);
    $chk->execute();
    $chk_res = $chk->get_result()->fetch_assoc();
    $chk->close();
    if ($chk_res) {
        $customer_id = $chk_res['id'];
    } else {
        $cust_uid = (int)$_SESSION['user_id'];
        $ins = $conn->prepare("INSERT INTO customers (company_name, contact_name, user_id, created_at) VALUES (?,'',?,NOW())");
        $ins->bind_param("si", $customer_name, $cust_uid);
        $ins->execute();
        $customer_id = $conn->insert_id;
        $ins->close();
    }
}

if (!$contract_no || (!$customer_id && !$customer_name)) {
    die('参数错误');
}

$conn->begin_transaction();
try {
    $ins = $conn->prepare("INSERT INTO sales_contracts
        (contract_no, contract_type, customer_id, customer_name,
         customer_address, customer_contact, customer_phone,
         own_name, own_address, own_contact, own_phone, contract_date,
         total_amount, payment_terms, delivery_terms, warranty_terms,
         breach_terms, dispute_terms,
         service_scope, work_start, work_end, acceptance_terms, sla_terms,
         service_mode, status, notes, created_by, user_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $uid = (int)$_SESSION['user_id'];
    $ins->bind_param("ssisssssssssdsssssssssssssii",
        $contract_no, $contract_type, $customer_id, $customer_name,
        $customer_address, $customer_contact, $customer_phone,
        $own_name, $own_address, $own_contact, $own_phone, $contract_date,
        $total_amount, $payment_terms, $delivery_terms, $warranty_terms,
        $breach_terms, $dispute_terms,
        $service_scope, $work_start, $work_end, $acceptance_terms, $sla_terms,
        $service_mode, $status, $notes, $uid, $uid);
    $ins->execute();
    $con_id = $conn->insert_id;

    $items   = $_POST['items'] ?? [];
    $uid     = intval($_SESSION['user_id']);
    $iins    = $conn->prepare("INSERT INTO sales_contract_items
        (contract_id,item_no,product_name,specification,unit,quantity,price,amount)
        VALUES (?,?,?,?,?,?,?,?)");
    $no = 1;
    foreach ($items as $item) {
        $custom = trim($item['custom_name'] ?? '');
        $name   = trim($item['product_name'] ?? '') ?: $custom;
        if (!$name) continue;
        if ($custom !== '') {
            $chk = $conn->prepare("SELECT id FROM products WHERE name=? AND user_id=? LIMIT 1");
            $chk->bind_param("si", $custom, $uid); $chk->execute();
            if (!$chk->get_result()->fetch_assoc()) {
                $sku = 'CUST' . date('YmdHis') . rand(10,99);
                $conn->prepare("INSERT INTO products (sku,name,unit,cost_price,default_price,user_id,is_active,created_at)
                    VALUES (?,?,?,0,0,?,1,NOW())")
                    ->execute([$sku, $custom, trim($item['unit']??'项'), $uid]);
            }
            $chk->close();
        }
        $spec = trim($item['specification'] ?? '');
        $unit = trim($item['unit'] ?? '项');
        $qty  = floatval($item['quantity'] ?? 1);
        $prc  = floatval($item['price']    ?? 0);
        $amt  = floatval($item['amount']   ?? $qty*$prc);
        $iins->bind_param("iisssddd", $con_id,$no,$name,$spec,$unit,$qty,$prc,$amt);
        $iins->execute();
        $no++;
    }
    $conn->commit();
    writeLog($conn, 'contracts', 'create', '新建合同');
    header('Location: contract_view.php?id=' . $con_id . '&created=1');
    exit;
} catch (Exception $e) {
    $conn->rollback();
    die('保存失败：' . $e->getMessage());
}
