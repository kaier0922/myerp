<?php
/**
 * ============================================================================
 * 文件名: customers.php
 * 版本: 1.1
 * 说明: 客户管理列表页面 — 侧边栏统一为 260px 风格
 * ============================================================================
 */

session_start();
require_once 'config.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$conn = getDBConnection();

$where_conditions = [tenantWhere()];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR short_name LIKE ? OR contact_name LIKE ? OR phone LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ssss';
}

$where_sql = implode(' AND ', $where_conditions);

$count_sql = "SELECT COUNT(*) as total FROM customers WHERE {$where_sql}";
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_records = $count_stmt->get_result()->fetch_assoc()['total'];
    $count_stmt->close();
} else {
    $total_records = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_records / $per_page);

// 统计数据
$stats = $conn->query("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN phone IS NOT NULL AND phone != '' THEN 1 ELSE 0 END) as has_phone,
        SUM(CASE WHEN email IS NOT NULL AND email != '' THEN 1 ELSE 0 END) as has_email,
        COUNT(DISTINCT CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN id END) as new_this_month
    FROM customers WHERE " . tenantWhere() . "
")->fetch_assoc();

$list_sql = "
    SELECT id, company_name, short_name, COALESCE(short_name, company_name) AS display_name, contact_name, phone, email, address, tax_number, created_at
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
    $list_sql_plain = "
        SELECT id, company_name, short_name, COALESCE(short_name, company_name) AS display_name, contact_name, phone, email, address, tax_number, created_at
        FROM customers
        WHERE {$where_sql}
        ORDER BY created_at DESC
        LIMIT {$per_page} OFFSET {$offset}
    ";
    $customers = $conn->query($list_sql_plain);
}

$conn->close();
?>
<?php $page_title = '客户管理'; include __DIR__ . '/head.php'; ?>
    <style>
        .main-content > * { max-width: 1200px; margin-left: auto; margin-right: auto; }

        .stats-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; margin-bottom: 32px;
        }
        .stat-card {
            background: white; padding: 20px; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .stat-label { font-size: 14px; color: #718096; margin-bottom: 8px; }
        .stat-value { font-size: 32px; font-weight: 700; color: #1a202c; }

        .btn-danger  { background: #ef4444; color: white; }
        .btn-danger:hover  { background: #dc2626; }
        .btn-action {
            padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px;
            background: white; color: #4a5568; text-decoration: none; font-size: 16px;
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 32px; height: 32px; transition: all 0.2s; cursor: pointer;
        }
        .btn-action:hover { background: #f7fafc; border-color: #cbd5e0; transform: scale(1.1); }
        .btn-delete { background: #fee; color: #c00; border-color: #fcc; }
        .btn-delete:hover { background: #fcc; color: #900; border-color: #faa; }

        .filter-card {
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px;
        }
        .filter-group { display: flex; gap: 16px; align-items: flex-end; }
        .filter-input-group { flex: 1; max-width: 400px; }
        .filter-label { font-size: 14px; font-weight: 500; color: #4a5568; margin-bottom: 6px; display: block; }
        .filter-input {
            width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0;
            border-radius: 6px; font-size: 14px;
        }
        .filter-input:focus { outline: none; border-color: #1f6dad; box-shadow: 0 0 0 3px rgba(31,109,173,0.1); }

        .data-card {
            background: white; border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden;
        }

        .table-wrapper { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 860px; }
        .table thead { background: #f7fafc; }
        .table th { padding: 16px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .table td { padding: 14px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .table tbody tr:hover { background: #f7fafc; }
        /* 1:checkbox 2:ID 3:简称 4:联系人 5:电话 6:地址 7:创建时间 8:操作 */
        .table th:nth-child(1), .table td:nth-child(1) { width: 36px; }
        .table th:nth-child(2), .table td:nth-child(2) { width: 50px; }
        .table th:nth-child(3), .table td:nth-child(3) { width: 160px; }
        .table th:nth-child(4), .table td:nth-child(4) { width: 90px; }
        .table th:nth-child(5), .table td:nth-child(5) { width: 120px; }
        .table th:nth-child(6), .table td:nth-child(6) { width: 160px; }
        .table th:nth-child(7), .table td:nth-child(7) { width: 130px; }
        .table th:nth-child(8), .table td:nth-child(8) { width: 200px; }

        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 8px; padding: 24px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
            text-decoration: none; color: #4a5568; transition: all 0.2s;
        }
        .pagination a:hover { background: #f7fafc; border-color: #cbd5e0; }
        .pagination .active { background: #1f6dad; color: white; border-color: #1f6dad; }

        .batch-actions {
            padding: 16px 24px; background: #f7fafc;
            border-top: 1px solid #e2e8f0; display: none;
        }
        .batch-actions.active { display: flex; align-items: center; gap: 16px; }
        .selected-count { color: #4a5568; font-size: 14px; }
        .action-buttons { display: flex; gap: 6px; align-items: center; }
    </style>
</head>
<body>

<!-- 导航栏（与 index.php 完全一致） -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏（与 index.php 完全一致，图标已修正） -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区 -->
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">客户列表</h1>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">客户总数</div>
            <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">有联系电话</div>
            <div class="stat-value"><?php echo $stats['has_phone'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">有邮箱</div>
            <div class="stat-value"><?php echo $stats['has_email'] ?? 0; ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-label">近30天新增</div>
            <div class="stat-value"><?php echo $stats['new_this_month'] ?? 0; ?></div>
        </div>
    </div>

    <!-- 搜索筛选 -->
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
                <a href="customers.php" class="btn btn-secondary">清除</a>
                <button onclick="openImportModal()" class="btn btn-secondary">📥 导入</button>
                <a href="customer_export.php?search=<?php echo urlencode($search); ?>" class="btn btn-secondary">📤 导出全部</a>
                <a href="customer_add.php" class="btn btn-primary">+ 添加客户</a>
            </div>
        </form>
    </div>

    <!-- 数据表格 -->
    <div class="data-card">
        <div class="table-wrapper">
            <table class="table">
                <thead>
                    <tr>
                        <th><input type="checkbox" onclick="selectAll(this)" id="selectAllCheckbox"></th>
                        <th>ID</th>
                        <th>客户简称</th>
                        <th>联系人</th>
                        <th>电话</th>
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
                                       class="customer-checkbox" onchange="updateBatchActions()">
                            </td>
                            <td><?php echo $customer['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($customer['short_name'] ?: $customer['company_name']); ?></strong>
                                <?php if (!empty($customer['short_name']) && $customer['short_name'] !== $customer['company_name']): ?>
                                <div style="color:#718096; font-size:12px; margin-top:2px;"><?php echo htmlspecialchars($customer['company_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($customer['contact_name'] ?? '') ?: '-'; ?></td>
                            <td><?php echo htmlspecialchars($customer['phone'] ?? '') ?: '-'; ?></td>
                            <td><?php echo htmlspecialchars($customer['address'] ?? '') ?: '-'; ?></td>
                            <td style="color:#718096; font-size:13px;">
                                <?php echo date('Y-m-d H:i', strtotime($customer['created_at'])); ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="customer_view.php?id=<?php echo $customer['id']; ?>"
                                       class="btn btn-secondary" style="font-size:13px;padding:6px 12px;">👁️ 查看</a>
                                    <a href="customer_edit.php?id=<?php echo $customer['id']; ?>"
                                       class="btn btn-secondary" style="font-size:13px;padding:6px 12px;">✏️ 编辑</a>
                                    <button onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['display_name']); ?>')"
                                            class="btn btn-secondary" style="font-size:13px;padding:6px 12px;color:#ef4444;border-color:#fecaca;">🗑️ 删除</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <div class="empty-state-icon">👥</div>
                                    <div class="empty-state-text">暂无客户数据</div>
                                    <a href="customer_add.php" class="btn btn-primary">添加第一个客户</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 批量操作 -->
        <div class="batch-actions" id="batchActions">
            <span class="selected-count">已选择 <strong id="selectedCount">0</strong> 项</span>
            <button onclick="exportSelected()" class="btn btn-secondary">📤 导出选中</button>
            <button onclick="batchDelete()" class="btn btn-danger">批量删除</button>
            <button onclick="clearSelection()" class="btn btn-secondary">取消选择</button>
        </div>

        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">← 上一页</a>
            <?php endif; ?>
            <?php
            $start_page = max(1, $page - 2);
            $end_page   = min($total_pages, $page + 2);
            if ($start_page > 1): ?>
                <a href="?page=1&search=<?php echo urlencode($search); ?>">1</a>
                <?php if ($start_page > 2): ?><span>...</span><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?><span>...</span><?php endif; ?>
                <a href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>"><?php echo $total_pages; ?></a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">下一页 →</a>
            <?php endif; ?>
            <span style="margin-left:16px; color:#718096;">共 <?php echo $total_records; ?> 条记录</span>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- 导入弹窗 -->
<div id="importOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:400;" onclick="if(event.target===this)closeImportModal()"></div>
<div id="importModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:500px;max-width:95vw;background:white;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,0.2);z-index:401;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:16px;font-weight:600;color:white;">📥 导入客户</span>
        <button onclick="closeImportModal()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:28px;height:28px;border-radius:6px;font-size:16px;cursor:pointer;">×</button>
    </div>
    <div style="padding:24px;">
        <div style="background:#f0f4ff;border-radius:8px;padding:14px 16px;margin-bottom:20px;font-size:14px;color:#3730a3;line-height:1.6;">
            <strong>导入说明：</strong><br>
            • 仅支持 CSV 格式（UTF-8 编码）<br>
            • 第一行为表头，必须包含：公司名称、联系人、电话<br>
            • 可选列：邮箱、地址、税号<br>
            • 重复的公司名称将被跳过
        </div>
        <div style="margin-bottom:16px;">
            <a href="customer_export.php?template=1" class="btn btn-secondary" style="font-size:13px;">⬇️ 下载 CSV 模板</a>
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block;font-size:14px;font-weight:600;color:#4a5568;margin-bottom:6px;">选择 CSV 文件</label>
            <input type="file" id="importFile" accept=".csv" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
        </div>
        <div id="importResult" style="display:none;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="closeImportModal()" class="btn btn-secondary">取消</button>
            <button onclick="doImport()" class="btn btn-primary" id="importBtn">开始导入</button>
        </div>
    </div>
</div>

<script>
function selectAll(checkbox) {
    document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateBatchActions();
}
function updateBatchActions() {
    const checked = document.querySelectorAll('.customer-checkbox:checked');
    const all = document.querySelectorAll('.customer-checkbox');
    document.getElementById('selectedCount').textContent = checked.length;
    document.getElementById('batchActions').classList.toggle('active', checked.length > 0);
    document.getElementById('selectAllCheckbox').checked = checked.length > 0 && checked.length === all.length;
}
function clearSelection() {
    document.querySelectorAll('.customer-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('selectAllCheckbox').checked = false;
    updateBatchActions();
}
function deleteCustomer(customerId, companyName) {
    const btn  = event.currentTarget || event.target;
    const orig = btn.textContent;
    sapConfirm(
        `确定要删除客户 "<strong>${companyName}</strong>" 吗？<br><br>此操作将永久删除客户信息。`,
        function() {
            btn.disabled = true;
            btn.textContent = '⏳';
            fetch('customer_delete.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + customerId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    sapToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1200);
                } else {
                    sapToast('删除失败: ' + data.message, 'error');
                    btn.disabled = false;
                    btn.textContent = orig;
                }
            })
            .catch(err => {
                sapToast('出错: ' + err.message, 'error');
                btn.disabled = false;
                btn.textContent = orig;
            });
        },
        { danger: true, title: '删除客户', okText: '确认删除' }
    );
}
// 导出选中行为 CSV（从当前页表格读取数据）
function exportSelected() {
    const checked = document.querySelectorAll('.customer-checkbox:checked');
    if (!checked.length) { sapToast('请先勾选要导出的客户', 'warning'); return; }

    const headers = ['ID','公司名称','联系人','电话','邮箱','地址','税号','创建时间'];
    const rows = [headers];

    checked.forEach(cb => {
        const tr = cb.closest('tr');
        const cells = tr.querySelectorAll('td');
        rows.push([
            cells[1]?.textContent.trim() ?? '',
            cells[2]?.textContent.trim() ?? '',
            cells[3]?.textContent.trim() ?? '',
            cells[4]?.textContent.trim() ?? '',
            cells[5]?.textContent.trim() ?? '',
            cells[6]?.textContent.trim() ?? '',
            cells[7]?.textContent.trim() ?? '',
            cells[8]?.textContent.trim() ?? '',
        ]);
    });

    const csv = '\uFEFF' + rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = '客户列表_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
}

// 导入弹窗
function openImportModal() {
    document.getElementById('importOverlay').style.display = 'block';
    document.getElementById('importModal').style.display = 'block';
    document.getElementById('importResult').style.display = 'none';
    document.getElementById('importFile').value = '';
}
function closeImportModal() {
    document.getElementById('importOverlay').style.display = 'none';
    document.getElementById('importModal').style.display = 'none';
}

async function doImport() {
    const file = document.getElementById('importFile').files[0];
    if (!file) { sapToast('请先选择 CSV 文件', 'warning'); return; }

    const btn = document.getElementById('importBtn');
    btn.disabled = true; btn.textContent = '⏳ 导入中...';

    const formData = new FormData();
    formData.append('csv_file', file);

    try {
        const r = await fetch('customer_import.php', { method: 'POST', body: formData });
        const d = await r.json();
        const box = document.getElementById('importResult');
        box.style.display = 'block';
        if (d.success) {
            box.style.background = '#d1fae5'; box.style.color = '#065f46';
            box.innerHTML = `✅ 导入完成：成功 <strong>${d.imported}</strong> 条，跳过 <strong>${d.skipped}</strong> 条${d.errors?.length ? `<br>⚠️ 错误：${d.errors.join('<br>')}` : ''}`;
            setTimeout(() => { closeImportModal(); location.reload(); }, 1500);
        } else {
            box.style.background = '#fee2e2'; box.style.color = '#991b1b';
            box.innerHTML = '❌ ' + (d.message || '导入失败');
        }
    } catch(e) {
        sapToast('导入出错：' + e.message, 'error');
    }
    btn.disabled = false; btn.textContent = '开始导入';
}
function batchDelete() {
    const checked = document.querySelectorAll('.customer-checkbox:checked');
    if (!checked.length) { sapToast('请先选择要删除的客户', 'warning'); return; }
    sapConfirm(
        `确定要删除选中的 <strong>${checked.length}</strong> 个客户吗？`,
        function() {
            const ids = Array.from(checked).map(cb => cb.value);
            let done = 0, fail = 0;
            const ba = document.getElementById('batchActions');
            const next = idx => {
                if (idx >= ids.length) {
                    sapToast(fail === 0 ? `批量删除完成！成功 ${done} 个。` : `完成：成功 ${done}，失败 ${fail}`,
                             fail === 0 ? 'success' : 'warning');
                    setTimeout(() => location.reload(), 1200);
                    return;
                }
                ba.innerHTML = `<span>正在删除... ${idx+1}/${ids.length}</span>`;
                fetch('customer_delete.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'id=' + ids[idx]
                })
                .then(r => r.json())
                .then(d => { d.success ? done++ : fail++; next(idx+1); })
                .catch(() => { fail++; next(idx+1); });
            };
            next(0);
        },
        { danger: true, title: '批量删除客户', okText: '确认删除' }
    );
}
</script>


<!-- SAP风格 Toast 消息（右上角滑入） -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:280px;max-width:420px;padding:14px 18px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:18px;flex-shrink:0;margin-top:1px;"></span>
  <span id="sapToastMsg" style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<script>
function sapToast(msg, type, duration) {
    type     = type     || 'info';
    duration = duration || 3000;
    const colors = { success:'#16a34a', error:'#dc2626', warning:'#d97706', info:'#1f6dad' };
    const icons  = { success:'✅', error:'❌', warning:'⚠️', info:'ℹ️' };
    const el = document.getElementById('sapToast');
    if (!el) { console.warn('sapToast element missing'); return; }
    document.getElementById('sapToastIcon').textContent = icons[type]  || 'ℹ️';
    document.getElementById('sapToastMsg').textContent  = msg;
    el.style.background = colors[type] || '#1f6dad';
    el.style.display    = 'flex';
    el.style.opacity    = '1';
    clearTimeout(el._timer);
    el._timer = setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => { el.style.display = 'none'; }, 320);
    }, duration);
}
</script>

<!-- SAP风格确认弹窗 -->
<div id="sapConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9000;"></div>
<div id="sapConfirmBox" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:420px;max-width:92vw;background:white;border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,0.18);z-index:9001;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:16px 20px;display:flex;align-items:center;gap:10px;">
    <span id="sapConfirmIcon" style="font-size:20px;">⚠️</span>
    <span id="sapConfirmTitle" style="font-size:15px;font-weight:600;color:white;">确认操作</span>
  </div>
  <div style="padding:24px 20px;">
    <p id="sapConfirmMsg" style="font-size:14px;color:#2d3748;line-height:1.7;margin:0;"></p>
  </div>
  <div style="padding:0 20px 20px;display:flex;justify-content:flex-end;gap:10px;">
    <button id="sapConfirmCancel"
      style="padding:9px 22px;border:1px solid #e2e8f0;border-radius:7px;background:#f7fafc;
             color:#4a5568;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s;"
      onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='#f7fafc'">
      取消
    </button>
    <button id="sapConfirmOk"
      style="padding:9px 22px;border:none;border-radius:7px;
             background:linear-gradient(135deg,#1f6dad,#135286);
             color:white;font-size:14px;font-weight:500;cursor:pointer;transition:all .15s;"
      onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
      确认
    </button>
  </div>
</div>

<script>
// SAP风格确认框（替代原生 confirm）
function sapConfirm(msg, onOk, opts) {
    opts = opts || {};
    document.getElementById('sapConfirmMsg').innerHTML   = msg.replace(/\n/g,'<br>');
    document.getElementById('sapConfirmTitle').textContent = opts.title   || '确认操作';
    document.getElementById('sapConfirmIcon').textContent  = opts.icon    || '⚠️';
    document.getElementById('sapConfirmOk').textContent    = opts.okText  || '确认';
    document.getElementById('sapConfirmOk').style.background =
        opts.danger ? 'linear-gradient(135deg,#ef4444,#dc2626)'
                    : 'linear-gradient(135deg,#1f6dad,#135286)';
    document.getElementById('sapConfirmOverlay').style.display = 'block';
    document.getElementById('sapConfirmBox').style.display     = 'block';

    const ok     = document.getElementById('sapConfirmOk');
    const cancel = document.getElementById('sapConfirmCancel');

    const close = () => {
        document.getElementById('sapConfirmOverlay').style.display = 'none';
        document.getElementById('sapConfirmBox').style.display     = 'none';
        ok.onclick = null; cancel.onclick = null;
    };
    ok.onclick     = () => { close(); onOk(); };
    cancel.onclick = () => { close(); };
}
</script>

</body>
</html>