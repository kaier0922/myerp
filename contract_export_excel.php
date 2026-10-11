<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('请先登录');
}

$conn = getDBConnection();

// ── 读取系统设置 ──
require_once __DIR__ . '/settings_helper.php';
$cfg = getUserSettings($conn, $_SESSION['user_id'] ?? 1);

$id = intval($_GET['id'] ?? 0);

// 获取合同
$sql = "SELECT * FROM sales_contracts WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$contract = $stmt->get_result()->fetch_assoc();

if (!$contract) die("合同不存在");

// 获取明细
$items_sql = "SELECT * FROM sales_contract_items WHERE contract_id = ? ORDER BY item_no";
$stmt2 = $conn->prepare($items_sql);
$stmt2->bind_param("i", $id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

// 设置Excel headers
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=销售合同_{$contract['contract_no']}.xls");
header("Cache-Control: max-age=0");

// 输出UTF-8 BOM
echo "\xEF\xBB\xBF";

// 构建Excel内容
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
<meta charset="UTF-8">
    <link rel="stylesheet" href="common.css">
<style>
th,
.header { font-size: 18pt; font-weight: bold; text-align: center; }
.info { font-size: 10pt; }
</style>
</head>
<body>

<table>
    <tr>
        <td colspan="7" class="header">销售合同</td>
    </tr>
    <tr>
        <td colspan="7" class="info"><strong>合同编号：</strong><?php echo htmlspecialchars($contract['contract_no']); ?></td>
    </tr>
    <tr>
        <td colspan="7">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3" class="info"><strong>甲方（卖方）：</strong><?php echo htmlspecialchars($cfg['company_name']); ?></td>
        <td colspan="4" class="info"><strong>乙方（买方）：</strong><?php echo htmlspecialchars($contract['customer_name']); ?></td>
    </tr>
    <tr>
        <td colspan="3" class="info"><strong>地址：</strong><?php echo htmlspecialchars($cfg['company_address']); ?></td>
        <td colspan="4" class="info"><strong>合同日期：</strong><?php echo $contract['contract_date']; ?></td>
    </tr>
    <tr>
        <td colspan="7" class="info"><strong>联系人：</strong><?php echo htmlspecialchars($cfg['contact_person']); ?>　<strong>电话：</strong><?php echo htmlspecialchars($cfg['contact_phone']); ?></td>
    </tr>
    <tr>
        <td colspan="7">&nbsp;</td>
    </tr>
    <tr>
        <th>序号</th>
        <th>产品/服务名称</th>
        <th>规格说明</th>
        <th>单位</th>
        <th>数量</th>
        <th>单价(¥)</th>
        <th>金额(¥)</th>
    </tr>
    <?php foreach ($items as $item): ?>
    <tr>
        <td style="text-align:center;"><?php echo $item['item_no']; ?></td>
        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
        <td><?php echo htmlspecialchars($item['specification']); ?></td>
        <td style="text-align:center;"><?php echo htmlspecialchars($item['unit']); ?></td>
        <td style="text-align:right;"><?php echo $item['quantity']; ?></td>
        <td style="text-align:right;"><?php echo number_format($item['price'], 2); ?></td>
        <td style="text-align:right;"><?php echo number_format($item['amount'], 2); ?></td>
    </tr>
    <?php endforeach; ?>
    <tr>
        <td colspan="6" style="text-align:right;"><strong>合计金额：</strong></td>
        <td style="text-align:right;"><strong>¥<?php echo number_format($contract['total_amount'], 2); ?></strong></td>
    </tr>
    <tr>
        <td colspan="7">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="7" class="info"><strong>付款条款：</strong><?php echo nl2br(htmlspecialchars($contract['payment_terms'])); ?></td>
    </tr>
    <tr>
        <td colspan="7" class="info"><strong>交付条款：</strong><?php echo nl2br(htmlspecialchars($contract['delivery_terms'])); ?></td>
    </tr>
    <tr>
        <td colspan="7" class="info"><strong>质保条款：</strong><?php echo nl2br(htmlspecialchars($contract['warranty_terms'])); ?></td>
    </tr>
    <tr>
        <td colspan="7">&nbsp;</td>
    </tr>
    <tr>
        <td colspan="3" class="info"><strong>甲方（盖章）：</strong><?php echo htmlspecialchars($cfg['company_name']); ?></td>
        <td colspan="4" class="info"><strong>乙方（盖章）：</strong><?php echo htmlspecialchars($contract['customer_name']); ?></td>
    </tr>
    <tr>
        <td colspan="3" class="info"><strong>日期：</strong><?php echo date('Y年m月d日', strtotime($contract['contract_date'])); ?></td>
        <td colspan="4" class="info"><strong>日期：</strong><?php echo date('Y年m月d日', strtotime($contract['contract_date'])); ?></td>
    </tr>
</table>

</body>
</html>
