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
    ORDER BY r.created_at DESC 
    LIMIT 5
");

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

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .stat-icon.blue { background: #eef2ff; }
        .stat-icon.green { background: #f0fdf4; }
        .stat-icon.purple { background: #faf5ff; }
        .stat-icon.orange { background: #fff7ed; }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
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
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* 表格 */
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

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge.success { background: #d1fae5; color: #065f46; }
        .badge.warning { background: #fed7aa; color: #92400e; }
        .badge.info { background: #dbeafe; color: #1e3a8a; }
        .badge.danger { background: #fee2e2; color: #991b1b; }

        /* 响应式 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <div class="navbar-brand">
            <div class="navbar-brand-icon">📊</div>
            <span>企业管理系统</span>
        </div>
        <div class="navbar-user">
            <div class="user-info">
                <div class="user-avatar"><?php echo mb_substr($nickname, 0, 1); ?></div>
                <div>
                    <div class="user-name"><?php echo htmlspecialchars($nickname); ?></div>
                    <div class="user-role">
                        <?php 
                        $role_names = ['admin' => '管理员', 'employee' => '员工', 'technician' => '技师'];
                        echo $role_names[$role] ?? $role;
                        ?>
                    </div>
                </div>
            </div>
            <button class="btn-logout" onclick="logout()">退出登录</button>
        </div>
    </nav>

    <!-- 侧边栏 -->
    <aside class="sidebar">
        <div class="menu-section">
            <div class="menu-title">主要功能</div>
            <a href="#" class="menu-item active" onclick="showPage('dashboard')">
                <span class="menu-icon">🏠</span>
                <span>仪表盘</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('customers')">
                <span class="menu-icon">👥</span>
                <span>客户管理</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('products')">
                <span class="menu-icon">📦</span>
                <span>产品管理</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('quotes')">
                <span class="menu-icon">💰</span>
                <span>报价管理</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('suppliers')">
                <span class="menu-icon">🏪</span>
                <span>供应商管理</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">业务管理</div>
            <a href="#" class="menu-item" onclick="showPage('delivery')">
                <span class="menu-icon">🚚</span>
                <span>送货管理</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('repair')">
                <span class="menu-icon">🔧</span>
                <span>维修管理</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('finance')">
                <span class="menu-icon">💳</span>
                <span>财务管理</span>
            </a>
        </div>

        <div class="menu-section">
            <div class="menu-title">系统设置</div>
            <a href="#" class="menu-item" onclick="showPage('users')">
                <span class="menu-icon">👤</span>
                <span>用户管理</span>
            </a>
            <a href="#" class="menu-item" onclick="showPage('seals')">
                <span class="menu-icon">🏢</span>
                <span>公章管理</span>
            </a>
        </div>
    </aside>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">欢迎回来，<?php echo htmlspecialchars($nickname); ?>！</h1>
            <p class="page-subtitle">今天是 <?php echo date('Y年m月d日 l'); ?></p>
        </div>

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
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon orange">📉</div>
                </div>
                <div class="stat-value">¥<?php echo number_format($payable_amount, 0); ?></div>
                <div class="stat-label">应付账款</div>
            </div>
        </div>

        <!-- 最近的报价单 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">最近的报价单</h2>
                <button class="btn-primary" onclick="showPage('quotes')">查看全部</button>
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
                                <td>¥<?php echo number_format($quote['final_amount'], 2); ?></td>
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
                            <td colspan="5" style="text-align: center; color: #a0aec0;">暂无报价单数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 最近的维修任务 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">最近的维修任务</h2>
                <button class="btn-primary" onclick="showPage('repair')">查看全部</button>
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
                            <td colspan="5" style="text-align: center; color: #a0aec0;">暂无维修任务数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function showPage(page) {
            // 移除所有激活状态
            document.querySelectorAll('.menu-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // 添加激活状态到当前点击的菜单
            event.target.closest('.menu-item').classList.add('active');
            
            // 根据不同的页面跳转
            const pages = {
                'dashboard': 'index.php',
                'customers': 'customers.php',
                'products': 'products.php',
                'quotes': 'quotes.php',
                'suppliers': 'suppliers.php',
                'delivery': 'deliveries.php',
                'repair': 'repair.php',
                'finance': 'finance.php',
                'users': 'users.php',
                'seals': 'seals.php'
            };
            
            if (pages[page]) {
                window.location.href = pages[page];
            } else {
                alert('即将跳转到：' + page + ' 页面\n（功能开发中...）');
            }
        }

        function logout() {
            if (confirm('确定要退出登录吗？')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>