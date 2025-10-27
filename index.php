<?php
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

// 获取统计数据
$conn = getDBConnection();

// 客户总数
$customers_count = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];

// 供应商总数
$suppliers_count = $conn->query("SELECT COUNT(*) as count FROM suppliers WHERE is_active = 1")->fetch_assoc()['count'];

// 产品总数
$products_count = $conn->query("SELECT COUNT(*) as count FROM products WHERE is_active = 1")->fetch_assoc()['count'];

// 报价单总数（本月）
$quotes_count = $conn->query("SELECT COUNT(*) as count FROM quotes WHERE MONTH(created_at) = MONTH(CURRENT_DATE) AND YEAR(created_at) = YEAR(CURRENT_DATE)")->fetch_assoc()['count'];

// 维修任务总数（进行中）
$repair_count = $conn->query("SELECT COUNT(*) as count FROM repair_jobs WHERE current_status NOT IN ('已完成', '已取消')")->fetch_assoc()['count'];

// 应收账款总额（未收款+部分收款）
$receivable_amount = $conn->query("SELECT IFNULL(SUM(outstanding_amount), 0) as amount FROM accounts_receivable WHERE status IN ('未收款', '部分收款', '已逾期')")->fetch_assoc()['amount'];

// 应付账款总额（未付款+部分付款）
$payable_amount = $conn->query("SELECT IFNULL(SUM(outstanding_amount), 0) as amount FROM accounts_payable WHERE status IN ('未付款', '部分付款', '已逾期')")->fetch_assoc()['amount'];

// 逾期应收账款
$overdue_receivable = $conn->query("SELECT COUNT(*) as count FROM accounts_receivable WHERE status = '已逾期'")->fetch_assoc()['count'];

// 逾期应付账款
$overdue_payable = $conn->query("SELECT COUNT(*) as count FROM accounts_payable WHERE status = '已逾期'")->fetch_assoc()['count'];

// 公章数量
$seals_count = $conn->query("SELECT COUNT(*) as count FROM seals")->fetch_assoc()['count'];

// 最近的报价单
$recent_quotes = $conn->query("
    SELECT q.*, c.company_name 
    FROM quotes q 
    LEFT JOIN customers c ON q.customer_id = c.id 
    ORDER BY q.created_at DESC 
    LIMIT 5
");

// 最近的维修任务
$recent_repairs = $conn->query("
    SELECT r.*, c.company_name 
    FROM repair_jobs r 
    LEFT JOIN customers c ON r.customer_id = c.id 
    WHERE r.current_status NOT IN ('已完成', '已取消')
    ORDER BY r.created_at DESC 
    LIMIT 5
");

// 最近的应付账款
$recent_payables = $conn->query("
    SELECT * FROM accounts_payable 
    WHERE status IN ('未付款', '部分付款')
    ORDER BY due_date ASC 
    LIMIT 5
");

// 本月收入和支出趋势
$monthly_income = $conn->query("
    SELECT IFNULL(SUM(paid_amount), 0) as amount 
    FROM accounts_receivable 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE)
")->fetch_assoc()['amount'];

$monthly_expense = $conn->query("
    SELECT IFNULL(SUM(paid_amount), 0) as amount 
    FROM accounts_payable 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE) 
    AND YEAR(created_at) = YEAR(CURRENT_DATE)
")->fetch_assoc()['amount'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>企业管理系统 - 主页</title>
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
        }

        .navbar-brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-name {
            font-size: 14px;
            color: #4a5568;
        }

        .user-role {
            font-size: 12px;
            color: #a0aec0;
        }

        .btn-logout {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: #edf2f7;
        }

        /* 侧边栏 */
        .sidebar {
            position: fixed;
            left: 0;
            top: 64px;
            width: 260px;
            height: calc(100vh - 64px);
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 24px 0;
            overflow-y: auto;
        }

        .menu-section {
            margin-bottom: 32px;
        }

        .menu-title {
            padding: 0 24px;
            font-size: 12px;
            font-weight: 600;
            color: #a0aec0;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }

        .menu-item:hover {
            background: #f7fafc;
            color: #667eea;
        }

        .menu-item.active {
            background: #eef2ff;
            color: #667eea;
            border-right: 3px solid #667eea;
        }

        .menu-icon {
            font-size: 20px;
            width: 24px;
            text-align: center;
        }

        /* 主内容区 */
        .main-content {
            margin-left: 260px;
            margin-top: 64px;
            padding: 32px;
            min-height: calc(100vh - 64px);
        }

        /* 页面头部 */
        .page-header {
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header-left {
            flex: 1;
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

        .quick-actions {
            display: flex;
            gap: 12px;
        }

        .btn-quick {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        /* 统计卡片网格 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        }

        .stat-icon.red {
            background: linear-gradient(135deg, #fc8181 0%, #f56565 100%);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            background: #fee;
            color: #c53030;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-top: 8px;
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
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
        }

        /* 表格样式 */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            text-align: left;
            padding: 12px;
            font-size: 13px;
            font-weight: 600;
            color: #718096;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
        }

        tbody td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            color: #2d3748;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        /* 徽章 */
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge.warning {
            background: #fef5e7;
            color: #d69e2e;
        }

        .badge.danger {
            background: #fed7d7;
            color: #c53030;
        }

        .badge.info {
            background: #bee3f8;
            color: #2c5282;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        /* 响应式 */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        /* 警告框 */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-warning {
            background: #fef5e7;
            color: #d69e2e;
            border: 1px solid #fbd38d;
        }

        .alert-info {
            background: #ebf8ff;
            color: #2c5282;
            border: 1px solid #90cdf4;
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-brand-icon">🏢</div>
            <span>企业管理系统</span>
        </div>
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo mb_substr($nickname, 0, 1, 'UTF-8'); ?>
                </div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($nickname); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn-logout" onclick="return confirm('确定要退出登录吗？')">退出登录</a>
        </div>
    </nav>

    <!-- 侧边栏 -->
    <aside class="sidebar">
        <div class="menu-section">
            <div class="menu-title">概览</div>
            <a href="index.php" class="menu-item active">
                <span class="menu-icon">📊</span>
                <span>工作台</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">核心业务</div>
            <a href="customers.php" class="menu-item">
                <span class="menu-icon">👥</span>
                <span>客户管理</span>
            </a>
            <a href="products.php" class="menu-item">
                <span class="menu-icon">📦</span>
                <span>产品管理</span>
            </a>
            <a href="quotes.php" class="menu-item">
                <span class="menu-icon">💰</span>
                <span>报价管理</span>
            </a>
            <a href="suppliers.php" class="menu-item">
                <span class="menu-icon">🏪</span>
                <span>供应商管理</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">业务管理</div>
            <a href="deliveries.php" class="menu-item">
                <span class="menu-icon">🚚</span>
                <span>送货管理</span>
            </a>
            <a href="repair.php" class="menu-item">
                <span class="menu-icon">🔧</span>
                <span>维修管理</span>
            </a>
            <a href="finance.php" class="menu-item">
                <span class="menu-icon">💳</span>
                <span>财务管理</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">系统设置</div>
            <a href="seal_management.php" class="menu-item">
                <span class="menu-icon">🖊️</span>
                <span>公章管理</span>
            </a>
            <a href="users.php" class="menu-item">
                <span class="menu-icon">👤</span>
                <span>用户管理</span>
            </a>
        </div>
    </aside>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 页面头部 -->
        <div class="page-header">
            <div class="page-header-left">
                <h1 class="page-title">欢迎回来，<?php echo htmlspecialchars($nickname); ?>！👋</h1>
                <p class="page-subtitle">今天是 <?php echo date('Y年m月d日 l'); ?> · 让我们开始高效的一天</p>
            </div>
            <div class="quick-actions">
                <a href="quotes.php?action=new" class="btn-quick btn-primary">
                    ➕ 新建报价单
                </a>
                <a href="payment_add_payable.php" class="btn-quick btn-success">
                    💳 添加应付款
                </a>
            </div>
        </div>

        <!-- 警告提示 -->
        <?php if ($overdue_receivable > 0 || $overdue_payable > 0): ?>
            <div class="alert alert-warning">
                <span>⚠️</span>
                <div>
                    <?php if ($overdue_receivable > 0): ?>
                        <strong>逾期提醒：</strong>您有 <?php echo $overdue_receivable; ?> 笔应收账款已逾期
                    <?php endif; ?>
                    <?php if ($overdue_payable > 0): ?>
                        <?php echo $overdue_receivable > 0 ? '，' : '<strong>逾期提醒：</strong>'; ?>
                        <?php echo $overdue_payable; ?> 笔应付账款已逾期
                    <?php endif; ?>
                    ，请及时处理。
                </div>
            </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">👥</div>
                </div>
                <div class="stat-value"><?php echo $customers_count; ?></div>
                <div class="stat-label">客户总数</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green">🏪</div>
                </div>
                <div class="stat-value"><?php echo $suppliers_count; ?></div>
                <div class="stat-label">活跃供应商</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon green">📦</div>
                </div>
                <div class="stat-value"><?php echo $products_count; ?></div>
                <div class="stat-label">在售产品</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">💰</div>
                </div>
                <div class="stat-value"><?php echo $quotes_count; ?></div>
                <div class="stat-label">本月报价单</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange">🔧</div>
                </div>
                <div class="stat-value"><?php echo $repair_count; ?></div>
                <div class="stat-label">进行中的维修</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon blue">📈</div>
                </div>
                <div class="stat-value">¥<?php echo number_format($receivable_amount, 0); ?></div>
                <div class="stat-label">应收账款</div>
                <?php if ($overdue_receivable > 0): ?>
                    <div class="stat-badge">⚠️ <?php echo $overdue_receivable; ?> 笔逾期</div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange">📉</div>
                </div>
                <div class="stat-value">¥<?php echo number_format($payable_amount, 0); ?></div>
                <div class="stat-label">应付账款</div>
                <?php if ($overdue_payable > 0): ?>
                    <div class="stat-badge">⚠️ <?php echo $overdue_payable; ?> 笔逾期</div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon purple">🖊️</div>
                </div>
                <div class="stat-value"><?php echo $seals_count; ?></div>
                <div class="stat-label">电子公章</div>
            </div>
        </div>

        <!-- 财务概览 -->
        <div class="stats-grid" style="grid-template-columns: 1fr 1fr;">
            <div class="content-card" style="padding: 20px;">
                <h3 style="font-size: 16px; color: #48bb78; margin-bottom: 12px;">💚 本月收入</h3>
                <div style="font-size: 28px; font-weight: 700; color: #48bb78;">
                    ¥<?php echo number_format($monthly_income, 2); ?>
                </div>
            </div>
            <div class="content-card" style="padding: 20px;">
                <h3 style="font-size: 16px; color: #ed8936; margin-bottom: 12px;">🧡 本月支出</h3>
                <div style="font-size: 28px; font-weight: 700; color: #ed8936;">
                    ¥<?php echo number_format($monthly_expense, 2); ?>
                </div>
            </div>
        </div>

        <!-- 最近的报价单 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">📋 最近的报价单</h2>
                <a href="quotes.php" class="btn-quick btn-primary">查看全部</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>报价单号</th>
                        <th>客户名称</th>
                        <th>金额</th>
                        <th>状态</th>
                        <th>日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_quotes->num_rows > 0): ?>
                        <?php while ($quote = $recent_quotes->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($quote['quote_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($quote['company_name'] ?? '未指定客户'); ?></td>
                                <td><strong>¥<?php echo number_format($quote['final_amount'], 2); ?></strong></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        '草稿' => 'info',
                                        '已发送' => 'warning',
                                        '已成交' => 'success',
                                        '已过期' => 'danger'
                                    ];
                                    $class = $status_class[$quote['status']] ?? 'info';
                                    echo "<span class='badge $class'>{$quote['status']}</span>";
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($quote['quote_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">暂无报价单数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 进行中的维修任务 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">🔧 进行中的维修任务</h2>
                <a href="repair.php" class="btn-quick btn-primary">查看全部</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>任务编号</th>
                        <th>客户名称</th>
                        <th>设备名称</th>
                        <th>状态</th>
                        <th>开始日期</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_repairs->num_rows > 0): ?>
                        <?php while ($repair = $recent_repairs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($repair['job_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($repair['company_name']); ?></td>
                                <td><?php echo htmlspecialchars($repair['device_name']); ?></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        '待检测' => 'info',
                                        '检测中' => 'warning',
                                        '维修中' => 'warning',
                                        '已完成' => 'success',
                                        '已取消' => 'danger'
                                    ];
                                    $class = $status_class[$repair['current_status']] ?? 'info';
                                    echo "<span class='badge $class'>{$repair['current_status']}</span>";
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d', strtotime($repair['start_date'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">暂无进行中的维修任务</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 待付款项 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">💳 待付款项</h2>
                <a href="finance.php" class="btn-quick btn-primary">查看全部</a>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>账单编号</th>
                        <th>供应商</th>
                        <th>应付金额</th>
                        <th>到期日期</th>
                        <th>状态</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_payables->num_rows > 0): ?>
                        <?php while ($payable = $recent_payables->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($payable['bill_no']); ?></strong></td>
                                <td><?php echo htmlspecialchars($payable['supplier_name']); ?></td>
                                <td><strong>¥<?php echo number_format($payable['outstanding_amount'], 2); ?></strong></td>
                                <td>
                                    <?php 
                                    $due_date = $payable['due_date'];
                                    $is_overdue = $due_date && strtotime($due_date) < time();
                                    echo $due_date ? date('Y-m-d', strtotime($due_date)) : '-';
                                    if ($is_overdue) echo ' <span style="color: #c53030;">⚠️</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        '未付款' => 'warning',
                                        '部分付款' => 'info',
                                        '已付款' => 'success',
                                        '已逾期' => 'danger'
                                    ];
                                    $class = $status_class[$payable['status']] ?? 'info';
                                    echo "<span class='badge $class'>{$payable['status']}</span>";
                                    ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="empty-state">暂无待付款项</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>