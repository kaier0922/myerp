<?php
/**
 * ============================================================================
 * 文件名: deliveries.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 送货管理列表页面
 * 
 * 功能说明：
 * 1. 显示送货单列表
 * 2. 支持搜索和筛选（状态、日期、支付状态）
 * 3. 支持查看、编辑、删除送货单
 * 4. 显示应收应付统计
 * 5. 分页显示
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

// ==================== 获取筛选条件 ====================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$payment_status = isset($_GET['payment_status']) ? trim($_GET['payment_status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// ==================== 分页参数 ====================
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// ==================== 连接数据库 ====================
$conn = getDBConnection();

// ==================== 构建查询条件 ====================
$where_conditions = ["1=1"];
$params = [];
$param_types = '';

// 搜索条件
if (!empty($search)) {
    $where_conditions[] = "(delivery_no LIKE ? OR customer_name LIKE ? OR contact_phone LIKE ? OR delivery_address LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

// 状态筛选
if (!empty($status)) {
    $where_conditions[] = "status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// 支付状态筛选
if (!empty($payment_status)) {
    $where_conditions[] = "payment_status = ?";
    $params[] = $payment_status;
    $param_types .= 's';
}

// 日期范围筛选
if (!empty($date_from)) {
    $where_conditions[] = "delivery_date >= ?";
    $params[] = $date_from;
    $param_types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "delivery_date <= ?";
    $params[] = $date_to;
    $param_types .= 's';
}

$where_sql = implode(' AND ', $where_conditions);

// ==================== 查询总数 ====================
$count_sql = "SELECT COUNT(*) as total FROM deliveries WHERE {$where_sql}";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $count_result = $conn->query($count_sql);
    $total_records = $count_result->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $per_page);

// ==================== 查询送货单列表 ====================
$list_sql = "
    SELECT 
        id,
        delivery_no,
        customer_name,
        contact_name,
        contact_phone,
        delivery_address,
        delivery_date,
        delivery_time,
        delivery_person,
        status,
        total_amount,
        paid_amount,
        payment_status,
        collect_on_delivery,
        created_at
    FROM deliveries
    WHERE {$where_sql}
    ORDER BY delivery_date DESC, created_at DESC
    LIMIT ? OFFSET ?
";

if (!empty($params)) {
    $list_stmt = $conn->prepare($list_sql);
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    $list_stmt->bind_param($param_types, ...$params);
    $list_stmt->execute();
    $deliveries = $list_stmt->get_result();
    $list_stmt->close();
} else {
    $list_sql = "
        SELECT 
            id,
            delivery_no,
            customer_name,
            contact_name,
            contact_phone,
            delivery_address,
            delivery_date,
            delivery_time,
            delivery_person,
            status,
            total_amount,
            paid_amount,
            payment_status,
            collect_on_delivery,
            created_at
        FROM deliveries
        WHERE {$where_sql}
        ORDER BY delivery_date DESC, created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";
    $deliveries = $conn->query($list_sql);
}

// ==================== 统计数据 ====================
$stats_sql = "
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'delivering' THEN 1 ELSE 0 END) as delivering_count,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
        SUM(total_amount) as total_revenue,
        SUM(paid_amount) as total_paid,
        SUM(total_amount - paid_amount) as total_unpaid
    FROM deliveries
    WHERE {$where_sql}
";

if (!empty($params)) {
    $stats_params = array_slice($params, 0, -2);
    $stats_param_types = substr($param_types, 0, -2);
    
    $stats_stmt = $conn->prepare($stats_sql);
    if (!empty($stats_params)) {
        $stats_stmt->bind_param($stats_param_types, ...$stats_params);
    }
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
    $stats_stmt->close();
} else {
    $stats_result = $conn->query($stats_sql);
    $stats = $stats_result->fetch_assoc();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>送货管理</title>
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

        /* ==================== 主内容区 ==================== */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==================== 页面标题 ==================== */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        /* ==================== 统计卡片 ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
        }

        .stat-card.primary .stat-value { color: #667eea; }
        .stat-card.warning .stat-value { color: #f59e0b; }
        .stat-card.info .stat-value { color: #3b82f6; }
        .stat-card.success .stat-value { color: #10b981; }
        .stat-card.danger .stat-value { color: #ef4444; }

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

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-action {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: white;
            color: #4a5568;
            text-decoration: none;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            transition: all 0.2s;
            cursor: pointer;
        }

        .btn-action:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
            transform: scale(1.1);
        }

        .btn-delete {
            background: #fee;
            color: #c00;
            border-color: #fcc;
        }

        .btn-delete:hover {
            background: #fcc;
            color: #900;
            border-color: #faa;
        }

        /* ==================== 筛选卡片 ==================== */
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 6px;
        }

        .filter-input, .filter-select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
        }

        /* ==================== 数据卡片 ==================== */
        .data-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* ==================== 表格样式 ==================== */
        .table-wrapper {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            min-width: 1600px;
        }

        .table thead {
            background: #f7fafc;
        }

        .table th {
            padding: 16px 12px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            white-space: nowrap;
        }

        .table td {
            padding: 14px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table tbody tr:hover {
            background: #f7fafc;
        }

        /* 列宽设置 */
        .table th:nth-child(1), .table td:nth-child(1) { width: 40px; }    /* 复选框 */
        .table th:nth-child(2), .table td:nth-child(2) { width: 140px; }   /* 送货单号 */
        .table th:nth-child(3), .table td:nth-child(3) { width: 120px; }   /* 客户 */
        .table th:nth-child(4), .table td:nth-child(4) { width: 100px; }   /* 联系人 */
        .table th:nth-child(5), .table td:nth-child(5) { width: 120px; }   /* 电话 */
        .table th:nth-child(6), .table td:nth-child(6) { width: 250px; }   /* 送货地址 */
        .table th:nth-child(7), .table td:nth-child(7) { width: 110px; }   /* 送货日期 */
        .table th:nth-child(8), .table td:nth-child(8) { width: 80px; }    /* 送货人 */
        .table th:nth-child(9), .table td:nth-child(9) { width: 100px; }   /* 状态 */
        .table th:nth-child(10), .table td:nth-child(10) { width: 100px; } /* 总金额 */
        .table th:nth-child(11), .table td:nth-child(11) { width: 100px; } /* 支付状态 */
        .table th:nth-child(12), .table td:nth-child(12) { width: auto; }  /* 操作 */

        /* 地址列允许换行 */
        .table td:nth-child(6) {
            white-space: normal;
            word-break: break-word;
        }

        /* ==================== 标签样式 ==================== */
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-pending { background: #e0e7ff; color: #3730a3; }
        .badge-delivering { background: #dbeafe; color: #1e40af; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-failed { background: #fee2e2; color: #991b1b; }
        .badge-cancelled { background: #f3f4f6; color: #6b7280; }

        .badge-unpaid { background: #fee2e2; color: #991b1b; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-paid { background: #d1fae5; color: #065f46; }

        .badge-cod { background: #fef3c7; color: #92400e; font-size: 11px; }

        /* ==================== 分页样式 ==================== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 24px;
            padding: 24px;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            text-decoration: none;
            color: #4a5568;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #f7fafc;
            border-color: #cbd5e0;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        /* ==================== 空状态 ==================== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .empty-state-text {
            font-size: 16px;
            margin-bottom: 24px;
        }

        /* ==================== 操作按钮组 ==================== */
        .action-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: nowrap;
        }

        /* ==================== 响应式 ==================== */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 0 16px;
            }

            .main-content {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">🚚 送货管理</div>
        <div class="navbar-actions">
            <a href="index.php" class="btn btn-action">返回首页</a>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <!-- ==================== 页面标题 ==================== -->
        <div class="page-header">
            <h1 class="page-title">送货单列表</h1>
            <div>
                <a href="delivery_add.php" class="btn btn-primary">+ 新建送货单</a>
            </div>
        </div>

        <!-- ==================== 统计卡片 ==================== -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-label">总送货单</div>
                <div class="stat-value"><?php echo number_format($stats['total_count'] ?? 0); ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">待送货</div>
                <div class="stat-value"><?php echo number_format($stats['pending_count'] ?? 0); ?></div>
            </div>
            <div class="stat-card info">
                <div class="stat-label">配送中</div>
                <div class="stat-value"><?php echo number_format($stats['delivering_count'] ?? 0); ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">已完成</div>
                <div class="stat-value"><?php echo number_format($stats['completed_count'] ?? 0); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">总营收</div>
                <div class="stat-value">¥<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">已收款</div>
                <div class="stat-value">¥<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></div>
            </div>
            <div class="stat-card danger">
                <div class="stat-label">未收款</div>
                <div class="stat-value">¥<?php echo number_format($stats['total_unpaid'] ?? 0, 2); ?></div>
            </div>
        </div>

        <!-- ==================== 筛选卡片 ==================== -->
        <div class="filter-card">
            <form method="GET" action="deliveries.php">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">搜索</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="单号/客户/电话/地址" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">送货状态</label>
                        <select name="status" class="filter-select">
                            <option value="">全部状态</option>
                            <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>待送货</option>
                            <option value="delivering" <?php echo $status == 'delivering' ? 'selected' : ''; ?>>配送中</option>
                            <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>已完成</option>
                            <option value="failed" <?php echo $status == 'failed' ? 'selected' : ''; ?>>失败</option>
                            <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">支付状态</label>
                        <select name="payment_status" class="filter-select">
                            <option value="">全部状态</option>
                            <option value="unpaid" <?php echo $payment_status == 'unpaid' ? 'selected' : ''; ?>>未付款</option>
                            <option value="partial" <?php echo $payment_status == 'partial' ? 'selected' : ''; ?>>部分付款</option>
                            <option value="paid" <?php echo $payment_status == 'paid' ? 'selected' : ''; ?>>已付款</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">开始日期</label>
                        <input type="date" name="date_from" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">结束日期</label>
                        <input type="date" name="date_to" class="filter-input" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 搜索</button>
                    <a href="deliveries.php" class="btn btn-action">清除筛选</a>
                </div>
            </form>
        </div>

        <!-- ==================== 数据卡片 ==================== -->
        <div class="data-card">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" onclick="selectAll(this)" id="selectAllCheckbox"></th>
                            <th>送货单号</th>
                            <th>客户</th>
                            <th>联系人</th>
                            <th>电话</th>
                            <th>送货地址</th>
                            <th>送货日期</th>
                            <th>送货人</th>
                            <th>状态</th>
                            <th>总金额</th>
                            <th>支付状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($deliveries->num_rows > 0): ?>
                            <?php while ($delivery = $deliveries->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="delivery_ids[]" 
                                           value="<?php echo $delivery['id']; ?>" 
                                           class="delivery-checkbox">
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($delivery['delivery_no']); ?></strong>
                                    <?php if ($delivery['collect_on_delivery']): ?>
                                    <br><span class="badge badge-cod">货到付款</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($delivery['customer_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($delivery['contact_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($delivery['contact_phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($delivery['delivery_address'] ?? ''); ?></td>
                                <td>
                                    <?php echo $delivery['delivery_date']; ?>
                                    <?php if (!empty($delivery['delivery_time'])): ?>
                                    <br><small style="color: #718096;"><?php echo htmlspecialchars($delivery['delivery_time']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($delivery['delivery_person'] ?? '') ?: '-'; ?></td>
                                <td>
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
                                </td>
                                <td>
                                    <strong style="color: #047857;">¥<?php echo number_format($delivery['total_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <span class="badge badge-<?php echo $delivery['payment_status']; ?>">
                                        <?php 
                                        $payment_labels = [
                                            'unpaid' => '未付款',
                                            'partial' => '部分付',
                                            'paid' => '已付款'
                                        ];
                                        echo $payment_labels[$delivery['payment_status']] ?? $delivery['payment_status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- 查看 -->
                                        <a href="delivery_view.php?id=<?php echo $delivery['id']; ?>" 
                                           class="btn-action" title="查看详情">
                                            👁️
                                        </a>

                                        <!-- 编辑 -->
                                        <?php if ($delivery['status'] != 'completed' && $delivery['status'] != 'cancelled'): ?>
                                        <a href="delivery_edit.php?id=<?php echo $delivery['id']; ?>" 
                                           class="btn-action" title="编辑">
                                            ✏️
                                        </a>
                                        <?php endif; ?>

                                        <!-- 删除 -->
                                        <button onclick="deleteDelivery(<?php echo $delivery['id']; ?>, '<?php echo htmlspecialchars($delivery['delivery_no']); ?>')" 
                                                class="btn-action btn-delete" title="删除">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">🚚</div>
                                        <div class="empty-state-text">暂无送货单数据</div>
                                        <a href="delivery_add.php" class="btn btn-primary">
                                            创建第一个送货单
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==================== 分页 ==================== -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        ← 上一页
                    </a>
                <?php endif; ?>

                <?php 
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?page=1&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">1</a>
                    <?php if ($start_page > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status); ?>&payment_status=<?php echo urlencode($payment_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                        下一页 →
                    </a>
                <?php endif; ?>

                <span style="margin-left: 16px; color: #718096;">
                    共 <?php echo $total_records; ?> 条记录
                </span>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 送货管理页面加载 ===');

        /**
         * 全选/取消全选
         */
        function selectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.delivery-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        /**
         * 删除送货单
         */
        function deleteDelivery(deliveryId, deliveryNo) {
            if (!confirm(`确定要删除送货单 "${deliveryNo}" 吗？\n\n此操作将永久删除送货单及相关记录。`)) {
                return;
            }

            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = '⏳';

            fetch('delivery_delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${deliveryId}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('删除失败: ' + data.message);
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('删除错误:', error);
                alert('删除出错: ' + error.message);
                button.disabled = false;
                button.textContent = originalText;
            });
        }

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>