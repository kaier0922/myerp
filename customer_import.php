<?php
/**
 * =====================================================
 * 文件名：customer_import.php
 * 版本：  1.0
 * 日期：  2026-03-17
 * 功能：  从 CSV 批量导入客户
 * 规则：  公司名称重复则跳过；联系人+电话重复也跳过
 * =====================================================
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_admin();
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json; charset=utf-8');

require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'未登录'], JSON_UNESCAPED_UNICODE); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['csv_file'])) {
    echo json_encode(['success'=>false,'message'=>'请上传文件'], JSON_UNESCAPED_UNICODE); exit;
}

$file = $_FILES['csv_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'文件上传失败'], JSON_UNESCAPED_UNICODE); exit;
}

// 检查扩展名
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    echo json_encode(['success'=>false,'message'=>'仅支持 CSV 格式'], JSON_UNESCAPED_UNICODE); exit;
}

$conn = getDBConnection();

$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    echo json_encode(['success'=>false,'message'=>'无法读取文件'], JSON_UNESCAPED_UNICODE); exit;
}

// 跳过 BOM
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") rewind($handle);

// 读取表头，找列索引
$header = fgetcsv($handle);
if (!$header) {
    echo json_encode(['success'=>false,'message'=>'CSV 格式错误，无法读取表头'], JSON_UNESCAPED_UNICODE); exit;
}

// 清理表头（去空白）
$header = array_map('trim', $header);

// 列名映射（兼容中英文）
$col_map = [
    'company_name' => ['公司名称','公司','company_name','company'],
    'contact_name' => ['联系人','联系人姓名','contact_name','contact'],
    'phone'        => ['电话','手机','联系电话','phone','mobile'],
    'email'        => ['邮箱','email','e-mail'],
    'address'      => ['地址','详细地址','address'],
    'tax_number'   => ['税号','税务登记号','tax_number'],
];

$col_idx = [];
foreach ($col_map as $field => $aliases) {
    foreach ($aliases as $alias) {
        $idx = array_search($alias, $header);
        if ($idx !== false) { $col_idx[$field] = $idx; break; }
    }
}

if (!isset($col_idx['company_name'])) {
    echo json_encode(['success'=>false,'message'=>'找不到「公司名称」列，请使用模板格式'], JSON_UNESCAPED_UNICODE); exit;
}

$imported = 0;
$skipped  = 0;
$errors   = [];
$row_num  = 1;

// 预加载已有公司名，减少查询次数
$existing = [];
$res = $conn->query("SELECT company_name FROM customers WHERE " . tenantWhere() . "");
while ($r = $res->fetch_assoc()) $existing[trim($r['company_name'])] = true;

$uid  = intval($_SESSION['user_id']);
$stmt = $conn->prepare("INSERT INTO customers (company_name, contact_name, phone, email, address, tax_number, user_id, created_at) VALUES (?,?,?,?,?,?,?,NOW())");

while (($row = fgetcsv($handle)) !== false) {
    $row_num++;
    if (count(array_filter($row)) === 0) continue; // 跳过空行

    $company = trim($row[$col_idx['company_name']] ?? '');
    if (!$company) { $errors[] = "第{$row_num}行：公司名称为空，已跳过"; $skipped++; continue; }

    // 跳过模板示例行
    if ($company === '示例公司名称') { $skipped++; continue; }

    // 重复检测
    if (isset($existing[$company])) { $skipped++; continue; }

    $contact = trim($row[$col_idx['contact_name'] ?? -1] ?? '');
    $phone   = trim($row[$col_idx['phone']        ?? -1] ?? '');
    $email   = trim($row[$col_idx['email']        ?? -1] ?? '');
    $address = trim($row[$col_idx['address']      ?? -1] ?? '');
    $tax_no  = trim($row[$col_idx['tax_number']   ?? -1] ?? '');

    $stmt->bind_param('ssssssi', $company, $contact, $phone, $email, $address, $tax_no, $uid);
    if ($stmt->execute()) {
        $imported++;
        $existing[$company] = true;
    } else {
        $errors[] = "第{$row_num}行（{$company}）：" . $stmt->error;
        $skipped++;
    }
}

$stmt->close();
fclose($handle);
$conn->close();

writeLog($conn, 'customers', 'import', '批量导入客户');
echo json_encode([
    'success'  => true,
    'imported' => $imported,
    'skipped'  => $skipped,
    'errors'   => array_slice($errors, 0, 5), // 最多返回5条错误
], JSON_UNESCAPED_UNICODE);
