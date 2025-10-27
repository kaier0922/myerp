<?php
/**
 * =====================================================
 * 文件名:repair_view.php
 * 功能:查看维修单详情
 * 描述:显示完整的维修单信息,包括配件、日志、费用等
 * =====================================================
 */

session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$conn = getDBConnection();

// 获取维修单ID
$repair_id = $_GET['id'] ?? 0;

// 获取维修单信息
$repair = $conn->query("
    SELECT ro.*, c.company_name, c.contact_name as customer_contact
    FROM repair_orders ro
    LEFT JOIN customers c ON ro.customer_id = c.id
    WHERE ro.id = $repair_id
")->fetch_assoc();

if (!$repair) {
    $_SESSION['message'] = '维修单不存在!';
    header('Location: repair.php');
    exit;
}

// 获取配件列表
$parts = $conn->query("SELECT * FROM repair_parts WHERE repair_id = $repair_id ORDER BY created_at");

// 获取维修日志
$logs = $conn->query("SELECT * FROM repair_logs WHERE repair_id = $repair_id ORDER BY created_at DESC");

// 获取收付款记录
$payments = $conn->query("SELECT * FROM repair_payments WHERE repair_id = $repair_id ORDER BY payment_date DESC");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>维修单详情 - 维修管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            text-decoration: none;
        }

        .navbar-actions {
            display: flex;
            gap: 12px;
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .order-no {
            font-size: 16px;
            color: #718096;
            font-weight: 400;
        }

        .status-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.repairing { background: #dbeafe; color: #1e3a8a; }
        .status-badge.testing { background: #e0e7ff; color: #3730a3; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }
        .status-badge.delivered { background: #d1fae5; color: #065f46; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }

        .header-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .header-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .header-label {
            font-size: 13px;
            color: #a0aec0;
            text-transform: uppercase;
            font-weight: 600;
        }

        .header-value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 500;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .info-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }

        .info-value {
            font-size: 15px;
            color: #2d3748;
        }

        .info-value.large {
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .timeline {
            position: relative;
            padding-left: 32px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 24px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #667eea;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -21px;
            top: 12px;
            width: 2px;
            height: calc(100% - 12px);
            background: #e2e8f0;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .timeline-time {
            font-size: 13px;
            color: #a0aec0;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .timeline-content {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .timeline-operator {
            font-size: 13px;
            color: #718096;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1.5;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .fee-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 15px;
        }

        .fee-row.total {
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 10px;
            padding-top: 16px;
            font-weight: 700;
            font-size: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        .print-only {
            display: none;
        }

        @media print {
            .navbar, .btn, .no-print {
                display: none !important;
            }

            .print-only {
                display: block;
            }

            body {
                background: white;
            }

            .main-content {
                margin-top: 0;
                padding: 20px;
            }

            .content-card, .page-header {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                gap: 16px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar no-print">
        <a href="repair.php" class="navbar-brand">
            <span>🔧</span>
            <span>维修单详情</span>
        </a>
        <div class="navbar-actions">
            <?php if ($repair['status'] != 'delivered' && $repair['status'] != 'cancelled'): ?>
                <button class="btn btn-primary" onclick="window.location.href='repair_edit.php?id=<?php echo $repair_id; ?>'">
                    ✏️ 编辑
                </button>
            <?php endif; ?>
            <button class="btn btn-secondary" onclick="window.print()">
                🖨️ 打印
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='repair.php'">
                ← 返回列表
            </button>
        </div>
    </nav>

    <!-- 打印标题 -->
    <div class="print-only" style="text-align: center; padding: 20px 0; border-bottom: 2px solid #000;">
        <h1 style="font-size: 28px; margin-bottom: 10px;">维修单</h1>
        <p style="font-size: 14px; color: #666;">Repair Order</p>
    </div>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 页面头部 -->
        <div class="page-header">
            <div class="header-top">
                <div>
                    <h1 class="page-title">维修单详情</h1>
                    <p class="order-no">单号: <?php echo htmlspecialchars($repair['order_no']); ?></p>
                </div>
                <?php
                $status_map = [
                    'pending' => '待处理',
                    'repairing' => '维修中',
                    'testing' => '测试中',
                    'completed' => '已完成',
                    'delivered' => '已交付',
                    'cancelled' => '已取消'
                ];
                ?>
                <span class="status-badge <?php echo $repair['status']; ?>">
                    <?php echo $status_map[$repair['status']] ?? $repair['status']; ?>
                </span>
            </div>

            <div class="header-grid">
                <div class="header-item">
                    <div class="header-label">维修类型</div>
                    <div class="header-value">
                        <?php echo $repair['repair_type'] == 'onsite' ? '上门服务' : '带回维修'; ?>
                    </div>
                </div>

                <div class="header-item">
                    <div class="header-label">接收日期</div>
                    <div class="header-value">
                        <?php echo date('Y-m-d H:i', strtotime($repair['receive_date'])); ?>
                    </div>
                </div>

                <?php if ($repair['expected_finish_date']): ?>
                    <div class="header-item">
                        <div class="header-label">预计完成</div>
                        <div class="header-value">
                            <?php echo date('Y-m-d', strtotime($repair['expected_finish_date'])); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['actual_finish_date']): ?>
                    <div class="header-item">
                        <div class="header-label">实际完成</div>
                        <div class="header-value">
                            <?php echo date('Y-m-d H:i', strtotime($repair['actual_finish_date'])); ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 客户信息 -->
        <div class="content-card">
            <h3 class="card-title">
                <span>👤</span>
                <span>客户信息</span>
            </h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">客户姓名</div>
                    <div class="info-value"><?php echo htmlspecialchars($repair['customer_name']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">联系电话</div>
                    <div class="info-value"><?php echo htmlspecialchars($repair['contact_phone']); ?></div>
                </div>

                <?php if ($repair['contact_address']): ?>
                    <div class="info-item">
                        <div class="info-label">联系地址</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['contact_address']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['company_name']): ?>
                    <div class="info-item">
                        <div class="info-label">关联客户</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['company_name']); ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 设备信息 -->
        <div class="content-card">
            <h3 class="card-title">
                <span>💻</span>
                <span>设备信息</span>
            </h3>
            
            <div class="info-grid">
                <?php if ($repair['device_type']): ?>
                    <div class="info-item">
                        <div class="info-label">设备类型</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['device_type']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['device_brand']): ?>
                    <div class="info-item">
                        <div class="info-label">品牌</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['device_brand']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['device_model']): ?>
                    <div class="info-item">
                        <div class="info-label">型号</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['device_model']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['device_sn']): ?>
                    <div class="info-item">
                        <div class="info-label">序列号</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['device_sn']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['accessories']): ?>
                    <div class="info-item">
                        <div class="info-label">附带配件</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['accessories']); ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($repair['technician']): ?>
                    <div class="info-item">
                        <div class="info-label">维修技师</div>
                        <div class="info-value"><?php echo htmlspecialchars($repair['technician']); ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-item" style="margin-top: 20px;">
                <div class="info-label">故障描述</div>
                <div class="info-value" style="white-space: pre-wrap; line-height: 1.6;">
                    <?php echo htmlspecialchars($repair['fault_description']); ?>
                </div>
            </div>

            <?php if ($repair['appearance_check']): ?>
                <div class="info-item" style="margin-top: 16px;">
                    <div class="info-label">外观检查</div>
                    <div class="info-value" style="white-space: pre-wrap; line-height: 1.6;">
                        <?php echo htmlspecialchars($repair['appearance_check']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($repair['repair_result']): ?>
                <div class="info-item" style="margin-top: 16px;">
                    <div class="info-label">维修结果</div>
                    <div class="info-value" style="white-space: pre-wrap; line-height: 1.6;">
                        <?php echo htmlspecialchars($repair['repair_result']); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- 维修配件 -->
        <?php if ($parts->num_rows > 0): ?>
            <div class="content-card">
                <h3 class="card-title">
                    <span>🔧</span>
                    <span>维修配件</span>
                </h3>
                
                <table>
                    <thead>
                        <tr>
                            <th>配件名称</th>
                            <th>型号</th>
                            <th>数量</th>
                            <th>单价</th>
                            <th>小计</th>
                            <?php if ($repair['status'] != 'delivered'): ?>
                                <th class="no-print">供应商</th>
                                <th class="no-print">成本</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $parts_total = 0;
                        while ($part = $parts->fetch_assoc()): 
                            $parts_total += $part['subtotal'];
                        ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($part['part_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($part['part_model'] ?? '-'); ?></td>
                                <td><?php echo $part['quantity']; ?></td>
                                <td>¥<?php echo number_format($part['unit_price'], 2); ?></td>
                                <td><strong>¥<?php echo number_format($part['subtotal'], 2); ?></strong></td>
                                <?php if ($repair['status'] != 'delivered'): ?>
                                    <td class="no-print"><?php echo htmlspecialchars($part['supplier'] ?? '-'); ?></td>
                                    <td class="no-print">¥<?php echo number_format($part['supplier_cost'], 2); ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                        <tr style="background: #f7fafc; font-weight: 600;">
                            <td colspan="4" style="text-align: right;">配件费用合计:</td>
                            <td>¥<?php echo number_format($parts_total, 2); ?></td>
                            <?php if ($repair['status'] != 'delivered'): ?>
                                <td colspan="2" class="no-print"></td>
                            <?php endif; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- 费用汇总 -->
        <div class="content-card">
            <h3 class="card-title">
                <span>💰</span>
                <span>费用汇总</span>
            </h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">服务费用</div>
                    <div class="info-value">¥<?php echo number_format($repair['service_fee'], 2); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">配件费用</div>
                    <div class="info-value">¥<?php echo number_format($repair['parts_fee'], 2); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">其他费用</div>
                    <div class="info-value">¥<?php echo number_format($repair['other_fee'], 2); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">已付金额</div>
                    <div class="info-value" style="color: #10b981;">
                        ¥<?php echo number_format($repair['paid_amount'], 2); ?>
                    </div>
                </div>
            </div>

            <div class="fee-summary">
                <div class="fee-row">
                    <span>服务费用:</span>
                    <span>¥<?php echo number_format($repair['service_fee'], 2); ?></span>
                </div>
                <div class="fee-row">
                    <span>配件费用:</span>
                    <span>¥<?php echo number_format($repair['parts_fee'], 2); ?></span>
                </div>
                <div class="fee-row">
                    <span>其他费用:</span>
                    <span>¥<?php echo number_format($repair['other_fee'], 2); ?></span>
                </div>
                <div class="fee-row total">
                    <span>总计:</span>
                    <span>¥<?php echo number_format($repair['total_fee'], 2); ?></span>
                </div>
                <div class="fee-row" style="border-top: 1px solid rgba(255, 255, 255, 0.3); margin-top: 10px; padding-top: 16px;">
                    <span>已付金额:</span>
                    <span>¥<?php echo number_format($repair['paid_amount'], 2); ?></span>
                </div>
                <div class="fee-row">
                    <span>待付金额:</span>
                    <span>¥<?php echo number_format($repair['total_fee'] - $repair['paid_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- 维修日志 -->
        <div class="content-card no-print">
            <h3 class="card-title">
                <span>📋</span>
                <span>维修日志</span>
            </h3>
            
            <?php if ($logs->num_rows > 0): ?>
                <div class="timeline">
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <div class="timeline-item">
                            <div class="timeline-time">
                                <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                            </div>
                            <div class="timeline-content">
                                <?php echo htmlspecialchars($log['description']); ?>
                            </div>
                            <div class="timeline-operator">
                                操作人: <?php echo htmlspecialchars($log['operator']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">暂无日志记录</div>
            <?php endif; ?>
        </div>

        <!-- 备注 -->
        <?php if ($repair['notes']): ?>
            <div class="content-card">
                <h3 class="card-title">
                    <span>📝</span>
                    <span>备注信息</span>
                </h3>
                <div style="white-space: pre-wrap; line-height: 1.8; color: #4a5568;">
                    <?php echo htmlspecialchars($repair['notes']); ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>