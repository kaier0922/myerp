<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('请先登录');
}

$conn = getDBConnection();
require_once __DIR__ . '/settings_helper.php';
$cfg = getUserSettings($conn, $_SESSION['user_id']);
$id = intval($_GET['id'] ?? 0);

// 获取合同（加 user_id 过滤）
$sql = "SELECT * FROM sales_contracts WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) die("合同不存在或无权访问");

// 获取明细
$items_sql = "SELECT * FROM sales_contract_items WHERE contract_id = ? ORDER BY item_no";
$stmt2 = $conn->prepare($items_sql);
$stmt2->bind_param("i", $id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

$company_name = $cfg['company_name'] ?? '深圳市凯尔耐特科技有限公司';
$company_address = $cfg['company_address'] ?? '';
$contact_person = $cfg['contact_person'] ?? '';
$contact_phone = $cfg['contact_phone'] ?? '';

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8">    <link rel="stylesheet" href="common.css">
<style>
.header{text-align:center;margin-bottom:20px;}
.header h1{font-size:24pt;color:#000;margin:10px 0;}
th,
.section{margin:15px 0;}
.section-title{font-weight:bold;font-size:11pt;margin-bottom:8px;}
.section-content{line-height:1.6;font-size:10.5pt;text-indent:2em;}
.signatures{margin-top:40px;display:flex;justify-content:space-between;}
</style></head><body>
<div class="header"><h1>销售合同</h1><p>合同编号：' . htmlspecialchars($contract['contract_no']) . '</p></div>
<div class="info">
<p><strong>甲方（卖方）：</strong>' . htmlspecialchars($company_name) . '</p>
<p><strong>地址：</strong>' . htmlspecialchars($company_address) . '</p>
<p><strong>联系人：</strong>' . htmlspecialchars($contact_person) . '　<strong>电话：</strong>' . htmlspecialchars($contact_phone) . '</p>
<p><strong>乙方（买方）：</strong>' . htmlspecialchars($contract['customer_name']) . '</p>
<p><strong>合同日期：</strong>' . $contract['contract_date'] . '</p>
</div>
<div class="section"><div class="section-title">第一条 产品及服务清单</div>
<table><tr><th>序号</th><th>产品/服务名称</th><th>规格说明</th><th>单位</th><th>数量</th><th>单价(¥)</th><th>金额(¥)</th></tr>';

foreach ($items as $item) {
    $html .= '<tr><td>' . $item['item_no'] . '</td><td>' . htmlspecialchars($item['product_name']) . '</td><td>' . htmlspecialchars($item['specification']) . '</td><td>' . htmlspecialchars($item['unit']) . '</td><td>' . $item['quantity'] . '</td><td>' . number_format($item['price'],2) . '</td><td>' . number_format($item['amount'],2) . '</td></tr>';
}

$html .= '<tr><td colspan="6" style="text-align:right;"><strong>合计金额：</strong></td><td><strong>¥' . number_format($contract['total_amount'],2) . '</strong></td></tr></table></div>
<div class="section"><div class="section-title">第二条 付款条款</div><div class="section-content">' . nl2br(htmlspecialchars($contract['payment_terms'])) . '</div></div>
<div class="section"><div class="section-title">第三条 交付条款</div><div class="section-content">' . nl2br(htmlspecialchars($contract['delivery_terms'])) . '</div></div>
<div class="section"><div class="section-title">第四条 质保条款</div><div class="section-content">' . nl2br(htmlspecialchars($contract['warranty_terms'])) . '</div></div>
<div class="signatures">
<div><p><strong>甲方（盖章）：</strong>' . htmlspecialchars($company_name) . '</p><p><strong>日期：</strong>' . date('Y年m月d日', strtotime($contract['contract_date'])) . '</p></div>
<div><p><strong>乙方（盖章）：</strong>' . htmlspecialchars($contract['customer_name']) . '</p><p><strong>日期：</strong>' . date('Y年m月d日', strtotime($contract['contract_date'])) . '</p></div>
</div></body></html>';

header("Content-Type: application/vnd.ms-word");
header("Content-Disposition: attachment; filename=销售合同_{$contract['contract_no']}.doc");
header("Cache-Control: max-age=0");
echo "\xEF\xBB\xBF";
echo $html;
?>
