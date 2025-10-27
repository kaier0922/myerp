<?php
/**
 * =====================================================
 * 文件名：repair.php
 * 功能：维修管理主页面
 * 描述：展示维修单列表，支持筛选、查看详情、编辑等操作
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
$search = $_GET['search'] ?? '';

// 构建查询条件
$where = "1=1";
if ($status_filter) {
    $where .= " AND ro.status = '" . $conn->real_escape_string($status_filter) . "'";
}
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $where .= " AND (ro.order_no LIKE '%$search_escaped%' 
                OR ro.customer_name LIKE '%$search_escaped%' 
                OR ro.device_type LIKE '%$search_escaped%')";
}

// 获取维修单统计
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'repairing' THEN 1 ELSE 0 END) as repairing_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(total_fee) as total_revenue
    FROM repair_orders
")->fetch_assoc();

// 获取维修单列表
$repairs = $conn->query("
    SELECT ro.*, c.company_name
    FROM repair_orders ro
    LEFT JOIN customers c ON ro.customer_id = c.id
    WHERE $where
    ORDER BY ro.receive_date DESC
    LIMIT 100
");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>维修管理 - 企业管理系统</title>
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

        /* 统一按钮样式 - 修复大小不一致问题 */
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

        .btn-sm {
            padding: 6px 14px;
            font-size: 13px;
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-sm:hover {
            background: #edf2f7;
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

        /* 统一按钮样式 - 修复大小不一致问题 */
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

        /* 移除 btn-sm 的特殊尺寸，使用统一尺寸 */
        .btn-sm {
            /* 使用与 .btn 相同的 padding */
            padding: 10px 20px;
            font-size: 14px;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* 操作按钮使用较小尺寸 */
        .btn-group .btn {
            padding: 6px 14px;
            font-size: 13px;
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

        .badge.pending { background: #fef3c7; color: #92400e; }
        .badge.repairing { background: #dbeafe; color: #1e3a8a; }
        .badge.testing { background: #e0e7ff; color: #3730a3; }
        .badge.completed { background: #d1fae5; color: #065f46; }
        .badge.delivered { background: #d1fae5; color: #065f46; }
        .badge.cancelled { background: #fee2e2; color: #991b1b; }

        .badge.onsite { background: #fef3c7; color: #92400e; }
        .badge.inshop { background: #dbeafe; color: #1e3a8a; }

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
            <span>🔧</span>
            <span>维修管理系统</span>
        </a>
        <div class="navbar-user">
            <a href="index.php" class="btn btn-back">← 返回主页</a>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 页面头部 -->
        <div class="page-header">
            <h1 class="page-title">🔧 维修管理</h1>
            <p class="page-subtitle">管理维修单，跟踪维修进度</p>
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
                <div class="stat-box-label">维修单总数</div>
                <div class="stat-box-value"><?php echo $stats['total_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">待处理</div>
                <div class="stat-box-value" style="color: #f59e0b;"><?php echo $stats['pending_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">维修中</div>
                <div class="stat-box-value" style="color: #3b82f6;"><?php echo $stats['repairing_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">已完成</div>
                <div class="stat-box-value" style="color: #10b981;"><?php echo $stats['completed_count'] ?? 0; ?></div>
            </div>
            <div class="stat-box">
                <div class="stat-box-label">总收入</div>
                <div class="stat-box-value" style="color: #10b981;">¥<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- 筛选栏 -->
                <!-- 筛选栏 -->
        <div class="filter-bar">
            <div class="filter-item">
                <label class="filter-label">状态：</label>
                <select class="filter-select" onchange="filterByStatus(this.value)">
                    <option value="">全部状态</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>待处理</option>
                    <option value="repairing" <?php echo $status_filter == 'repairing' ? 'selected' : ''; ?>>维修中</option>
                    <option value="testing" <?php echo $status_filter == 'testing' ? 'selected' : ''; ?>>测试中</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>已完成</option>
                    <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>已交付</option>
                </select>
            </div>

            <div class="filter-item">
                <label class="filter-label">搜索：</label>
                <input type="text" class="filter-input" placeholder="单号/客户/设备" 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       onkeypress="if(event.key==='Enter') searchRepairs(this.value)">
            </div>

            <button class="btn btn-primary" onclick="window.location.href='repair_add.php'">+ 新增维修单</button>
            <button class="btn btn-secondary btn-sm" onclick="clearFilters()">清除筛选</button>
        </div>


        <!-- 维修单列表 -->
        <div class="content-card">
            <div class="card-header">
                <h2 class="card-title">维修单列表</h2>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>维修单号</th>
                        <th>类型</th>
                        <th>客户名称</th>
                        <th>设备信息</th>
                        <th>故障描述</th>
                        <th>维修费用</th>
                        <th>接收日期</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($repairs->num_rows > 0): ?>
                        <?php while ($row = $repairs->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['order_no']); ?></strong></td>
                                <td>
                                    <span class="badge <?php echo $row['repair_type']; ?>">
                                        <?php echo $row['repair_type'] == 'onsite' ? '上门服务' : '带回维修'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['customer_name']); ?>
                                    <br><small style="color: #a0aec0;"><?php echo htmlspecialchars($row['contact_phone']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($row['device_type'] ?? '-'); ?>
                                    <?php if ($row['device_brand']): ?>
                                        <br><small style="color: #a0aec0;"><?php echo htmlspecialchars($row['device_brand'] . ' ' . $row['device_model']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?php echo htmlspecialchars($row['fault_description']); ?>
                                    </div>
                                </td>
                                <td style="font-weight: 600;">¥<?php echo number_format($row['total_fee'], 2); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($row['receive_date'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $row['status']; ?>">
                                        <?php 
                                        $status_map = [
                                            'pending' => '待处理',
                                            'repairing' => '维修中',
                                            'testing' => '测试中',
                                            'completed' => '已完成',
                                            'delivered' => '已交付',
                                            'cancelled' => '已取消'
                                        ];
                                        echo $status_map[$row['status']] ?? $row['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn btn-sm" onclick="viewRepair(<?php echo $row['id']; ?>)">查看</button>
                                        <?php if ($row['status'] != 'delivered' && $row['status'] != 'cancelled'): ?>
                                            <button class="btn btn-sm" onclick="editRepair(<?php echo $row['id']; ?>)">编辑</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #a0aec0; padding: 40px;">暂无维修单数据</td>
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
         * 搜索维修单
         */
        function searchRepairs(keyword) {
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
            window.location.href = 'repair.php';
        }

        /**
         * 查看维修单
         */
        function viewRepair(id) {
            window.location.href = 'repair_view.php?id=' + id;
        }

        /**
         * 编辑维修单
         */
        function editRepair(id) {
            window.location.href = 'repair_edit.php?id=' + id;
        }
    </script>
</body>
</html>