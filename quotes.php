<?php
/**
 * =====================================================
 * 文件名：quotes.php
 * 功能：报价单管理主页面
 * 描述：展示报价单列表，支持筛选、搜索、查看详情等操作
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

// 获取筛选条件
$status_filter = $_GET['status'] ?? '';
$template_filter = $_GET['template'] ?? '';
$search = $_GET['search'] ?? '';

// 构建查询条件
$where = "1=1";
if ($status_filter) {
    $where .= " AND q.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($template_filter) {
    $where .= " AND q.template_type = '" . $conn->real_escape_string($template_filter) . "'";
}
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $where .= " AND (q.quote_no LIKE '%$search_escaped%' 
                OR c.company_name LIKE '%$search_escaped%' 
                OR q.project_name LIKE '%$search_escaped%')";
}

// 获取报价单统计
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = '草稿' THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status = '已发送' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN status = '已成交' THEN 1 ELSE 0 END) as deal_count,
        SUM(CASE WHEN status = '已过期' THEN 1 ELSE 0 END) as expired_count,
        SUM(final_amount) as total_amount,
        SUM(CASE WHEN status = '已成交' THEN final_amount ELSE 0 END) as deal_amount
    FROM quotes
")->fetch_assoc();

// 获取报价单列表
$quotes = $conn->query("
    SELECT q.*, c.company_name, c.contact_name, c.phone
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE $where
    ORDER BY q.created_at DESC
    LIMIT 100
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报价单管理 - 企业管理系统</title>
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

        /* 统一按钮样式 */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            line-height: 1.5;
            white-space: nowrap;
        }

        .btn-back {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #edf2f7;
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

        .btn-sm {
            padding: 6px 14px;
            font-size: 13px;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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
            transition: all 0.3s;
        }

        .stat-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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

        /* 筛选栏 */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-label {
            font-size: 14px;
            color: #4a5568;
            font-weight: 500;
        }

        .filter-select, .filter-input {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        /* 内容卡片 */
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
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

        .badge.draft { background: #f3f4f6; color: #6b7280; }
        .badge.sent { background: #dbeafe; color: #1e3a8a; }
        .badge.deal { background: #d1fae5; color: #065f46; }
        .badge.expired { background: #fee2e2; color: #991b1b; }

        .badge.assembled_pc { background: #ede9fe; color: #5b21b6; }
        .badge.brand_pc { background: #dbeafe; color: #1e40af; }
        .badge.weak_current { background: #fef3c7; color: #92400e; }
        .badge.strong_current { background: #fed7aa; color: #9a3412; }

        /* 成功消息 */
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

        /* 响应式 */
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
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
            <span>💰</span>
            <span>报价单管理</span>
        </a>
        <div class="navbar-user">
            <a href="index.php" class="btn btn-back">← 返回主页</a>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 页面头部 -->
        <div class="page-header">
            <h1 class="page-title">💰 报价单管理</h1>
            <p class="page-subtitle">创建和管理报价单，跟踪报价进度</p>
        </div>

        <!-- 成功消息 -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-box-label">报价单总数</div>
                <div class="stat-box-value"><?php echo $stats['total_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">草稿</div>
                <div class="stat-box-value" style="color: #6b7280;"><?php echo $stats['draft_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">已发送</div>
                <div class="stat-box-value" style="color: #3b82f6;"><?php echo $stats['sent_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">已成交</div>
                <div class="stat-box-value" style="color: #10b981;"><?php echo $stats['deal_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">报价总额</div>
                <div class="stat-box-value" style="color: #667eea;">¥<?php echo number_format($stats['total_amount'] ?? 0, 0); ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">成交金额</div>
                <div class="stat-box-value" style="color: #10b981;">¥<?php echo number_format($stats['deal_amount'] ?? 0, 0); ?></div>
            </div>
        </div>

        <!-- 筛选栏 -->
        <div class="filter-bar">
            <div class="filter-item">
                <label class="filter-label">状态：</label>
                <select class="filter-select" onchange="filterByStatus(this.value)">
                    <option value="">全部状态</option>
                    <option value="草稿" <?php echo $status_filter == '草稿' ? 'selected' : ''; ?>>草稿</option>
                    <option value="已发送" <?php echo $status_filter == '已发送' ? 'selected' : ''; ?>>已发送</option>
                    <option value="已成交" <?php echo $status_filter == '已成交' ? 'selected' : ''; ?>>已成交</option>
                    <option value="已过期" <?php echo $status_filter == '已过期' ? 'selected' : ''; ?>>已过期</option>
                </select>
            </div>

            <div class="filter-item">
                <label class="filter-label">模板类型：</label>
                <select class="filter-select" onchange="filterByTemplate(this.value)">
                    <option value="">全部类型</option>
                    <option value="assembled_pc" <?php echo $template_filter == 'assembled_pc' ? 'selected' : ''; ?>>组装电脑</option>
                    <option value="brand_pc" <?php echo $template_filter == 'brand_pc' ? 'selected' : ''; ?>>品牌整机</option>
                    <option value="weak_current" <?php echo $template_filter == 'weak_current' ? 'selected' : ''; ?>>弱电工程</option>
                    <option value="strong_current" <?php echo $template_filter == 'strong_current' ? 'selected' : ''; ?>>强电工程</option>
                </select>
            </div>

            <div class="filter-item">
                <label class="filter-label">搜索：</label>
                <input type="text" class="filter-input" placeholder="单号/客户/项目" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       onkeypress="if(event.key==='Enter') searchQuotes(this.value)">
            </div>

            <button class="btn btn-primary" onclick="window.location.href='quote_add.php'">+ 新增报价单</button>
            <button class="btn btn-secondary" onclick="clearFilters()">清除筛选</button>
        </div>

        <!-- 报价单列表 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">报价单列表</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>报价单号</th>
                        <th>客户信息</th>
                        <th>项目名称</th>
                        <th>模板类型</th>
                        <th>报价金额</th>
                        <th>报价日期</th>
                        <th>有效期</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($quotes->num_rows > 0): ?>
                        <?php while ($row = $quotes->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['quote_no']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($row['company_name'] ?? '未指定客户'); ?>
                                    <?php if ($row['contact_name']): ?>
                                        <br><small style="color: #a0aec0;"><?php echo htmlspecialchars($row['contact_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['project_name']): ?>
                                        <div style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo htmlspecialchars($row['project_name']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $template_map = [
                                        'assembled_pc' => '组装电脑',
                                        'brand_pc' => '品牌整机',
                                        'weak_current' => '弱电工程',
                                        'strong_current' => '强电工程'
                                    ];
                                    $template_name = $template_map[$row['template_type']] ?? $row['template_type'];
                                    ?>
                                    <span class="badge <?php echo $row['template_type']; ?>">
                                        <?php echo $template_name; ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600;">¥<?php echo number_format($row['final_amount'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['quote_date'])); ?></td>
                                <td>
                                    <?php
                                    $valid_until = date('Y-m-d', strtotime($row['quote_date'] . ' + ' . $row['valid_days'] . ' days'));
                                    $is_expired = strtotime($valid_until) < time();
                                    $color = $is_expired ? '#ef4444' : '#718096';
                                    ?>
                                    <span style="color: <?php echo $color; ?>;">
                                        <?php echo $valid_until; ?>
                                        <?php if ($is_expired): ?>
                                            <small>(已过期)</small>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = [
                                        '草稿' => 'draft',
                                        '已发送' => 'sent',
                                        '已成交' => 'deal',
                                        '已过期' => 'expired'
                                    ];
                                    $class = $status_class[$row['status']] ?? 'draft';
                                    ?>
                                    <span class="badge <?php echo $class; ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm btn-secondary" onclick="viewQuote(<?php echo $row['id']; ?>)">查看</button>
                                        <?php if ($row['status'] != '已成交'): ?>
                                            <button class="btn btn-sm btn-secondary" onclick="editQuote(<?php echo $row['id']; ?>)">编辑</button>
                                        <?php endif; ?>
                                        <?php if ($row['status'] == '草稿'): ?>
                                            <button class="btn btn-sm btn-secondary" onclick="deleteQuote(<?php echo $row['id']; ?>)">删除</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #a0aec0; padding: 40px;">暂无报价单数据</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        /**
         * 按状态筛选
         */
        function filterByStatus(status) {
            const url = new URL(window.location.href);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location.href = url.toString();
        }

        /**
         * 按模板类型筛选
         */
        function filterByTemplate(template) {
            const url = new URL(window.location.href);
            if (template) {
                url.searchParams.set('template', template);
            } else {
                url.searchParams.delete('template');
            }
            window.location.href = url.toString();
        }

        /**
         * 搜索报价单
         */
        function searchQuotes(keyword) {
            const url = new URL(window.location.href);
            if (keyword) {
                url.searchParams.set('search', keyword);
            } else {
                url.searchParams.delete('search');
            }
            window.location.href = url.toString();
        }

        /**
         * 清除筛选
         */
        function clearFilters() {
            window.location.href = 'quotes.php';
        }

        /**
         * 查看报价单
         */
        function viewQuote(id) {
            window.location.href = 'quote_view.php?id=' + id;
        }

        /**
         * 编辑报价单
         */
        function editQuote(id) {
            window.location.href = 'quote_edit.php?id=' + id;
        }

        /**
         * 删除报价单
         */
        function deleteQuote(id) {
            if (confirm('确定要删除这个报价单吗？此操作不可撤销。')) {
                window.location.href = 'quote_delete.php?id=' + id;
            }
        }
    </script>
</body>
</html>