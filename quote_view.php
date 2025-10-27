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
<!-- 引入公章选择器样式 -->
<link rel="stylesheet" href="seal-picker.css">
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
    position: relative; /* 重要：为公章定位 */
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
    letter-spacing: 1px;
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
    .action{display:none !important;}
    .seal-controls{display:none !important;}
    .document-seal{cursor:default !important;}
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
    border:1px solid #ccc; padding:12px 15px; border-radius:8px;
    font-size:13px; box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    z-index: 1000;
}
.action label {
    display:block; margin-bottom:8px;
    cursor: pointer;
}
.action button {
    background:#0a5f99; color:#fff; border:none; padding:8px 16px;
    cursor:pointer; border-radius:5px; font-size:13px;
    margin-right: 5px;
    margin-bottom: 5px;
    transition: all 0.2s;
}
.action button:hover{
    background:#084c7a;
    transform: translateY(-2px);
}
.action button.btn-seal {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
.action button.btn-seal:hover {
    background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
}
.action button.btn-success {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}
.action button.btn-success:hover {
    background: linear-gradient(135deg, #3da564 0%, #2f8a5a 100%);
}
.action hr {
    border: none;
    border-top: 1px solid #e2e8f0;
    margin: 10px 0;
}
</style>
</head>
<body>

<!-- 操作面板 -->
<div class="action">
    <label>
        <input type="checkbox" id="togglePrice"> 隐藏单价与金额
    </label>
    <hr>
    <div style="margin-bottom: 8px; color: #667eea; font-weight: 600;">🖊️ 公章操作</div>
    <!-- 公章选择器容器 -->
    <div class="seal-picker-container" style="margin-bottom: 8px;"></div>
    <button class="btn-success" onclick="saveSealConfig()">💾 保存公章位置</button>
    <hr>
    <button onclick="window.print()">🖨️ 打印/PDF</button>
    <button onclick="window.location.href='quotes.php'">← 返回列表</button>
</div>

<!-- 报价单内容 - 添加 print-area class 用于公章定位 -->
<div class="container print-area" id="quotationContent" style="position: relative; overflow: visible;">
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

<!-- 引入公章选择器脚本 -->
<script src="seal-picker.js"></script>
<script>
// 切换隐藏价格
document.getElementById('togglePrice').addEventListener('change', function() {
    const show = !this.checked;
    document.querySelectorAll('.price, .subtotal').forEach(el => {
        el.style.display = show ? '' : 'none';
    });
});

// 保存公章配置
function saveSealConfig() {
    if (!window.sealPicker || !window.sealPicker.sealElement) {
        alert('❌ 请先选择并放置公章！');
        return;
    }
    
    const sealConfig = window.sealPicker.getSealPosition();
    if (!sealConfig) {
        alert('❌ 获取公章位置失败！');
        return;
    }
    
    // 保存到数据库
    fetch('ajax_seals.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'save_document_seal',
            document_type: 'quotation',
            document_id: <?php echo $quote_id; ?>,
            seal_id: sealConfig.seal_id,
            position_x: Math.round(sealConfig.x),
            position_y: Math.round(sealConfig.y),
            seal_size: sealConfig.size
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ 公章位置已保存！');
        } else {
            alert('❌ 保存失败：' + (data.error || '未知错误'));
        }
    })
    .catch(err => {
        console.error('保存失败:', err);
        alert('❌ 保存失败，请检查网络连接');
    });
}

// 页面加载时尝试恢复已保存的公章
window.addEventListener('load', function() {
    // 等待 sealPicker 初始化
    setTimeout(function() {
        fetch('ajax_seals.php?action=get_document_seal&document_type=quotation&document_id=<?php echo $quote_id; ?>')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.config && window.sealPicker) {
                // 自动恢复公章
                const config = data.config;
                
                // 模拟选择公章
                const seal = {
                    id: config.seal_id,
                    file_path: config.file_path,
                    seal_name: config.seal_name
                };
                
                window.sealPicker.currentSeal = seal;
                window.sealPicker.addSealToDocument();
                
                // 设置位置和大小
                if (window.sealPicker.sealElement) {
                    window.sealPicker.sealElement.style.left = config.position_x + 'px';
                    window.sealPicker.sealElement.style.top = config.position_y + 'px';
                    window.sealPicker.sealElement.style.width = config.seal_size + 'px';
                    window.sealPicker.sealElement.style.height = config.seal_size + 'px';
                    window.sealPicker.sealElement.style.right = 'auto';
                    window.sealPicker.sealElement.style.bottom = 'auto';
                    
                    console.log('✅ 已恢复公章位置');
                }
            }
        })
        .catch(err => console.log('未找到已保存的公章配置'));
    }, 500);
});
</script>

</body>
</html>