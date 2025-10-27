<?php
/**
 * =====================================================
 * 文件名：quote_view.php (A4 紧凑优化版，无“项目名称/工期/模板”)
 * 功能：查看并打印/导出报价单
 * 描述：极度优化版面，压缩表头表尾，为项目内容留出最大空间，适应A4纸打印（约13行内容）
 * =====================================================
 */

session_start();
require_once 'config.php'; // 假设 config.php 包含数据库连接函数 getDBConnection()

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();
$quote_id = $_GET['id'] ?? 0;

// --- 假设的公司信息 ---
$company_info = [
    'name' => '凯尔电脑',
    'slogan' => '专业 IT 解决方案与技术服务提供商',
    'contact_person' => '刘凯',
    'phone' => '0755-28960660',
    'mob' => '13723468235',
    'email' => 'kaier@outlook.com',
    'address' => '广东省深圳市龙华区大浪街道华霆148号',
    'tax_id' => '91440300319401498L',
    'bank_name' => '平安银行龙华支行',
    'bank_account' => '11014767225004',
    'logo_url' => 'assets/images/company_logo.png',
];

// 获取报价单主信息、客户信息和销售人员信息
$quote_sql = "
    SELECT 
        q.*, 
        c.company_name, c.contact_name AS customer_contact, c.phone AS customer_phone, c.email AS customer_email, c.address AS customer_address,
        u.nickname AS salesperson_name, u.phone AS salesperson_phone
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.user_id = u.id
    WHERE q.id = ?
";
$quote_stmt = $conn->prepare($quote_sql);
$quote_stmt->bind_param("i", $quote_id);
$quote_stmt->execute();
$quote_result = $quote_stmt->get_result();
$quote = $quote_result->fetch_assoc();

if (!$quote) {
    echo "报价单不存在或无权限查看。";
    exit;
}

// 获取报价单明细
$items_sql = "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY seq ASC";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $quote_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// 金额转中文
function number_to_chinese_currency($number) {
    return "人民币: " . number_format($number, 2) . " 元";
}

$grand_total = floatval($quote['final_amount']);
$grand_total_chinese = number_to_chinese_currency($grand_total);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<title>报价单 - <?php echo $quote['quote_no']; ?></title>
<style>
body {
    font-family: 'SimSun', 'Arial', sans-serif;
    margin: 0;
    padding: 0;
    color: #333;
    background-color: #f0f0f0;
}
.container {
    width: 210mm;
    min-height: 297mm;
    margin: 10px auto;
    padding: 10mm 15mm;
    background-color: #fff;
    box-shadow: 0 0 5px rgba(0,0,0,0.1);
    position: relative;
    font-size: 10pt;
}
.quote-header {
    text-align: center;
    padding-bottom: 5px;
    margin-bottom: 10px;
}
.quote-header h1 {
    color: #0a5f99;
    font-size: 22pt;
    margin: 0 0 2px 0;
}
.quote-header p {
    font-size: 9pt;
    margin: 1px 0;
}
.info-block {
    border: 1px solid #0a5f99;
    margin-bottom: 10px;
    padding: 8px;
    background-color: #f0f8ff;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 4px 15px;
    font-size: 10pt;
}
.info-grid div span:first-child {
    font-weight: bold;
    color: #333;
    display: inline-block;
    width: 55px;
}
.info-grid-full { grid-column: 1 / -1; }

.item-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 10px;
    font-size: 9pt;
}
.item-table th, .item-table td {
    border: 1px solid #ccc;
    padding: 5px 4px;
    text-align: left;
    word-break: break-all;
}
.item-table th {
    background-color: #0a5f99;
    color: #fff;
    text-align: center;
    font-weight: normal;
}
.item-table .seq-col { width: 3%; text-align: center; }
.item-table .name-col { width: 30%; }
.item-table .spec-col { width: 20%; }
.item-table .unit-col { width: 7%; text-align: center; }
.item-table .qty-col, .item-table .price-col, .item-table .subtotal-col { width: 10%; text-align: right; }

.summary-flex {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}
.summary-box {
    width: 45%;
    border: 2px solid #cc9900;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 8px;
    border-bottom: 1px solid #eee;
}
.summary-total {
    background-color: #ffedd5;
    color: #333;
    font-weight: bold;
}

.terms-signature {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    font-size: 9pt;
}
.terms-box { width: 60%; }
.signature-box { width: 38%; }
.signature-box p {
    border-top: 1px solid #666;
    padding-top: 3px;
    margin-top: 25px;
}
.terms-box h4, .signature-box h4 {
    color: #0a5f99;
    border-left: 2px solid #cc9900;
    padding-left: 5px;
    font-size: 10pt;
}

.quote-footer {
    margin-top: 10px;
    padding-top: 5px;
    text-align: center;
    font-size: 8pt;
    color: #555;
    border-top: 1px dashed #ccc;
}

.action-bar {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 8px 12px;
    background-color: #fff;
    border: 1px solid #ddd;
    border-radius: 5px;
    z-index: 1000;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
}
.action-bar button {
    background-color: #0a5f99;
    color: white;
    border: none;
    padding: 6px 12px;
    cursor: pointer;
    border-radius: 3px;
    font-size: 13px;
    margin: 0 3px;
}
.action-bar button:hover { background-color: #084c7a; }

@media print {
    body { background: none; }
    .container { margin: 0; padding: 8mm 12mm; box-shadow: none; }
    .action-bar { display: none; }
}
</style>
</head>
<body>

<div class="action-bar">
    <button onclick="window.print();">打印/PDF</button>
    <button onclick="window.location.href='quotes.php';">返回列表</button>
</div>

<div class="container">
    <div class="quote-header">
        <h1><?php echo $company_info['name']; ?></h1>
        <p><strong><?php echo $company_info['slogan']; ?></strong></p>
        <p>
            报价单编号: <strong><?php echo $quote['quote_no']; ?></strong> | 
            日期: <?php echo date('Y-m-d', strtotime($quote['quote_date'])); ?> | 
            有效期: <?php echo $quote['valid_days']; ?> 天
        </p>
    </div>

    <div class="info-block">
        <div class="info-grid">
            <div><span>客户:</span> <?php echo htmlspecialchars($quote['company_name']); ?></div>
            <div><span>联系人:</span> <?php echo htmlspecialchars($quote['customer_contact']); ?></div>
            <div><span>电话:</span> <?php echo htmlspecialchars($quote['customer_phone'] ?? 'N/A'); ?></div>
            <div><span>销售:</span> <?php echo htmlspecialchars($quote['salesperson_name'] ?? 'N/A'); ?></div>
            <div class="info-grid-full"><span>客户地址:</span> <?php echo htmlspecialchars($quote['customer_address'] ?? 'N/A'); ?></div>
        </div>
    </div>

    <table class="item-table">
        <thead>
            <tr>
                <th class="seq-col">序号</th>
                <th class="name-col">产品名称/服务项目</th>
                <th class="spec-col">型号/配置说明</th>
                <th>品牌</th>
                <th class="unit-col">单位</th>
                <th class="qty-col">数量</th>
                <th class="price-col">单价 (¥)</th>
                <th class="subtotal-col">金额 (¥)</th>
                <th>备注/质保</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $total_before_discount = 0;
            $row_count = 0;
            if (!empty($items)): 
                foreach ($items as $item): 
                    $total_before_discount += floatval($item['subtotal']);
                    $row_count++;
            ?>
            <tr>
                <td class="seq-col"><?php echo $item['seq']; ?></td>
                <td class="name-col"><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></td>
                <td class="spec-col"><?php echo htmlspecialchars($item['spec'] ?: $item['model'] ?: '---'); ?></td>
                <td><?php echo htmlspecialchars($item['brand'] ?: '通用'); ?></td>
                <td class="unit-col"><?php echo htmlspecialchars($item['unit'] ?: '项'); ?></td>
                <td class="qty-col"><?php echo intval($item['quantity']); ?></td>
                <td class="price-col"><?php echo number_format($item['price'], 2); ?></td>
                <td class="subtotal-col"><?php echo number_format($item['subtotal'], 2); ?></td>
                <td><?php echo htmlspecialchars($item['warranty'] ?: '1年'); ?></td>
            </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <div class="summary-flex">
        <div class="summary-box">
            <div class="summary-row"><span>项目小计</span><span>¥<?php echo number_format($total_before_discount, 2); ?></span></div>
            <div class="summary-row"><span>折扣/优惠</span><span style="color:#d9534f;">- ¥<?php echo number_format($quote['discount'] ?? 0, 2); ?></span></div>
            <div class="summary-row summary-total"><span>最终总价</span><span>¥<?php echo number_format($grand_total, 2); ?></span></div>
        </div>
    </div>

    <div class="terms-signature">
        <div class="terms-box">
            <h4>报价条款</h4>
            <div style="white-space: pre-wrap; border-left: 2px solid #ccc; padding-left: 5px;"><?php echo htmlspecialchars($quote['terms'] ?? '1. 本报价自报价日起'.$quote['valid_days'].'天内有效。2. 所有报价未包含税费，如需开票请另行说明。'); ?></div>
        </div>
        <div class="signature-box">
            <h4>签署确认</h4>
            <p>客户代表: _________________________</p>
            <p>销售代表: <?php echo htmlspecialchars($quote['salesperson_name'] ?? 'N/A'); ?></p>
            <p>日期: _________________________</p>
        </div>
    </div>

    <div class="quote-footer">
        <p>
            <span>联系人: <?php echo $company_info['contact_person']; ?></span> | 
            <span>电话: <?php echo $company_info['phone']; ?></span> | 
            <span>邮箱: <?php echo $company_info['email']; ?></span> | 
            <span>公司地址: <?php echo $company_info['address']; ?></span>
        </p>
        <p>税号/开户行: <?php echo $company_info['tax_id']; ?> / <?php echo $company_info['bank_name']; ?> (账号: <?php echo $company_info['bank_account']; ?>)</p>
    </div>
</div>

</body>
</html>
