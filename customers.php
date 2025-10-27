<?php
/**
 * ============================================================================
 * 文件名: customers.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 客户管理列表页面
 * 
 * 功能说明：
 * 1. 显示客户列表
 * 2. 支持搜索和筛选
 * 3. 支持添加、编辑、删除客户
 * 4. 支持批量删除
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

// 搜索条件（公司名称、联系人、电话）
if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR contact_name LIKE ? OR phone LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

$where_sql = implode(' AND ', $where_conditions);

// ==================== 查询总数 ====================
$count_sql = "SELECT COUNT(*) as total FROM customers WHERE {$where_sql}";

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

// ==================== 查询客户列表 ====================
$list_sql = "
    SELECT 
        id,
        company_name,
        contact_name,
        phone,
        email,
        address,
        created_at
    FROM customers
    WHERE {$where_sql}
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
";

if (!empty($params)) {
    $list_stmt = $conn->prepare($list_sql);
    $params[] = $per_page;
    $params[] = $offset;
    $param_types .= 'ii';
    $list_stmt->bind_param($param_types, ...$params);
    $list_stmt->execute();
    $customers = $list_stmt->get_result();
    $list_stmt->close();
} else {
    $list_sql = "
        SELECT 
            id,
            company_name,
            contact_name,
            phone,
            email,
            address,
            created_at
        FROM customers
        WHERE {$where_sql}
        ORDER BY created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";
    $customers = $conn->query($list_sql);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客户管理</title>
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
            max-width: 1600px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==================== 页面标题 ==================== */
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
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

        .filter-group {
            display: flex;
            gap: 16px;
            align-items: flex-end;
        }

        .filter-input-group {
            flex: 1;
            max-width: 400px;
        }

        .filter-label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 6px;
            display: block;
        }

        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            min-width: 1200px;
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
        .table th:nth-child(1),
        .table td:nth-child(1) { width: 40px; }    /* 复选框 */
        
        .table th:nth-child(2),
        .table td:nth-child(2) { width: 60px; }    /* ID */
        
        .table th:nth-child(3),
        .table td:nth-child(3) { width: 320px; }   /* 公司名称 */
        
        .table th:nth-child(4),
        .table td:nth-child(4) { width: 120px; }   /* 联系人 */
        
        .table th:nth-child(5),
        .table td:nth-child(5) { width: 140px; }   /* 电话 */
        
        .table th:nth-child(6),
        .table td:nth-child(6) { width: 180px; }   /* 邮箱 */
        
        .table th:nth-child(7),
        .table td:nth-child(7) { width: auto; }    /* 地址 */
        
        .table th:nth-child(8),
        .table td:nth-child(8) { width: 150px; }   /* 创建时间 */
        
        .table th:nth-child(9),
        .table td:nth-child(9) { width: 120px; }   /* 操作 */

        /* 公司名称和地址允许换行 */
        .table td:nth-child(3),
        .table td:nth-child(7) {
            white-space: normal;
            word-break: break-word;
        }

        /* 操作列不换行 */
        .table td:nth-child(9) {
            white-space: nowrap;
        }

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

        /* ==================== 批量操作 ==================== */
        .batch-actions {
            padding: 16px 24px;
            background: #f7fafc;
            border-top: 1px solid #e2e8f0;
            display: none;
        }

        .batch-actions.active {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .selected-count {
            color: #4a5568;
            font-size: 14px;
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
            .navbar {
                padding: 0 16px;
            }

            .main-content {
                padding: 16px;
            }

            .filter-group {
                flex-direction: column;
            }

            .filter-input-group {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">👥 客户管理</div>
        <div class="navbar-actions">
            <a href="index.php" class="btn btn-action">返回首页</a>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <!-- ==================== 页面标题 ==================== -->
        <div class="page-header">
            <h1 class="page-title">客户列表</h1>
            <div>
                <a href="customer_add.php" class="btn btn-success">+ 添加客户</a>
            </div>
        </div>

        <!-- ==================== 筛选卡片 ==================== -->
        <div class="filter-card">
            <form method="GET" action="customers.php">
                <div class="filter-group">
                    <div class="filter-input-group">
                        <label class="filter-label">搜索</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="公司名称、联系人或电话" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">🔍 搜索</button>
                    <a href="customers.php" class="btn btn-action">清除</a>
                </div>
            </form>
        </div>

        <!-- ==================== 数据卡片 ==================== -->
        <div class="data-card">
            <div class="table-wrapper">
                <table class="table">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" onclick="selectAll(this)" id="selectAllCheckbox">
                            </th>
                            <th>ID</th>
                            <th>公司名称</th>
                            <th>联系人</th>
                            <th>电话</th>
                            <th>邮箱</th>
                            <th>地址</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customers->num_rows > 0): ?>
                            <?php while ($customer = $customers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="customer_ids[]" 
                                           value="<?php echo $customer['id']; ?>" 
                                           class="customer-checkbox"
                                           onchange="updateBatchActions()">
                                </td>
                                <td><?php echo $customer['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($customer['company_name'] ?? ''); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($customer['contact_name'] ?? '') ?: '-'; ?></td>
                                <td><?php echo htmlspecialchars($customer['phone'] ?? '') ?: '-'; ?></td>
                                <td><?php echo htmlspecialchars($customer['email'] ?? '') ?: '-'; ?></td>
                                <td><?php echo htmlspecialchars($customer['address'] ?? '') ?: '-'; ?></td>
                                <td style="color: #718096; font-size: 13px;">
                                    <?php echo date('Y-m-d H:i', strtotime($customer['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- 编辑 -->
                                        <a href="customer_edit.php?id=<?php echo $customer['id']; ?>" 
                                           class="btn-action" title="编辑">
                                            ✏️
                                        </a>

                                        <!-- 删除 -->
                                        <button onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['company_name']); ?>')" 
                                                class="btn-action btn-delete" title="删除">
                                            🗑️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">
                                    <div class="empty-state">
                                        <div class="empty-state-icon">👥</div>
                                        <div class="empty-state-text">暂无客户数据</div>
                                        <a href="customer_add.php" class="btn btn-success">
                                            添加第一个客户
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==================== 批量操作栏 ==================== -->
            <div class="batch-actions" id="batchActions">
                <span class="selected-count">
                    已选择 <strong id="selectedCount">0</strong> 项
                </span>
                <button onclick="batchDelete()" class="btn btn-danger">批量删除</button>
                <button onclick="clearSelection()" class="btn btn-action">取消选择</button>
            </div>

            <!-- ==================== 分页 ==================== -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">
                        ← 上一页
                    </a>
                <?php endif; ?>

                <?php 
                $show_pages = 5;
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?page=1&search=<?php echo urlencode($search); ?>">1</a>
                    <?php if ($start_page > 2): ?>
                        <span>...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span>...</span>
                    <?php endif; ?>
                    <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">
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
        console.log('=== 客户管理页面加载 ===');

        /**
         * 全选/取消全选
         */
        function selectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBatchActions();
        }

        /**
         * 更新批量操作栏
         */
        function updateBatchActions() {
            const checkboxes = document.querySelectorAll('.customer-checkbox:checked');
            const count = checkboxes.length;
            const batchActions = document.getElementById('batchActions');
            const selectedCount = document.getElementById('selectedCount');

            selectedCount.textContent = count;

            if (count > 0) {
                batchActions.classList.add('active');
            } else {
                batchActions.classList.remove('active');
            }

            const allCheckboxes = document.querySelectorAll('.customer-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            selectAllCheckbox.checked = (count > 0 && count === allCheckboxes.length);
        }

        /**
         * 清除选择
         */
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.customer-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
            updateBatchActions();
        }

        /**
         * 删除单个客户
         */
        function deleteCustomer(customerId, companyName) {
            if (!confirm(`确定要删除客户 "${companyName}" 吗？\n\n此操作将永久删除客户信息。`)) {
                return;
            }

            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = '⏳';

            fetch('customer_delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${customerId}`
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

        /**
         * 批量删除
         */
        function batchDelete() {
            const checkboxes = document.querySelectorAll('.customer-checkbox:checked');

            if (checkboxes.length === 0) {
                alert('请先选择要删除的客户');
                return;
            }

            if (!confirm(`确定要删除选中的 ${checkboxes.length} 个客户吗？\n\n此操作将永久删除这些客户信息。`)) {
                return;
            }

            const ids = Array.from(checkboxes).map(cb => cb.value);
            let deleted = 0;
            let failed = 0;

            const batchActions = document.getElementById('batchActions');
            batchActions.innerHTML = `<span>正在删除... 0/${ids.length}</span>`;

            const deleteNext = (index) => {
                if (index >= ids.length) {
                    if (failed === 0) {
                        alert(`批量删除完成！\n成功删除 ${deleted} 个客户。`);
                    } else {
                        alert(`批量删除完成！\n成功: ${deleted}\n失败: ${failed}`);
                    }
                    window.location.reload();
                    return;
                }

                batchActions.innerHTML = `<span>正在删除... ${index + 1}/${ids.length}</span>`;

                fetch('customer_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `id=${ids[index]}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        deleted++;
                    } else {
                        failed++;
                        console.error(`删除 ID ${ids[index]} 失败:`, data.message);
                    }
                    deleteNext(index + 1);
                })
                .catch(error => {
                    failed++;
                    console.error(`删除 ID ${ids[index]} 异常:`, error);
                    deleteNext(index + 1);
                });
            };

            deleteNext(0);
        }

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>