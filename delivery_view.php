<?php
/**
 * ============================================================================
 * 文件名: delivery_view.php
 * 版本: 1.0
 * 创建日期: 2025-10-17
 * 说明: 送货单详情查看页面
 * 
 * 功能说明：
 * 1. 显示送货单完整信息
 * 2. 显示送货明细
 * 3. 显示收款记录
 * 4. 显示操作日志
 * 5. 状态更新操作
 * 6. 收款登记
 * 7. 签收确认
 * ============================================================================
 */

session_start();
require_once 'config.php';

// ==================== 权限验证 ====================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ==================== 获取参数 ====================
$delivery_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($delivery_id <= 0) {
    die('无效的送货单ID');
}

// ==================== 连接数据库 ====================
$conn = getDBConnection();

// ==================== 查询送货单信息 ====================
$delivery_sql = "
    SELECT 
        d.*,
        c.company_name as customer_company,
        c.address as customer_address
    FROM deliveries d
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE d.id = ?
";

$delivery_stmt = $conn->prepare($delivery_sql);
$delivery_stmt->bind_param('i', $delivery_id);
$delivery_stmt->execute();
$delivery_result = $delivery_stmt->get_result();

if ($delivery_result->num_rows === 0) {
    die('送货单不存在');
}

$delivery = $delivery_result->fetch_assoc();
$delivery_stmt->close();

// ==================== 查询送货明细 ====================
$items_sql = "
    SELECT * FROM delivery_items WHERE delivery_id = ? ORDER BY id
";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param('i', $delivery_id);
$items_stmt->execute();
$items = $items_stmt->get_result();
$items_stmt->close();

// ==================== 查询收款记录 ====================
$payments_sql = "
    SELECT * FROM delivery_payments WHERE delivery_id = ? ORDER BY payment_date DESC
";
$payments_stmt = $conn->prepare($payments_sql);
$payments_stmt->bind_param('i', $delivery_id);
$payments_stmt->execute();
$payments = $payments_stmt->get_result();
$payments_stmt->close();

// ==================== 查询操作日志 ====================
$logs_sql = "
    SELECT * FROM delivery_logs WHERE delivery_id = ? ORDER BY created_at DESC
";
$logs_stmt = $conn->prepare($logs_sql);
$logs_stmt->bind_param('i', $delivery_id);
$logs_stmt->execute();
$logs = $logs_stmt->get_result();
$logs_stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>送货单详情 - <?php echo htmlspecialchars($delivery['delivery_no']); ?></title>
    <style>
        /* ==================== 全局样式 ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        /* ==================== 导航栏 ==================== */
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
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }

        .navbar-actions {
            display: flex;
            gap: 12px;
        }

        /* ==================== 按钮样式 ==================== */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-info {
            background: #3b82f6;
            color: white;
        }

        .btn-info:hover {
            background: #2563eb;
        }

        /* ==================== 主内容区 ==================== */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==================== 页面头部 ==================== */
        .page-header {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .header-title {
            flex: 1;
        }

        .delivery-no {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .delivery-meta {
            display: flex;
            gap: 20px;
            color: #718096;
            font-size: 14px;
        }

        .status-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        /* ==================== 标签样式 ==================== */
        .badge {
            padding: 6px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-pending { background: #e0e7ff; color: #3730a3; }
        .badge-delivering { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #6b7280; }

        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-paid { background: #d1fae5; color: #065f46; }

        .badge-cod { 
            background: #fef3c7; 
            color: #92400e; 
            font-size: 12px;
            margin-left: 12px;
        }

        /* ==================== 信息卡片 ==================== */
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
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
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }

        .info-item {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f7fafc;
        }

        .info-label {
            width: 120px;
            color: #718096;
            font-size: 14px;
        }

        .info-value {
            flex: 1;
            color: #1a202c;
            font-size: 14px;
            font-weight: 500;
        }

        /* ==================== 表格样式 ==================== */
        .table-wrapper {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: #f7fafc;
        }

        .table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .table tbody tr:hover {
            background: #f7fafc;
        }

        /* ==================== 费用汇总 ==================== */
        .fee-summary {
            background: #f7fafc;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .fee-row.total {
            border-top: 2px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
        }

        .fee-row.unpaid {
            color: #ef4444;
        }

        /* ==================== 时间线样式 ==================== */
        .timeline {
            position: relative;
            padding-left: 30px;
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
            top: 6px;
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
            top: 18px;
            width: 2px;
            height: calc(100% - 6px);
            background: #e2e8f0;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .timeline-content {
            background: #f7fafc;
            border-radius: 8px;
            padding: 12px;
        }

        .timeline-time {
            font-size: 12px;
            color: #718096;
            margin-bottom: 4px;
        }

        .timeline-text {
            font-size: 14px;
            color: #1a202c;
            font-weight: 500;
        }

        .timeline-operator {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        /* ==================== 空状态 ==================== */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }

        /* ==================== 模态框 ==================== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 24px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }

        .form-label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        /* ==================== 响应式 ==================== */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 16px;
            }

            .main-content {
                padding: 16px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .header-top {
                flex-direction: column;
            }

            .status-actions {
                margin-top: 16px;
                width: 100%;
            }

            .status-actions .btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">🚚 送货单详情</div>
        <div class="navbar-actions">
            <a href="deliveries.php" class="btn btn-secondary">返回列表</a>
            <?php if ($delivery['status'] != 'completed' && $delivery['status'] != 'cancelled'): ?>
            <a href="delivery_edit.php?id=<?php echo $delivery_id; ?>" class="btn btn-primary">编辑</a>
            <?php endif; ?>
            <button class="btn btn-secondary" onclick="window.print()">🖨️ 打印</button>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <!-- ==================== 页面头部 ==================== -->
        <div class="page-header">
            <div class="header-top">
                <div class="header-title">
                    <div class="delivery-no">
                        <?php echo htmlspecialchars($delivery['delivery_no']); ?>
                        <?php if ($delivery['collect_on_delivery']): ?>
                        <span class="badge badge-cod">货到付款</span>
                        <?php endif; ?>
                    </div>
                    <div class="delivery-meta">
                        <span>创建时间: <?php echo date('Y-m-d H:i', strtotime($delivery['created_at'])); ?></span>
                        <?php if ($delivery['updated_at'] != $delivery['created_at']): ?>
                        <span>更新时间: <?php echo date('Y-m-d H:i', strtotime($delivery['updated_at'])); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="status-actions">
                    <span class="badge badge-<?php echo $delivery['status']; ?>">
                        <?php 
                        $status_labels = [
                            'pending' => '待送货',
                            'delivering' => '配送中',
                            'completed' => '已完成',
                            'failed' => '失败',
                            'cancelled' => '已取消'
                        ];
                        echo $status_labels[$delivery['status']] ?? $delivery['status'];
                        ?>
                    </span>

                    <?php if ($delivery['status'] == 'pending'): ?>
                        <button class="btn btn-info" onclick="updateStatus('delivering')">开始配送</button>
                        <button class="btn btn-danger" onclick="updateStatus('cancelled')">取消</button>
                    <?php elseif ($delivery['status'] == 'delivering'): ?>
                        <button class="btn btn-success" onclick="showCompleteModal()">确认签收</button>
                        <button class="btn btn-warning" onclick="updateStatus('failed')">标记失败</button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 支付状态 -->
            <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0;">
                <span style="color: #718096; margin-right: 12px;">支付状态:</span>
                <span class="badge badge-<?php echo $delivery['payment_status']; ?>">
                    <?php 
                    $payment_labels = [
                        'unpaid' => '未付款',
                        'partial' => '部分付款',
                        'paid' => '已付款'
                    ];
                    echo $payment_labels[$delivery['payment_status']] ?? $delivery['payment_status'];
                    ?>
                </span>
                <?php if ($delivery['payment_status'] != 'paid'): ?>
                <button class="btn btn-success" onclick="showPaymentModal()" style="margin-left: 12px;">登记收款</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== 客户信息 ==================== -->
        <div class="info-card">
            <h2 class="card-title">客户信息</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">客户名称</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['customer_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">联系人</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['contact_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">联系电话</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['contact_phone']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">送货地址</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['delivery_address']); ?></div>
                </div>
            </div>
        </div>

        <!-- ==================== 送货信息 ==================== -->
        <div class="info-card">
            <h2 class="card-title">送货信息</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">送货日期</div>
                    <div class="info-value"><?php echo $delivery['delivery_date']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">送货时间</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['delivery_time']) ?: '不限'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">送货人</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['delivery_person']) ?: '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">车牌号</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['vehicle_no']) ?: '-'; ?></div>
                </div>
                <?php if ($delivery['actual_delivery_time']): ?>
                <div class="info-item">
                    <div class="info-label">实际送达时间</div>
                    <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($delivery['actual_delivery_time'])); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($delivery['recipient_name']): ?>
                <div class="info-item">
                    <div class="info-label">收货人</div>
                    <div class="info-value"><?php echo htmlspecialchars($delivery['recipient_name']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== 送货明细 ==================== -->
        <div class="info-card">
            <h2 class="card-title">送货明细</h2>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="60">序号</th>
                            <th>产品名称</th>
                            <th>规格型号</th>
                            <th width="80">数量</th>
                            <th width="60">单位</th>
                            <th width="120">单价</th>
                            <th width="120">小计</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $index = 1;
                        $total_qty = 0;
                        while ($item = $items->fetch_assoc()): 
                            $total_qty += $item['quantity'];
                        ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $index++; ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['product_spec']) ?: '-'; ?></td>
                            <td style="text-align: center;"><?php echo $item['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($item['unit']); ?></td>
                            <td style="text-align: right;">¥<?php echo number_format($item['unit_price'], 2); ?></td>
                            <td style="text-align: right;"><strong>¥<?php echo number_format($item['subtotal'], 2); ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                        <tr style="background: #f7fafc; font-weight: 600;">
                            <td colspan="3" style="text-align: right;">合计：</td>
                            <td style="text-align: center;"><?php echo $total_qty; ?></td>
                            <td colspan="2"></td>
                            <td style="text-align: right;">¥<?php echo number_format($delivery['goods_amount'], 2); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 费用汇总 -->
            <div class="fee-summary">
                <div class="fee-row">
                    <span>货物金额：</span>
                    <span>¥<?php echo number_format($delivery['goods_amount'], 2); ?></span>
                </div>
                <div class="fee-row">
                    <span>运费：</span>
                    <span>¥<?php echo number_format($delivery['freight_fee'], 2); ?></span>
                </div>
                <div class="fee-row total">
                    <span>总金额：</span>
                    <span>¥<?php echo number_format($delivery['total_amount'], 2); ?></span>
                </div>
                <div class="fee-row">
                    <span>已收款：</span>
                    <span style="color: #10b981;">¥<?php echo number_format($delivery['paid_amount'], 2); ?></span>
                </div>
                <?php if ($delivery['total_amount'] > $delivery['paid_amount']): ?>
                <div class="fee-row unpaid">
                    <span>未收款：</span>
                    <span><strong>¥<?php echo number_format($delivery['total_amount'] - $delivery['paid_amount'], 2); ?></strong></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ==================== 收款记录 ==================== -->
        <div class="info-card">
            <h2 class="card-title">收款记录</h2>
            <?php if ($payments->num_rows > 0): ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th width="80">类型</th>
                            <th width="120">金额</th>
                            <th width="100">支付方式</th>
                            <th width="180">支付时间</th>
                            <th>收款人</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($payment = $payments->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="badge badge-<?php echo $payment['payment_type'] == 'received' ? 'paid' : 'unpaid'; ?>" 
                                      style="font-size: 12px;">
                                    <?php echo $payment['payment_type'] == 'received' ? '已收' : '应收'; ?>
                                </span>
                            </td>
                            <td style="font-weight: 600; color: <?php echo $payment['payment_type'] == 'received' ? '#10b981' : '#ef4444'; ?>;">
                                ¥<?php echo number_format($payment['amount'], 2); ?>
                            </td>
                            <td><?php echo htmlspecialchars($payment['payment_method'] ?? '') ?: '-'; ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($payment['payment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($payment['payee'] ?? '') ?: '-'; ?></td>
                            <td><?php echo htmlspecialchars($payment['notes'] ?? '') ?: '-'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">暂无收款记录</div>
            <?php endif; ?>
        </div>

        <!-- ==================== 操作日志 ==================== -->
        <div class="info-card">
            <h2 class="card-title">操作日志</h2>
            <?php if ($logs->num_rows > 0): ?>
            <div class="timeline">
                <?php while ($log = $logs->fetch_assoc()): ?>
                <div class="timeline-item">
                    <div class="timeline-content">
                        <div class="timeline-time">
                            <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                        </div>
                        <div class="timeline-text">
                            <?php echo htmlspecialchars($log['description']); ?>
                        </div>
                        <?php if ($log['location']): ?>
                        <div class="timeline-operator">
                            📍 <?php echo htmlspecialchars($log['location']); ?>
                        </div>
                        <?php endif; ?>
                        <div class="timeline-operator">
                            👤 <?php echo htmlspecialchars($log['operator']); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">暂无操作日志</div>
            <?php endif; ?>
        </div>

        <!-- ==================== 备注信息 ==================== -->
        <?php if (!empty($delivery['notes'])): ?>
        <div class="info-card">
            <h2 class="card-title">备注信息</h2>
            <div style="padding: 12px; background: #f7fafc; border-radius: 8px; line-height: 1.8;">
                <?php echo nl2br(htmlspecialchars($delivery['notes'])); ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- ==================== 收款登记模态框 ==================== -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">登记收款</div>
            <form id="paymentForm">
                <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                
                <div class="form-group">
                    <label class="form-label required">收款金额</label>
                    <input type="number" name="amount" class="form-input" 
                           step="0.01" min="0.01" 
                           max="<?php echo $delivery['total_amount'] - $delivery['paid_amount']; ?>"
                           value="<?php echo $delivery['total_amount'] - $delivery['paid_amount']; ?>"
                           required>
                    <small style="color: #718096; font-size: 12px;">
                        应收: ¥<?php echo number_format($delivery['total_amount'] - $delivery['paid_amount'], 2); ?>
                    </small>
                </div>

                <div class="form-group">
                    <label class="form-label required">支付方式</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="">请选择</option>
                        <option value="现金">现金</option>
                        <option value="银行转账">银行转账</option>
                        <option value="支付宝">支付宝</option>
                        <option value="微信">微信</option>
                        <option value="刷卡">刷卡</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">收款人</label>
                    <input type="text" name="payee" class="form-input" 
                           placeholder="请输入收款人">
                </div>

                <div class="form-group">
                    <label class="form-label">备注</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="收款备注信息" rows="3"></textarea>
                </div>
            </form>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closePaymentModal()">取消</button>
                <button class="btn btn-success" onclick="submitPayment()">确认收款</button>
            </div>
        </div>
    </div>

    <!-- ==================== 签收确认模态框 ==================== -->
    <div id="completeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">确认签收</div>
            <form id="completeForm">
                <input type="hidden" name="delivery_id" value="<?php echo $delivery_id; ?>">
                
                <div class="form-group">
                    <label class="form-label required">收货人</label>
                    <input type="text" name="recipient_name" class="form-input" 
                           placeholder="请输入收货人姓名" required>
                </div>

                <div class="form-group">
                    <label class="form-label">签收时间</label>
                    <input type="datetime-local" name="actual_delivery_time" class="form-input" 
                           value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">签收备注</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="签收说明或特殊情况" rows="3"></textarea>
                </div>
            </form>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeCompleteModal()">取消</button>
                <button class="btn btn-success" onclick="submitComplete()">确认签收</button>
            </div>
        </div>
    </div>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 送货单详情页面加载 ===');

        /**
         * 更新状态
         */
        function updateStatus(newStatus) {
            const statusNames = {
                'delivering': '配送中',
                'cancelled': '已取消',
                'failed': '失败'
            };

            if (!confirm(`确定要将状态更新为"${statusNames[newStatus]}"吗？`)) {
                return;
            }

            fetch('delivery_status_update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `delivery_id=<?php echo $delivery_id; ?>&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('更新失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('更新错误:', error);
                alert('更新出错: ' + error.message);
            });
        }

        /**
         * 显示收款模态框
         */
        function showPaymentModal() {
            document.getElementById('paymentModal').classList.add('active');
        }

        /**
         * 关闭收款模态框
         */
        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
        }

        /**
         * 提交收款
         */
        function submitPayment() {
            const form = document.getElementById('paymentForm');
            
            if (!form.checkValidity()) {
                alert('请填写所有必填项');
                return;
            }

            const formData = new FormData(form);

            fetch('delivery_payment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('收款失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('收款错误:', error);
                alert('收款出错: ' + error.message);
            });
        }

        /**
         * 显示签收模态框
         */
        function showCompleteModal() {
            document.getElementById('completeModal').classList.add('active');
        }

        /**
         * 关闭签收模态框
         */
        function closeCompleteModal() {
            document.getElementById('completeModal').classList.remove('active');
        }

        /**
         * 提交签收
         */
        function submitComplete() {
            const form = document.getElementById('completeForm');
            
            if (!form.checkValidity()) {
                alert('请填写所有必填项');
                return;
            }

            const formData = new FormData(form);

            fetch('delivery_status_update.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('签收失败: ' + data.message);
                }
            })
            .catch(error => {
                console.error('签收错误:', error);
                alert('签收出错: ' + error.message);
            });
        }

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const paymentModal = document.getElementById('paymentModal');
            const completeModal = document.getElementById('completeModal');
            
            if (event.target === paymentModal) {
                closePaymentModal();
            }
            if (event.target === completeModal) {
                closeCompleteModal();
            }
        }

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>