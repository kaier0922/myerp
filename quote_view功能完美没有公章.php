<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$quote_id = $_GET['id'] ?? 0;

$company_info = [
    'name' => '报价单',
    'contact_person' => '刘凯',
    'phone' => '13723468235',
    'email' => 'kaier@outlook.com',
    'website' => 'https://www.864g.com',
    'tax_id' => '91440300319401498L',
    'bank_name' => '平安银行龙华支行',
    'bank_account' => '11014767225004'
];

$quote_sql = "
    SELECT q.*, c.company_name, c.contact_name AS customer_contact, 
           c.phone AS customer_phone, c.address AS customer_address
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.id = ?
";
$stmt = $conn->prepare($quote_sql);
$stmt->bind_param("i", $quote_id);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
if (!$quote) die("报价单不存在");

$items_sql = "SELECT * FROM quote_items WHERE quote_id=? ORDER BY seq ASC";
$stmt2 = $conn->prepare($items_sql);
$stmt2->bind_param("i", $quote_id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$grand_total = floatval($quote['final_amount']);
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>报价单 - <?php echo e($quote['quote_no']); ?></title>
<style>
body {
    font-family: "SimSun","Arial",sans-serif;
    background:#f5f5f5; margin:0; padding:0;
}
.container {
    width:210mm; min-height:297mm; margin:10px auto;
    background:#fff; padding:10mm 12mm;
    box-shadow:0 0 5px rgba(0,0,0,0.1);
    font-size:10pt;
}
.header { text-align:center; margin-bottom:10px; }
.header h1 { font-size:22pt; color:#0a5f99; margin:0; }
.header p { margin:3px 0; font-size:9pt; color:#333; }

/* 客户信息布局 */
.client-info {
    display:grid;
    grid-template-columns: 2fr 1fr;
    gap:8px 20px;
    margin-bottom:10px;
}
.client-left div, .client-right div {
    margin-bottom:3px;
    font-size:10pt;
}
.client-left span.label, .client-right span.label {
    font-weight:bold;
    display:inline-block;
    width:80px;
    color:#000;
}
.client-left .address {
    display:block;
    margin-left:80px;
    margin-top:-3px;
    color:#333;
    line-height:1.4;
}
.client-right span.label {
    width:70px;
    letter-spacing: 1px; /* 电 话间距 */
}

/* 表格 */
.table {
    width:100%; border-collapse:collapse; font-size:9pt;
    margin-top:5px;
}
.table th, .table td {
    border:1px solid #ccc; padding:4px 3px;
    text-align:center;
}
.table th {
    background:#0a5f99; color:#fff; white-space:nowrap;
}
.table .price, .table .subtotal { text-align:right; }

/* 汇总与备注 */
.summary {
    display:flex; justify-content:flex-end; margin-top:8px;
}
.summary-box {
    width:45%; border:2px solid #cc9900;
    font-size:10pt;
}
.summary-row {
    display:flex; justify-content:space-between;
    padding:3px 8px; border-bottom:1px solid #eee;
}
.summary-total { background:#ffedd5; font-weight:bold; }

.remark-box {
    margin-top:10px;
    font-size:9pt;
    text-align:left;
}
.remark-box span.label {
    font-weight:bold;
    color:#333;
    margin-right:5px;
}

/* 页脚 */
.footer {
    font-size:8pt; text-align:center; color:#555;
    border-top:1px dashed #ccc; margin-top:10px; padding-top:4px;
}
.footer a { color:#0a5f99; text-decoration:none; }
.footer a:hover { text-decoration:underline; }

/* 打印优化 */
@media print {
    body{background:none;}
    .container{box-shadow:none; margin:0; padding:8mm 10mm;}
    .action{display:none;}
}
/* 去除页眉页脚 */
@page {
    size: A4;
    margin: 10mm;
    @bottom-left { content: none; }
    @bottom-right { content: none; }
    @top-left { content: none; }
    @top-right { content: none; }
}

.action {
    position:fixed; top:20px; right:20px; background:#fff;
    border:1px solid #ccc; padding:8px 10px; border-radius:5px;
    font-size:13px;
}
.action label {
    display:block; margin-bottom:5px;
}
.action button {
    background:#0a5f99; color:#fff; border:none; padding:5px 10px;
    cursor:pointer; border-radius:3px; font-size:12px;
}
.action button:hover{background:#084c7a;}
</style>
</head>
<body>

<div class="action">
    <label><input type="checkbox" id="togglePrice"> 隐藏单价与金额</label>
    <button onclick="window.print()">打印/PDF</button>
    <button onclick="window.location.href='quotes.php'">返回列表</button>
</div>

<div class="container">
    <div class="header">
        <h1><?php echo e($company_info['name']); ?></h1>
        <p>报价单编号: <strong><?php echo e($quote['quote_no']); ?></strong>　
        日期: <?php echo e(date('Y-m-d', strtotime($quote['quote_date']))); ?>　
        有效期: 3 天</p>
    </div>

    <div class="client-info">
        <div class="client-left">
            <div><span class="label">客户名称:</span><?php echo e($quote['company_name']); ?></div>
            <div><span class="label">客户地址:</span><span class="address"><?php echo e($quote['customer_address']); ?></span></div>
        </div>
        <div class="client-right">
            <div><span class="label">联系人:</span><?php echo e($quote['customer_contact']); ?></div>
            <div><span class="label">电 话:</span><?php echo e($quote['customer_phone']); ?></div>
        </div>
    </div>

    <table class="table" id="quoteTable">
        <thead>
            <tr>
                <th class="seq">序号</th>
                <th class="name">产品名称/服务项目</th>
                <th class="spec">品牌型号/配置参数</th>
                <th class="unit">单位</th>
                <th class="qty">数量</th>
                <th class="price">单价(¥)</th>
                <th class="subtotal">金额(¥)</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $total = 0; $row=0;
        foreach ($items as $it):
            $total += $it['subtotal']; $row++;
            $brandSpec = trim(($it['brand'] ? $it['brand'].' ' : '').($it['spec'] ?: $it['model']));
        ?>
        <tr>
            <td class="seq"><?php echo e($it['seq']); ?></td>
            <td class="name"><?php echo e($it['product_name']); ?></td>
            <td class="spec"><?php echo e($brandSpec); ?></td>
            <td class="unit"><?php echo e($it['unit']); ?></td>
            <td class="qty"><?php echo intval($it['quantity']); ?></td>
            <td class="price">¥<?php echo number_format($it['price'], 2); ?></td>
            <td class="subtotal">¥<?php echo number_format($it['subtotal'], 2); ?></td>
        </tr>
        <?php endforeach;
        $blank = max(0, 13 - $row);
        for($i=0;$i<$blank;$i++):
            echo "<tr><td class='seq'>-</td><td></td><td></td><td></td><td></td><td class='price'></td><td class='subtotal'></td></tr>";
        endfor;
        ?>
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-row"><span>项目小计</span><span>¥<?php echo number_format($total,2); ?></span></div>
            <div class="summary-row"><span>折扣/优惠</span><span style="color:#d9534f;">- ¥<?php echo number_format($quote['discount'] ?? 0,2); ?></span></div>
            <div class="summary-row summary-total"><span>最终总价</span><span>¥<?php echo number_format($grand_total,2); ?></span></div>
        </div>
    </div>

    <div class="remark-box">
        <span class="label">备注:</span>
        <?php echo e($quote['terms'] ?? '此报价单所列内容均为初步方案，具体配置与价格以实际沟通为准。'); ?>
    </div>

    <div class="footer">
        <p>联系人: <?php echo e($company_info['contact_person']); ?>　
        电话: <?php echo e($company_info['phone']); ?>　
        邮箱: <?php echo e($company_info['email']); ?>　
        网址: <a href="<?php echo e($company_info['website']); ?>" target="_blank"><?php echo e($company_info['website']); ?></a></p>
        <p>税号: <?php echo e($company_info['tax_id']); ?>　
        开户行: <?php echo e($company_info['bank_name']); ?>　
        账号: <?php echo e($company_info['bank_account']); ?></p>
        <p>凯尔电脑专注于中小型企业IT应用 为企业提供IT选型采购安装部署运维一站式解决方案</p>
        <p style="color:#777;">本报价单由凯尔电脑报价系统生成</p>
    </div>
</div>

<script>
// 切换隐藏价格
document.getElementById('togglePrice').addEventListener('change', function() {
    const show = !this.checked;
    document.querySelectorAll('.price, .subtotal').forEach(el => {
        el.style.display = show ? '' : 'none';
    });
});
</script>

</body>
</html>
