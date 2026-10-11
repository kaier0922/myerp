<?php
/**
 * =====================================================
 * 文件名：export_excel.php
 * 功能：导出报价单为Excel格式
 * 说明：使用简单的HTML表格方式生成Excel
 * =====================================================
 */

session_start();
require_once 'config.php';

// 检查登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

// 获取报价单ID
$quote_id = $_GET['id'] ?? 0;

if (!$quote_id) {
    die('无效的报价单ID');
}

$conn = getDBConnection();

// ── 读取系统设置 ──
require_once __DIR__ . '/settings_helper.php';
$cfg = getUserSettings($conn, $_SESSION['user_id'] ?? 1);


// 获取报价单信息
$quote_sql = "
    SELECT q.*, COALESCE(c.short_name, c.company_name) AS company_name, c.address AS customer_address, c.contact_name, c.phone
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.id = ? AND q.user_id = ?
";
$stmt = $conn->prepare($quote_sql);
$stmt->bind_param("ii", $quote_id, $_SESSION['user_id']);
$stmt->execute();
$quote = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$quote) {
    die('报价单不存在或无权访问');
}

// 获取报价单明细
$items_sql = "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY seq ASC";
$stmt = $conn->prepare($items_sql);
$stmt->bind_param("i", $quote_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

// 格式化日期
$quote_date = $quote['quote_date'];
if (preg_match('/^\d{8}$/', $quote_date)) {
    $quote_date = substr($quote_date, 0, 4) . '-' . substr($quote_date, 4, 2) . '-' . substr($quote_date, 6, 2);
}

// 公司信息
$company_info = [
    'name' => '报价单',
    'contact_person' => '刘凯',
    'phone' => $cfg['contact_phone'],
    'email' => $cfg['contact_email'],
    'website' => $cfg['company_website'],
    'tax_id' => $cfg['tax_id'],
    'bank_name' => $cfg['bank_name'],
    'bank_account' => $cfg['bank_account']
];

// 设置header，告诉浏览器这是Excel文档
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="报价单_' . $quote['quote_no'] . '.xls"');
header('Cache-Control: max-age=0');

// 输出HTML格式的Excel
echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
echo '    <link rel="stylesheet" href="common.css">
<style>';
echo '';
echo 'th,';
echo '';
echo '.title { font-size: 24px; font-weight: bold; text-align: center; color: #1f6dad; }';
echo '.info { font-size: 12px; text-align: center; margin-bottom: 20px; }';
echo '.section-title { font-size: 14px; font-weight: bold; margin: 20px 0 10px 0; background: #f0f0f0; padding: 5px; }';
echo '.text-right { text-align: right; }';
echo '.text-left { text-align: left; }';
echo '.summary { font-size: 14px; font-weight: bold; margin-top: 20px; }';
echo '.footer { font-size: 11px; color: #666; margin-top: 30px; border-top: 1px solid #ccc; padding-top: 10px; }';
echo '</style>';
echo '</head>';
echo '<body>';

// 标题
echo '<div class="title">' . htmlspecialchars($company_info['name']) . '</div>';
echo '<div class="info">';
echo '报价单编号: <strong>' . htmlspecialchars($quote['quote_no']) . '</strong>　';
echo '日期: ' . htmlspecialchars($quote_date) . '　';
echo '有效期: 3 天';
echo '</div>';

// 客户信息
echo '<div class="section-title">客户信息</div>';
echo '<table style="margin-bottom: 20px;">';
echo '<tr>';
echo '<td class="text-left" style="width: 15%; font-weight: bold;">客户名称</td>';
echo '<td class="text-left" style="width: 35%;">' . htmlspecialchars($quote['company_name']) . '</td>';
echo '<td class="text-left" style="width: 15%; font-weight: bold;">联系人</td>';
echo '<td class="text-left" style="width: 35%;">' . htmlspecialchars($quote['contact_name'] ?? '') . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td class="text-left" style="font-weight: bold;">客户地址</td>';
echo '<td class="text-left">' . htmlspecialchars($quote['customer_address'] ?? '') . '</td>';
echo '<td class="text-left" style="font-weight: bold;">电话</td>';
echo '<td class="text-left">' . htmlspecialchars($quote['phone'] ?? '') . '</td>';
echo '</tr>';
echo '</table>';

// 明细表格
echo '<div class="section-title">报价明细</div>';
echo '<table>';
echo '<thead>';
echo '<tr>';
echo '<th style="width: 5%;">序号</th>';
echo '<th style="width: 10%;">配件类型</th>';
echo '<th style="width: 20%;">产品名称</th>';
echo '<th style="width: 25%;">品牌型号/规格参数</th>';
echo '<th style="width: 8%;">单位</th>';
echo '<th style="width: 8%;">数量</th>';
echo '<th style="width: 12%;">单价(¥)</th>';
echo '<th style="width: 12%;">金额(¥)</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$total = 0;
foreach ($items as $item) {
    $total += $item['subtotal'];
    $brandSpec = trim(($item['brand'] ? $item['brand'].' ' : '') . ($item['spec'] ?: $item['model']));
    
    echo '<tr>';
    echo '<td>' . htmlspecialchars($item['seq']) . '</td>';
    echo '<td>' . htmlspecialchars($item['category'] ?? '') . '</td>';
    echo '<td class="text-left">' . htmlspecialchars($item['product_name']) . '</td>';
    echo '<td class="text-left">' . htmlspecialchars($brandSpec) . '</td>';
    echo '<td>' . htmlspecialchars($item['unit']) . '</td>';
    echo '<td>' . intval($item['quantity']) . '</td>';
    echo '<td class="text-right">' . number_format($item['price'], 2) . '</td>';
    echo '<td class="text-right">' . number_format($item['subtotal'], 2) . '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';

// 汇总
$discount = floatval($quote['discount'] ?? 0);
$final_amount = floatval($quote['final_amount']);

echo '<div class="summary">';
echo '<table style="width: 50%; margin-left: auto; margin-top: 20px;">';
echo '<tr>';
echo '<td class="text-left" style="font-weight: bold;">项目小计</td>';
echo '<td class="text-right">¥' . number_format($total, 2) . '</td>';
echo '</tr>';
echo '<tr>';
echo '<td class="text-left" style="font-weight: bold;">折扣/优惠</td>';
echo '<td class="text-right" style="color: #d9534f;">- ¥' . number_format($discount, 2) . '</td>';
echo '</tr>';
echo '<tr style="background: #ffedd5;">';
echo '<td class="text-left" style="font-weight: bold; font-size: 16px;">最终总价</td>';
echo '<td class="text-right" style="font-weight: bold; font-size: 16px; color: #1f6dad;">¥' . number_format($final_amount, 2) . '</td>';
echo '</tr>';
echo '</table>';
echo '</div>';

// 备注
if (!empty($quote['terms'])) {
    echo '<div class="section-title">备注</div>';
    echo '<div style="text-align: left; padding: 10px; border: 1px solid #ccc; background: #fafafa;">';
    echo nl2br(htmlspecialchars($quote['terms']));
    echo '</div>';
}

// 页脚
echo '<div class="footer">';
echo '<strong>联系人:</strong> ' . htmlspecialchars($company_info['contact_person']) . '　';
echo '<strong>电话:</strong> ' . htmlspecialchars($company_info['phone']) . '　';
echo '<strong>邮箱:</strong> ' . htmlspecialchars($company_info['email']) . '　';
echo '<strong>网址:</strong> ' . htmlspecialchars($company_info['website']) . '<br>';
echo '<strong>税号:</strong> ' . htmlspecialchars($company_info['tax_id']) . '　';
echo '<strong>开户行:</strong> ' . htmlspecialchars($company_info['bank_name']) . '　';
echo '<strong>账号:</strong> ' . htmlspecialchars($company_info['bank_account']);
echo '<br><br>';
echo htmlspecialchars($cfg['quote_footer']??'');
echo '</div>';

echo '</body>';
echo '</html>';
?>
