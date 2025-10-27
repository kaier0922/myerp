<?php
/**
 * =====================================================
 * 文件名：suppliers.php
 * 功能：供应商列表管理
 * 描述：显示、搜索、筛选供应商列表，支持添加、编辑、删除
 * 版本：1.0
 * 更新日期：2025-10-22
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

// ==================== 获取消息 ====================
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';

if ($success_message) unset($_SESSION['success_message']);
if ($error_message) unset($_SESSION['error_message']);

// ==================== 获取筛选参数 ====================
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$page_size = 20;
$offset = ($page - 1) * $page_size;

// ==================== 构建查询条件 ====================
$where_conditions = [];
$params = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(company_name LIKE ? OR contact_person LIKE ? OR contact_phone LIKE ? OR supplier_code LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

if ($status === 'active') {
    $where_conditions[] = "is_active = 1";
} elseif ($status === 'inactive') {
    $where_conditions[] = "is_active = 0";
}

$where_sql = '';
if (count($where_conditions) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

// ==================== 查询总数 ====================
$count_sql = "SELECT COUNT(*) as total FROM suppliers {$where_sql}";

$count_stmt = $conn->prepare($count_sql);
if (count($params) > 0) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_row = $total_result->fetch_assoc();
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $page_size);
$count_stmt->close();

// ==================== 查询供应商列表 ====================
$sql = "
    SELECT *
    FROM suppliers
    {$where_sql}
    ORDER BY updated_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$params[] = $page_size;
$params[] = $offset;
$param_types .= 'ii';

if (count($params) > 0) {
    $stmt->bind_param($param_types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$suppliers = [];

while ($row = $result->fetch_assoc()) {
    $suppliers[] = $row;
}

$stmt->close();

// ==================== 获取统计信息 ====================
$stats_result = $conn->query("
    SELECT 
        COUNT(*) as total_suppliers,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_suppliers,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_suppliers
    FROM suppliers
");
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>供应商管理 - 企业管理系统</title>
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
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* 页面头部 */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
        }

        /* 按钮样式 */
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

        .btn-small {
            padding: 6px 10px;
            font-size: 12px;
            white-space: nowrap;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: nowrap;
            align-items: center;
        }

        /* 搜索筛选区域 */
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .filter-row {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: nowrap;
        }

        .filter-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .filter-group.filter-buttons {
            flex: 0 0 auto;
            flex-direction: row;
            gap: 12px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
        }

        .form-control {
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* 表格容器 */
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table thead {
            background: #f7fafc;
        }

        .table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .table td:last-child {
            white-space: nowrap;
        }

        /* 公司名称列 - 限制宽度 */
        .table td:nth-child(2) {
            max-width: 180px;
        }

        .table td:nth-child(2) strong {
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 地址列 - 限制宽度并隐藏溢出 */
        .table td:nth-child(5) {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* 状态列 - 不换行 */
        .table td:nth-child(7) {
            white-space: nowrap;
        }

        .table tbody tr:hover {
            background: #f7fafc;
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* 联系信息样式 */
        .contact-info {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        /* 徽章样式 */
        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border-radius: 6px;
            text-decoration: none;
            color: #4a5568;
            font-size: 14px;
            transition: all 0.2s;
        }

        .pagination a:hover {
            background: #f7fafc;
        }

        .pagination span.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .empty-state-description {
            color: #718096;
            font-size: 14px;
        }

        /* 消息提示 */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            position: relative;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .alert-close {
            margin-left: auto;
            cursor: pointer;
            font-size: 20px;
            font-weight: bold;
            color: inherit;
            opacity: 0.6;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .navbar {
                padding: 0 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group.filter-buttons {
                flex-direction: row;
                justify-content: flex-start;
            }

            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <div class="navbar-brand-icon">📊</div>
            <span>企业管理系统</span>
        </a>
        <a href="index.php" class="btn-back">← 返回主页</a>
    </nav>

    <main class="main-content">
        <!-- 页面头部 -->
        <div class="page-header">
            <h1 class="page-title">🏢 供应商管理</h1>
            <a href="supplier_add.php" class="btn btn-primary">+ 新增供应商</a>
        </div>

        <!-- 消息提示 -->
        <?php if ($success_message): ?>
            <div class="alert alert-success" id="successAlert">
                <span>✓</span>
                <span><?php echo htmlspecialchars($success_message); ?></span>
                <span class="alert-close" onclick="this.parentElement.remove()">×</span>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error" id="errorAlert">
                <span>✗</span>
                <span><?php echo htmlspecialchars($error_message); ?></span>
                <span class="alert-close" onclick="this.parentElement.remove()">×</span>
            </div>
        <?php endif; ?>

        <!-- 统计卡片 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">全部供应商</div>
                <div class="stat-value"><?php echo $stats['total_suppliers']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">合作中</div>
                <div class="stat-value"><?php echo $stats['active_suppliers']; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">已停用</div>
                <div class="stat-value"><?php echo $stats['inactive_suppliers']; ?></div>
            </div>
        </div>

        <!-- 搜索筛选 -->
        <div class="filter-section">
            <form method="GET" action="suppliers.php">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>搜索</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="公司名称、联系人、电话、编号" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>状态</label>
                        <select name="status" class="form-control">
                            <option value="all" <?php echo ($status == 'all') ? 'selected' : ''; ?>>全部状态</option>
                            <option value="active" <?php echo ($status == 'active') ? 'selected' : ''; ?>>合作中</option>
                            <option value="inactive" <?php echo ($status == 'inactive') ? 'selected' : ''; ?>>已停用</option>
                        </select>
                    </div>
                    
                    <div class="filter-group filter-buttons">
                        <button type="submit" class="btn btn-primary">🔍 搜索</button>
                        <a href="suppliers.php" class="btn btn-secondary">🔄 重置</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 供应商列表 -->
        <div class="table-container">
            <?php if (count($suppliers) > 0): ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th width="120">供应商编号</th>
                            <th width="180">公司名称</th>
                            <th width="100">联系人</th>
                            <th width="130">联系电话</th>
                            <th width="250">地址</th>
                            <th width="100">账期(天)</th>
                            <th width="90">状态</th>
                            <th width="240">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($supplier['supplier_code']); ?></td>
                                <td title="<?php echo htmlspecialchars($supplier['company_name']); ?>">
                                    <strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong>
                                    <?php if ($supplier['tax_number']): ?>
                                        <div class="contact-info">
                                            税号: <?php echo htmlspecialchars($supplier['tax_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                                <td><?php echo htmlspecialchars($supplier['contact_phone']); ?></td>
                                <td title="<?php echo htmlspecialchars($supplier['address']); ?>">
                                    <?php echo htmlspecialchars($supplier['address']); ?>
                                </td>
                                <td><?php echo $supplier['payment_terms']; ?> 天</td>
                                <td>
                                    <?php if ($supplier['is_active'] == 1): ?>
                                        <span class="badge badge-success">合作中</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">已停用</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="supplier_view.php?id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-secondary btn-small">👁️ 查看</a>
                                        <a href="supplier_edit.php?id=<?php echo $supplier['id']; ?>" 
                                           class="btn btn-secondary btn-small">✏️ 编辑</a>
                                        <?php if ($supplier['is_active'] == 1): ?>
                                            <button onclick="deleteSupplier(<?php echo $supplier['id']; ?>)" 
                                                    class="btn btn-secondary btn-small">🗑️ 停用</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- 分页 -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                ← 上一页
                            </a>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                下一页 →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">🏢</div>
                    <div class="empty-state-title">暂无供应商</div>
                    <div class="empty-state-description">
                        <?php if ($search || $status != 'all'): ?>
                            没有找到符合条件的供应商，请尝试其他搜索条件
                        <?php else: ?>
                            还没有添加任何供应商，点击"新增供应商"开始添加
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // 自动隐藏消息提示（3秒后）
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.getElementById('successAlert');
            const errorAlert = document.getElementById('errorAlert');
            
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.transition = 'opacity 0.5s';
                    successAlert.style.opacity = '0';
                    setTimeout(() => successAlert.remove(), 500);
                }, 3000);
            }
            
            if (errorAlert) {
                setTimeout(() => {
                    errorAlert.style.transition = 'opacity 0.5s';
                    errorAlert.style.opacity = '0';
                    setTimeout(() => errorAlert.remove(), 500);
                }, 5000);
            }
        });

        /**
         * 停用供应商
         */
        async function deleteSupplier(id) {
            if (!confirm('确定要停用这个供应商吗？\n\n供应商将被标记为"已停用"状态，不会真正删除数据。')) {
                return;
            }
            
            try {
                const response = await fetch('supplier_delete.php?id=' + id + '&ajax=1', {
                    method: 'GET'
                });
                
                if (!response.ok) {
                    throw new Error('网络请求失败');
                }
                
                const result = await response.json();
                
                if (result.success) {
                    alert('✓ ' + result.message);
                    window.location.reload();
                } else {
                    alert('✗ 停用失败：' + result.message);
                }
            } catch (error) {
                console.error('停用错误:', error);
                alert('✗ 停用出错，请重试');
            }
        }
    </script>
</body>
</html>
