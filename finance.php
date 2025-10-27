<?php
/**
 * =====================================================
 * 文件名：finance.php
 * 功能：财务管理主页面
 * 描述：管理应收应付账款，查看收付款记录，财务数据统计
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
$role = $_SESSION['role'];

$conn = getDBConnection();

// 获取应收账款统计
$receivable_stats = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        SUM(total_amount) as total_amount,
        SUM(paid_amount) as paid_amount,
        SUM(outstanding_amount) as outstanding_amount
    FROM accounts_receivable 
    WHERE status IN ('未收款', '部分收款', '已逾期')
")->fetch_assoc();

// 获取应付账款统计
$payable_stats = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        SUM(total_amount) as total_amount,
        SUM(paid_amount) as paid_amount,
        SUM(outstanding_amount) as outstanding_amount
    FROM accounts_payable 
    WHERE status IN ('未付款', '部分付款', '已逾期')
")->fetch_assoc();

// 获取应收账款列表
$receivables = $conn->query("
    SELECT ar.*, c.company_name, c.contact_name
    FROM accounts_receivable ar
    LEFT JOIN customers c ON ar.customer_id = c.id
    ORDER BY ar.bill_date DESC
    LIMIT 50
");

// 获取应付账款列表
$payables = $conn->query("
    SELECT * FROM accounts_payable
    ORDER BY bill_date DESC
    LIMIT 50
");

// 获取最近的收付款记录
$recent_payments = $conn->query("
    SELECT pr.*, u.nickname as operator_name
    FROM payment_records pr
    LEFT JOIN users u ON pr.operator_id = u.id
    ORDER BY pr.payment_date DESC, pr.created_at DESC
    LIMIT 20
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>财务管理 - 企业管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        /* 顶部导航栏 */
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

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-back {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        /* 主内容区 */
        .main-content {
            margin-top: 64px;
            padding: 32px;
        }

        /* 页面头部 */
        .page-header {
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .page-subtitle {
            color: #718096;
            font-size: 14px;
        }

        /* 标签页 */
        .tabs {
            background: white;
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 24px;
            display: flex;
            gap: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 12px 24px;
            background: transparent;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            color: #718096;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            background: #f7fafc;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* 统计卡片 */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-box-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }

        .stat-box-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
        }

        .stat-box-value.positive {
            color: #10b981;
        }

        .stat-box-value.negative {
            color: #ef4444;
        }

        /* 内容卡片 */
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
        }

        .btn-primary {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #4a5568;
            transition: all 0.2s;
        }

        .btn-sm:hover {
            background: #edf2f7;
        }

        /* 表格样式 */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tr:hover {
            background: #f7fafc;
        }

        /* 徽章样式 */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.success { 
            background: #d1fae5; 
            color: #065f46; 
        }

        .badge.warning { 
            background: #fed7aa; 
            color: #92400e; 
        }

        .badge.info { 
            background: #dbeafe; 
            color: #1e3a8a; 
        }

        .badge.danger { 
            background: #fee2e2; 
            color: #991b1b; 
        }

        /* 金额样式 */
        .amount {
            font-weight: 600;
        }

        .amount.positive {
            color: #10b981;
        }

        .amount.negative {
            color: #ef4444;
        }

        .overdue {
            color: #dc2626;
            font-weight: 600;
        }

        /* 成功消息提示 */
        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            table {
                font-size: 12px;
            }

            th, td {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span>💳</span>
            <span>财务管理系统</span>
        </a>
        <div class="navbar-user">
            <a href="index.php" class="btn-back">← 返回主页</a>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 页面头部 -->
        <div class="page-header">
            <h1 class="page-title">💰 财务管理</h1>
            <p class="page-subtitle">管理应收应付账款，跟踪资金流动</p>
        </div>

        <!-- 成功消息提示 -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- 标签页切换 -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('receivable')">应收账款</button>
            <button class="tab-btn" onclick="switchTab('payable')">应付账款</button>
            <button class="tab-btn" onclick="switchTab('records')">收付款记录</button>
        </div>

        <!-- ================== 应收账款标签页 ================== -->
        <div id="receivable" class="tab-content active">
            <!-- 应收统计 -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-box-label">待收账单数</div>
                    <div class="stat-box-value"><?php echo $receivable_stats['total_count'] ?? 0; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">应收总额</div>
                    <div class="stat-box-value positive">¥<?php echo number_format($receivable_stats['total_amount'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">已收金额</div>
                    <div class="stat-box-value">¥<?php echo number_format($receivable_stats['paid_amount'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">未收金额</div>
                    <div class="stat-box-value negative">¥<?php echo number_format($receivable_stats['outstanding_amount'] ?? 0, 2); ?></div>
                </div>
            </div>

            <!-- 应收账款列表 -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">应收账款列表</h2>
                    <button class="btn-primary" onclick="addReceivable()">+ 新增应收</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>账单编号</th>
                            <th>客户名称</th>
                            <th>账单日期</th>
                            <th>应收金额</th>
                            <th>已收金额</th>
                            <th>未收金额</th>
                            <th>到期日期</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($receivables->num_rows > 0): ?>
                            <?php while ($row = $receivables->fetch_assoc()): ?>
                                <?php
                                // 判断是否逾期
                                $is_overdue = $row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] != '已收款';
                                $status_class = [
                                    '未收款' => 'danger',
                                    '部分收款' => 'warning',
                                    '已收款' => 'success',
                                    '已逾期' => 'danger'
                                ];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['bill_no']); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($row['company_name']); ?>
                                        <br><small style="color: #a0aec0;"><?php echo htmlspecialchars($row['contact_name']); ?></small>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($row['bill_date'])); ?></td>
                                    <td class="amount">¥<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="amount positive">¥<?php echo number_format($row['paid_amount'], 2); ?></td>
                                    <td class="amount negative">¥<?php echo number_format($row['outstanding_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($is_overdue): ?>
                                            <span class="overdue">⚠️ <?php echo date('Y-m-d', strtotime($row['due_date'])); ?></span>
                                        <?php else: ?>
                                            <?php echo $row['due_date'] ? date('Y-m-d', strtotime($row['due_date'])) : '-'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $status_class[$row['status']] ?? 'info'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($row['outstanding_amount'] > 0): ?>
                                                <button class="btn-sm" onclick="receivePayment(<?php echo $row['id']; ?>)">收款</button>
                                            <?php endif; ?>
                                            <button class="btn-sm" onclick="viewDetail(<?php echo $row['id']; ?>, 'receivable')">详情</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #a0aec0; padding: 40px;">暂无应收账款数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================== 应付账款标签页 ================== -->
        <div id="payable" class="tab-content">
            <!-- 应付统计 -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-box-label">待付账单数</div>
                    <div class="stat-box-value"><?php echo $payable_stats['total_count'] ?? 0; ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">应付总额</div>
                    <div class="stat-box-value negative">¥<?php echo number_format($payable_stats['total_amount'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">已付金额</div>
                    <div class="stat-box-value">¥<?php echo number_format($payable_stats['paid_amount'] ?? 0, 2); ?></div>
                </div>
                <div class="stat-box">
                    <div class="stat-box-label">未付金额</div>
                    <div class="stat-box-value negative">¥<?php echo number_format($payable_stats['outstanding_amount'] ?? 0, 2); ?></div>
                </div>
            </div>

            <!-- 应付账款列表 -->
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">应付账款列表</h2>
                    <button class="btn-primary" onclick="addPayable()">+ 新增应付</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>账单编号</th>
                            <th>供应商名称</th>
                            <th>费用类别</th>
                            <th>账单日期</th>
                            <th>应付金额</th>
                            <th>已付金额</th>
                            <th>未付金额</th>
                            <th>到期日期</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payables->num_rows > 0): ?>
                            <?php while ($row = $payables->fetch_assoc()): ?>
                                <?php
                                // 判断是否逾期
                                $is_overdue = $row['due_date'] && strtotime($row['due_date']) < time() && $row['status'] != '已付款';
                                $status_class = [
                                    '未付款' => 'danger',
                                    '部分付款' => 'warning',
                                    '已付款' => 'success',
                                    '已逾期' => 'danger'
                                ];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['bill_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['supplier_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['bill_date'])); ?></td>
                                    <td class="amount">¥<?php echo number_format($row['total_amount'], 2); ?></td>
                                    <td class="amount positive">¥<?php echo number_format($row['paid_amount'], 2); ?></td>
                                    <td class="amount negative">¥<?php echo number_format($row['outstanding_amount'], 2); ?></td>
                                    <td>
                                        <?php if ($is_overdue): ?>
                                            <span class="overdue">⚠️ <?php echo date('Y-m-d', strtotime($row['due_date'])); ?></span>
                                        <?php else: ?>
                                            <?php echo $row['due_date'] ? date('Y-m-d', strtotime($row['due_date'])) : '-'; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $status_class[$row['status']] ?? 'info'; ?>">
                                            <?php echo $row['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <?php if ($row['outstanding_amount'] > 0): ?>
                                                <button class="btn-sm" onclick="makePayment(<?php echo $row['id']; ?>)">付款</button>
                                            <?php endif; ?>
                                            <button class="btn-sm" onclick="viewDetail(<?php echo $row['id']; ?>, 'payable')">详情</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" style="text-align: center; color: #a0aec0; padding: 40px;">暂无应付账款数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================== 收付款记录标签页 ================== -->
        <div id="records" class="tab-content">
            <div class="content-card">
                <div class="card-header">
                    <h2 class="card-title">收付款记录</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>记录编号</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>付款方式</th>
                            <th>日期</th>
                            <th>操作员</th>
                            <th>备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_payments->num_rows > 0): ?>
                            <?php while ($row = $recent_payments->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['record_no']); ?></strong></td>
                                    <td>
                                        <span class="badge <?php echo $row['payment_type'] == '收款' ? 'success' : 'warning'; ?>">
                                            <?php echo $row['payment_type']; ?>
                                        </span>
                                    </td>
                                    <td class="amount <?php echo $row['payment_type'] == '收款' ? 'positive' : 'negative'; ?>">
                                        <?php echo $row['payment_type'] == '收款' ? '+' : '-'; ?>¥<?php echo number_format($row['amount'], 2); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['payment_method'] ?? '-'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($row['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['operator_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #a0aec0; padding: 40px;">暂无收付款记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        /**
         * 切换标签页
         */
        function switchTab(tabName) {
            // 隐藏所有标签内容
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // 移除所有按钮的激活状态
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 显示选中的标签内容
            document.getElementById(tabName).classList.add('active');
            
            // 激活对应的按钮
            event.target.classList.add('active');
        }

        /**
         * 新增应收款
         */
        function addReceivable() {
            window.location.href = 'payment_add_receivable.php';
        }

        /**
         * 新增应付款
         */
        function addPayable() {
            window.location.href = 'payment_add_payable.php';
        }

        /**
         * 收款操作
         */
        function receivePayment(id) {
            window.location.href = 'payment_receive.php?id=' + id;
        }

        /**
         * 付款操作
         */
        function makePayment(id) {
            window.location.href = 'payment_pay.php?id=' + id;
        }

        /**
         * 查看详情
         */
        function viewDetail(id, type) {
            alert('查看详情功能开发中...\nID: ' + id + '\n类型: ' + type);
            // 后续可以开发详情页面：window.location.href = 'payment_detail.php?id=' + id + '&type=' + type;
        }
    </script>
</body>
</html>