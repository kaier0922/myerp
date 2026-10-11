<?php
/**
 * =====================================================
 * 文件名：export_word.php
 * 功能：导出报价单为Word格式
 * 说明：使用PHPWord库生成DOCX文档
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

// 设置header，告诉浏览器这是Word文档
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="报价单_' . $quote['quote_no'] . '.docx"');
header('Cache-Control: max-age=0');

// 检查是否安装了PHPWord
if (!class_exists('PhpOffice\PhpWord\PhpWord')) {
    // 如果没有PHPWord，使用HTML转Word的简单方法
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment; filename="报价单_' . $quote['quote_no'] . '.doc"');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">';
    echo '<head><meta charset="utf-8"><title>报价单</title></head>';
    echo '<body>';
    
    // 标题
    echo '<h1 style="text-align:center; color:#1f6dad;">' . htmlspecialchars($company_info['name']) . '</h1>';
    echo '<p style="text-align:center;">报价单编号: <strong>' . htmlspecialchars($quote['quote_no']) . '</strong>　';
    echo '日期: ' . htmlspecialchars($quote_date) . '　有效期: 3 天</p>';
    
    // 客户信息
    echo '<h3>客户信息</h3>';
    echo '<table border="0" cellpadding="5" style="width:100%; margin-bottom:20px;">';
    echo '<tr><td><strong>客户名称:</strong> ' . htmlspecialchars($quote['company_name']) . '</td>';
    echo '<td><strong>联系人:</strong> ' . htmlspecialchars($quote['contact_name'] ?? '') . '</td></tr>';
    echo '<tr><td><strong>客户地址:</strong> ' . htmlspecialchars($quote['customer_address'] ?? '') . '</td>';
    echo '<td><strong>电话:</strong> ' . htmlspecialchars($quote['phone'] ?? '') . '</td></tr>';
    echo '</table>';
    
    // 明细表格
    echo '<h3>报价明细</h3>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="width:100%; border-collapse:collapse;">';
    echo '<thead style="background:#1f6dad; color:#fff;">';
    echo '<tr>';
    echo '<th>序号</th>';
    echo '<th>配件类型</th>';
    echo '<th>产品名称</th>';
    echo '<th>品牌型号</th>';
    echo '<th>单位</th>';
    echo '<th>数量</th>';
    echo '<th>单价(¥)</th>';
    echo '<th>金额(¥)</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $total = 0;
    foreach ($items as $item) {
        $total += $item['subtotal'];
        $brandSpec = trim(($item['brand'] ? $item['brand'].' ' : '') . ($item['spec'] ?: $item['model']));
        
        echo '<tr>';
        echo '<td style="text-align:center;">' . htmlspecialchars($item['seq']) . '</td>';
        echo '<td>' . htmlspecialchars($item['category'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($item['product_name']) . '</td>';
        echo '<td>' . htmlspecialchars($brandSpec) . '</td>';
        echo '<td style="text-align:center;">' . htmlspecialchars($item['unit']) . '</td>';
        echo '<td style="text-align:center;">' . intval($item['quantity']) . '</td>';
        echo '<td style="text-align:right;">¥' . number_format($item['price'], 2) . '</td>';
        echo '<td style="text-align:right;">¥' . number_format($item['subtotal'], 2) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
    // 汇总
    $discount = floatval($quote['discount'] ?? 0);
    $final_amount = floatval($quote['final_amount']);
    
    echo '<div style="margin-top:20px; text-align:right;">';
    echo '<p><strong>项目小计:</strong> ¥' . number_format($total, 2) . '</p>';
    echo '<p><strong>折扣/优惠:</strong> <span style="color:#d9534f;">- ¥' . number_format($discount, 2) . '</span></p>';
    echo '<p style="font-size:18px;"><strong>最终总价:</strong> <span style="color:#1f6dad;">¥' . number_format($final_amount, 2) . '</span></p>';
    echo '</div>';
    
    // 备注
    if (!empty($quote['terms'])) {
        echo '<h3>备注</h3>';
        echo '<p>' . nl2br(htmlspecialchars($quote['terms'])) . '</p>';
    }
    
    // 页脚
    echo '<hr>';
    echo '<p style="font-size:12px; color:#666;">';
    echo '<strong>联系人:</strong> ' . htmlspecialchars($company_info['contact_person']) . '　';
    echo '<strong>电话:</strong> ' . htmlspecialchars($company_info['phone']) . '　';
    echo '<strong>邮箱:</strong> ' . htmlspecialchars($company_info['email']) . '<br>';
    echo '<strong>税号:</strong> ' . htmlspecialchars($company_info['tax_id']) . '　';
    echo '<strong>开户行:</strong> ' . htmlspecialchars($company_info['bank_name']) . '　';
    echo '<strong>账号:</strong> ' . htmlspecialchars($company_info['bank_account']);
    echo '</p>';
    
    echo '</body></html>';
    exit;
}

// 如果有PHPWord，使用PHPWord生成
// 这里可以添加PHPWord的代码
?>
