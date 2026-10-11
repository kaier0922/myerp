<?php
/**
 * =====================================================
 * 文件名：customer_export.php
 * 版本：  1.0
 * 日期：  2026-03-17
 * 功能：  导出客户列表为 CSV / 下载模板
 * =====================================================
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$conn = getDBConnection();

// 下载空模板
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="客户导入模板.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo "公司名称,联系人,电话,邮箱,地址,税号\n";
    echo "示例公司名称,张三,13800138000,example@email.com,深圳市南山区,91440300XXXXXXXX\n";
    $conn->close(); exit;
}

// 导出全部（支持搜索过滤）
$search = trim($_GET['search'] ?? '');
$where = '1=1';
if ($search) {
    $s = $conn->real_escape_string($search);
    $where = "(company_name LIKE '%$s%' OR contact_name LIKE '%$s%' OR phone LIKE '%$s%')";
}

$result = $conn->query("
    SELECT id, company_name, contact_name, phone, email, address, tax_number, created_at
    FROM customers WHERE $where AND " . tenantWhere() . " ORDER BY id ASC
");

$filename = '客户列表_' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['ID', '公司名称', '联系人', '电话', '邮箱', '地址', '税号', '创建时间']);

while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['id'],
        $row['company_name'] ?? '',
        $row['contact_name']  ?? '',
        $row['phone']         ?? '',
        $row['email']         ?? '',
        $row['address']       ?? '',
        $row['tax_number']    ?? '',
        $row['created_at']    ?? '',
    ]);
}
fclose($out);
$conn->close();
