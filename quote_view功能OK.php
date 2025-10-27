<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$quote_id = $_GET['id'] ?? 0;
$conn = getDBConnection();

$quote = $conn->query("
    SELECT q.*, c.company_name, c.contact_name, c.phone, c.address, u.nickname as creator_name
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.user_id = u.id
    WHERE q.id = $quote_id
")->fetch_assoc();

if (!$quote) {
    die('报价单不存在');
}

$items = $conn->query("
    SELECT * FROM quote_items 
    WHERE quote_id = $quote_id 
    ORDER BY seq
");

$template = $conn->query("
    SELECT * FROM quote_templates 
    WHERE template_code = '{$quote['template_type']}'
")->fetch_assoc();

$conn->close();

$is_construction = in_array($quote['template_type'], ['weak_current', 'strong_current']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $quote['quote_no']; ?> - 报价单</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Microsoft YaHei', '微软雅黑', sans-serif;
            background: #f5f5f5;
            padding: 10px;
        }

        /* A4尺寸 */
        .quote-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            padding: 15mm 12mm;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* 顶部紧凑设计 */
        .header {
            border: 3px solid #667eea;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #f8f9ff 0%, #ffffff 100%);
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .company-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .company-logo {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .company-info h1 {
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 2px;
        }

        .company-info p {
            font-size: 12px;
            color: #64748b;
        }

        .quote-no {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            background: white;
            padding: 6px 12px;
            border-radius: 6px;
            border: 2px solid #667eea;
        }

        /* 客户信息网格 */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px 12px;
            font-size: 12px;
            border-left: 3px solid #667eea;
            padding-left: 12px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-icon {
            width: 24px;
            height: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
        }

        .info-text span:first-child {
            color: #94a3b8;
            font-size: 11px;
        }

        .info-text span:last-child {
            color: #1e293b;
            font-weight: 500;
            display: block;
            line-height: 1.3;
        }

        /* 项目信息（施工类） */
        .project-bar {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-left: 3px solid #f59e0b;
            padding: 8px 12px;
            margin: 10px 0;
            border-radius: 4px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            font-size: 12px;
        }

        .project-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .project-item strong {
            color: #92400e;
            font-size: 11px;
        }

        /* 标题区 */
        .title-bar {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #1e293b;
            padding: 8px 0;
            margin: 10px 0;
            border-bottom: 2px solid #e2e8f0;
        }

        /* 产品表格 - 极致紧凑 */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 12px;
        }

        .items-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 4px;
            font-weight: 600;
            font-size: 11px;
            text-align: center;
        }

        .items-table td {
            padding: 6px 4px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .items-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        /* 金额汇总 - 紧凑版 */
        .summary-box {
            float: right;
            width: 250px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 12px;
        }

        .summary-row.total {
            margin-top: 6px;
            padding-top: 8px;
            border-top: 2px solid #cbd5e1;
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
        }

        /* 条款 - 极简版 */
        .terms-box {
            clear: both;
            font-size: 10px;
            line-height: 1.6;
            color: #475569;
            background: #f8fafc;
            padding: 8px 10px;
            border-radius: 4px;
            border-left: 3px solid #10b981;
            margin-bottom: 10px;
        }

        .terms-box strong {
            font-size: 11px;
            color: #1e293b;
            display: block;
            margin-bottom: 4px;
        }

        /* 签名区 - 精简版 */
        .signature-bar {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            font-size: 11px;
            text-align: center;
            padding: 8px 0;
        }

        .signature-box {
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 20px;
        }

        .signature-label {
            color: #64748b;
            margin-bottom: 4px;
        }

        /* 页脚 - 超紧凑 */
        .footer-bar {
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
        }

        /* 打印按钮 */
        .print-buttons {
            text-align: center;
            margin: 20px 0;
        }

        .btn {
            padding: 10px 25px;
            margin: 0 8px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .quote-page {
                width: 210mm;
                height: 297mm;
                margin: 0;
                box-shadow: none;
                padding: 12mm 10mm;
            }

            .print-buttons {
                display: none;
            }
        }

        @page {
            size: A4;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="quote-page">
        <!-- 紧凑头部 -->
        <div class="header">
            <div class="header-top">
                <div class="company-section">
                    <div class="company-logo">🏢</div>
                    <div class="company-info">
                        <h1>凯尔电脑</h1>
                        <p><?php 
                            $names = [
                                'assembled_pc' => '组装电脑报价单',
                                'brand_pc' => '品牌整机报价单',
                                'weak_current' => '弱电工程报价单',
                                'strong_current' => '强电工程报价单'
                            ];
                            echo $names[$quote['template_type']] ?? '报价单';
                        ?></p>
                    </div>
                </div>
                <div class="quote-no"><?php echo $quote['quote_no']; ?></div>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-icon">🏢</div>
                    <div class="info-text">
                        <span>客户名称</span>
                        <span><?php echo htmlspecialchars($quote['company_name'] ?? ''); ?></span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">👤</div>
                    <div class="info-text">
                        <span>联系人</span>
                        <span><?php echo htmlspecialchars($quote['contact_name'] ?? ''); ?></span>
                    </div>
                </div>

                <?php if (!empty($quote['phone'])): ?>
                <div class="info-item">
                    <div class="info-icon">📞</div>
                    <div class="info-text">
                        <span>联系电话</span>
                        <span><?php echo htmlspecialchars($quote['phone']); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-item">
                    <div class="info-icon">📅</div>
                    <div class="info-text">
                        <span>报价日期</span>
                        <span><?php echo date('Y年m月d日', strtotime($quote['quote_date'])); ?></span>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-icon">⏱️</div>
                    <div class="info-text">
                        <span>有效期</span>
                        <span><?php echo $quote['valid_days']; ?> 天</span>
                    </div>
                </div>

                <?php if (!empty($quote['address'])): ?>
                <div class="info-item" style="grid-column: span 3;">
                    <div class="info-icon">📍</div>
                    <div class="info-text">
                        <span>地址</span>
                        <span><?php echo htmlspecialchars($quote['address']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 项目信息条（施工类） -->
        <?php if ($is_construction && !empty($quote['project_name'])): ?>
        <div class="project-bar">
            <div class="project-item">
                <strong>项目名称：</strong><?php echo htmlspecialchars($quote['project_name']); ?>
            </div>
            <?php if (!empty($quote['project_location'])): ?>
            <div class="project-item">
                <strong>项目地址：</strong><?php echo htmlspecialchars($quote['project_location']); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($quote['construction_period'])): ?>
            <div class="project-item">
                <strong>工期：</strong><?php echo htmlspecialchars($quote['construction_period']); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 标题 -->
        <div class="title-bar">
            <?php echo $is_construction ? '📋 工程项目清单' : '📦 产品配置清单'; ?>
        </div>

        <!-- 产品表格 -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="30">序号</th>
                    <?php if ($quote['template_type'] == 'assembled_pc'): ?>
                        <th>品名</th>
                        <th width="200">规格</th>
                    <?php elseif ($quote['template_type'] == 'brand_pc'): ?>
                        <th>产品名称</th>
                        <th width="80">品牌</th>
                        <th width="100">型号</th>
                        <th width="60">质保</th>
                    <?php else: ?>
                        <th>项目名称</th>
                        <th width="180">规格说明</th>
                    <?php endif; ?>
                    <th width="40" class="text-center">单位</th>
                    <th width="40" class="text-center">数量</th>
                    <th width="70" class="text-right">单价</th>
                    <th width="80" class="text-right">小计</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($item = $items->fetch_assoc()): ?>
                <tr>
                    <td class="text-center"><?php echo $item['seq']; ?></td>
                    <?php if ($quote['template_type'] == 'assembled_pc'): ?>
                        <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                        <td><?php 
                            $spec_parts = array_filter([
                                $item['brand'] ?? '',
                                $item['spec'] ?? ''
                            ]);
                            echo htmlspecialchars(implode(' ', $spec_parts));
                        ?></td>
                    <?php elseif ($quote['template_type'] == 'brand_pc'): ?>
                        <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['brand'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['model'] ?? ''); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['warranty'] ?? ''); ?></td>
                    <?php else: ?>
                        <td><?php echo htmlspecialchars($item['product_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($item['spec'] ?? ''); ?></td>
                    <?php endif; ?>
                    <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?? '个'); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-right">¥<?php echo number_format($item['price'], 2); ?></td>
                    <td class="text-right"><strong>¥<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <!-- 金额汇总 -->
        <div class="summary-box">
            <div class="summary-row">
                <span>小计</span>
                <span>¥<?php echo number_format($quote['final_amount'] + $quote['discount'], 2); ?></span>
            </div>
            <?php if ($quote['discount'] > 0): ?>
            <div class="summary-row">
                <span>折扣</span>
                <span style="color: #ef4444;">-¥<?php echo number_format($quote['discount'], 2); ?></span>
            </div>
            <?php endif; ?>
            <div class="summary-row total">
                <span>合计金额</span>
                <span>¥<?php echo number_format($quote['final_amount'], 2); ?></span>
            </div>
        </div>

        <!-- 条款说明 -->
        <?php if ($template && !empty($template['default_terms'])): ?>
        <div class="terms-box">
            <strong>✓ 条款说明</strong>
            <?php echo nl2br(htmlspecialchars($template['default_terms'])); ?>
        </div>
        <?php endif; ?>

        <!-- 签名区 -->
        <div class="signature-bar">
            <div class="signature-box">
                <div class="signature-label">销售代表</div>
                <div><?php echo htmlspecialchars($quote['creator_name'] ?? ''); ?></div>
            </div>
            <div class="signature-box">
                <div class="signature-label">客户签字</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">签约日期</div>
            </div>
        </div>

        <!-- 页脚 -->
        <div class="footer-bar">
            凯尔电脑是一家企业IT解决方案商  专为企业IT提供一站式选型采购安装部署运维解决方案 为企业保驾护航  https://www.864g.com
        </div>
    </div>

    <div class="print-buttons">
        <button class="btn btn-primary" onclick="window.print()">🖨️ 打印报价单</button>
        <button class="btn btn-secondary" onclick="window.close()">关闭窗口</button>
    </div>
</body>
</html>