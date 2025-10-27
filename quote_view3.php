<?php
/**
 * =====================================================
 * 文件名：quote_view.php (A4 紧凑优化版)
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

// --- 假设的公司信息 (请根据您的实际情况修改) ---
$company_info = [
    'name' => '深圳市巨航科技有限公司',
    'slogan' => '专业 IT 解决方案与技术服务提供商',
    'contact_person' => '张三 (销售经理)',
    'phone' => '0755-12345678',
    'fax' => '0755-12345679',
    'email' => 'sales@juhang.com',
    'address' => '广东省深圳市南山区高新科技园XXX号',
    'tax_id' => '91440300XXXXXXXXX',
    'bank_name' => '中国银行深圳支行',
    'bank_account' => '1234 5678 9012 3456',
    'logo_url' => 'assets/images/company_logo.png', 
];
// ----------------------------------------------------


// 1. 获取报价单主信息、客户信息和销售人员信息
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

// 2. 获取报价单明细
$items_sql = "SELECT * FROM quote_items WHERE quote_id = ? ORDER BY seq ASC";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $quote_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);

// 3. 辅助函数：将数字金额转为中文大写 (为简洁省略了完整的数字转大写函数，请根据实际项目需要补充或使用原有的)
function number_to_chinese_currency($number) {
    // 仅返回一个占位符或简短中文表示，因为代码过长，此处为演示目的
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
        /* ------------------------------------- */
        /* 基础样式 - 屏幕显示 & 打印通用 */
        /* ------------------------------------- */
        body {
            font-family: 'SimSun', 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #f0f0f0;
        }
        .container {
            width: 210mm; /* A4 宽度 */
            min-height: 297mm; /* A4 高度 */
            margin: 10px auto; /* 减少顶部外边距 */
            padding: 10mm 15mm; /* 极小内边距 */
            background-color: #fff;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
            position: relative;
            font-size: 10pt; /* 整体字体缩小 */
        }

        /* 头部信息 - 极度压缩 */
        .quote-header {
            text-align: center;
            padding-bottom: 5px;
            margin-bottom: 10px;
        }
        .quote-header h1 {
            color: #0a5f99;
            font-size: 22pt; /* 缩小标题 */
            margin: 0 0 2px 0;
        }
        .quote-header p {
            font-size: 9pt;
            margin: 1px 0;
        }

        /* 客户和报价信息块 - 压缩 */
        .info-block {
            border: 1px solid #0a5f99;
            margin-bottom: 10px;
            padding: 8px;
            background-color: #f0f8ff; /* 浅蓝色背景 */
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr; /* 增加列数 */
            gap: 4px 15px;
            font-size: 10pt;
        }
        .info-grid div span:first-child {
            font-weight: bold;
            color: #333;
            display: inline-block;
            width: 55px; /* 减少标签宽度 */
        }
        .info-grid-full {
            grid-column: 1 / -1;
        }

        /* 产品明细表格 - 核心内容区域 */
        .item-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            font-size: 9pt; /* 表格内容继续缩小 */
        }
        .item-table th, .item-table td {
            border: 1px solid #ccc;
            padding: 5px 4px; /* 缩小内边距 */
            text-align: left;
            word-break: break-all; /* 允许长文本换行 */
        }
        .item-table th {
            background-color: #0a5f99;
            color: #fff;
            text-align: center;
            font-weight: normal;
            font-size: 9pt;
        }
        /* 调整列宽，为描述留出空间 */
        .item-table .seq-col { width: 3%; text-align: center; }
        .item-table .name-col { width: 30%; }
        .item-table .spec-col { width: 20%; }
        .item-table .unit-col { width: 7%; text-align: center; }
        .item-table .qty-col, .item-table .price-col, .item-table .subtotal-col { width: 10%; text-align: right; }
        .item-table td:nth-child(5), .item-table td:nth-child(6), .item-table td:nth-child(7), .item-table td:nth-child(8), .item-table td:nth-child(9) {
             text-align: center; /* 集中对齐中间字段 */
        }


        /* 底部汇总 - 紧凑 */
        .summary-flex {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
        .summary-box {
            width: 45%; /* 缩小汇总框宽度 */
            border: 2px solid #cc9900; /* 加粗边框突出总价 */
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 8px;
            border-bottom: 1px solid #eee;
            font-size: 10pt;
        }
        .summary-total {
            background-color: #ffedd5; /* 浅金色背景 */
            color: #333;
            font-size: 12pt;
            font-weight: bold;
        }

        /* 条款和签名 - 压缩 */
        .terms-signature {
            display: flex;
            justify-content: space-between;
            margin-top: 15px; /* 顶部间距减小 */
            font-size: 9pt;
            line-height: 1.4;
        }
        .terms-box {
            width: 60%;
        }
        .signature-box {
            width: 38%;
        }
        .signature-box p {
            border-top: 1px solid #666;
            padding-top: 3px;
            margin-top: 30px; /* 签名区高度减小 */
        }
        .terms-box h4, .signature-box h4 {
            color: #0a5f99;
            border-left: 2px solid #cc9900;
            padding-left: 5px;
            font-size: 10pt;
            margin-bottom: 5px;
        }

        /* 页脚联系方式 - 压缩到最底部 */
        .quote-footer {
            margin-top: 10px;
            padding-top: 5px;
            text-align: center;
            font-size: 8pt;
            color: #555;
            border-top: 1px dashed #ccc;
            position: absolute;
            bottom: 10mm; /* 距离A4纸底边10mm */
            left: 15mm;
            right: 15mm;
        }
        .quote-footer .contact-line {
            margin: 0 5px;
        }
        
        /* ------------------------------------- */
        /* 打印样式优化 (@media print) */
        /* ------------------------------------- */
        @media print {
            body {
                background: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .container {
                width: 100%;
                min-height: auto;
                margin: 0;
                padding: 8mm 12mm; /* 打印时更紧凑 */
                box-shadow: none;
                border: none;
                position: static;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
            .action-bar {
                display: none;
            }
            /* 打印时确保表格不被截断 */
            .item-table tr {
                page-break-inside: avoid;
            }
            .summary-box {
                border: 1px solid #cc9900; /* 打印时边框恢复标准 */
            }
            .quote-footer {
                 position: static; /* 打印时解除绝对定位 */
            }
        }
        
        /* ------------------------------------- */
        /* 屏幕操作栏样式 */
        /* ------------------------------------- */
        .action-bar {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 8px 12px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        .action-bar button {
            background-color: #0a5f99;
            color: white;
            border: none;
            padding: 6px 12px;
            text-align: center;
            cursor: pointer;
            border-radius: 3px;
            font-size: 13px;
            margin: 0 3px;
        }
        .action-bar button:hover {
            background-color: #084c7a;
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
                
                <div class="info-grid-full"><span>项目名称:</span> <?php echo htmlspecialchars($quote['project_name'] ?? '---'); ?></div>
                
                <div><span>工期:</span> <?php echo htmlspecialchars($quote['construction_period'] ?? 'N/A'); ?></div>
                <div><span>模板:</span> <?php echo htmlspecialchars($quote['template_type']); ?></div>
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
                        <td class="name-col">
                            <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                        </td>
                        <td class="spec-col"><?php echo htmlspecialchars($item['spec'] ?: $item['model'] ?: '---'); ?></td>
                        <td><?php echo htmlspecialchars($item['brand'] ?: '通用'); ?></td>
                        <td class="unit-col"><?php echo htmlspecialchars($item['unit'] ?: '项'); ?></td> <td class="qty-col"><?php echo intval($item['quantity']); ?></td>
                        <td class="price-col"><?php echo number_format($item['price'], 2); ?></td>
                        <td class="subtotal-col"><?php echo number_format($item['subtotal'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['warranty'] ?: '1年'); ?></td>
                    </tr>
                <?php 
                    endforeach; 
                endif; 
                
                // 填充空白行以凑够13行总行数（内容行+汇总行+签名行）
                $min_content_rows = 13; // 目标内容行数 (包含项目和汇总等)
                $used_rows = $row_count + 2; // 实际项目行数 + 汇总行 + 签名/条款预留
                $blank_rows = max(0, $min_content_rows - $used_rows);
                
                for ($i = 0; $i < $blank_rows; $i++) {
                    echo '<tr><td class="seq-col">-</td><td class="name-col"></td><td class="spec-col"></td><td></td><td class="unit-col"></td><td class="qty-col"></td><td class="price-col"></td><td class="subtotal-col"></td><td></td></tr>';
                }
                
                ?>
            </tbody>
        </table>
        
        <div class="summary-flex">
            <div class="summary-box">
                <div class="summary-row">
                    <span>项目小计</span>
                    <span>¥<?php echo number_format($total_before_discount, 2); ?></span>
                </div>
                <div class="summary-row">
                    <span>折扣/优惠</span>
                    <span style="color: #d9534f;">- ¥<?php echo number_format($quote['discount'] ?? 0, 2); ?></span>
                </div>
                <div class="summary-row summary-total">
                    <span>最终总价 (含税/不含税)</span>
                    <span>¥<?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>


        <div class="terms-signature">
            <div class="terms-box">
                <h4>报价条款</h4>
                <div style="white-space: pre-wrap; margin-left: 5px; border-left: 2px solid #ccc; padding-left: 5px;"><?php echo htmlspecialchars($quote['terms'] ?? '1. 本报价自报价日起'.$quote['valid_days'].'天内有效。2. 所有报价未包含税费，如需开票请另行说明。'); ?></div>
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
                <span class="contact-line">联系人: <?php echo $company_info['contact_person']; ?></span> | 
                <span class="contact-line">电话: <?php echo $company_info['phone']; ?></span> | 
                <span class="contact-line">邮箱: <?php echo $company_info['email']; ?></span> | 
                <span class="contact-line">公司地址: <?php echo $company_info['address']; ?></span>
            </p>
            <p>税号/开户行: <?php echo $company_info['tax_id']; ?> / <?php echo $company_info['bank_name']; ?> (账号: <?php echo $company_info['bank_account']; ?>)</p>
        </div>

    </div>

    <script>
        // 保持打印提示的脚本
        window.onload = function() {
            if (window.matchMedia('print').matches) {
                return;
            }
            const printButton = document.querySelector('.action-bar button:first-child');
            if (printButton) {
                printButton.addEventListener('click', function() {
                    setTimeout(() => {
                        alert('如需导出为 PDF 文件，请在弹出的打印窗口中，将“目标打印机”设置为“另存为 PDF”或类似的选项。');
                    }, 500); 
                });
            }
        };
    </script>
</body>
</html>