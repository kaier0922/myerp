<?php
/**
 * 文件名：suppliers.php
 * 功能：供应商列表管理
 */

session_start();
require_once 'config.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// AJAX：保存供应商简称
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_short_name') {
    header('Content-Type: application/json');
    $sid  = intval($_POST['id'] ?? 0);
    $sn   = trim($_POST['short_name'] ?? '');
    $stmt = $conn->prepare("UPDATE suppliers SET short_name=? WHERE id=? AND user_id=?");
    $stmt->bind_param('sii', $sn, $sid, $user_id);
    echo json_encode(['success' => $stmt->execute()]);
    exit;
}

$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message   = isset($_SESSION['error_message'])   ? $_SESSION['error_message']   : '';
if ($success_message) unset($_SESSION['success_message']);
if ($error_message)   unset($_SESSION['error_message']);

$search    = isset($_GET['search']) ? trim($_GET['search']) : '';
$status    = isset($_GET['status']) ? $_GET['status'] : 'all';
$page      = isset($_GET['page'])   ? intval($_GET['page'])   : 1;
$page_size = 20;
$offset    = ($page - 1) * $page_size;

$where_conditions = [tenantWhere()];
$params      = [];
$param_types = '';

if ($search) {
    $where_conditions[] = "(company_name LIKE ? OR contact_person LIKE ? OR contact_phone LIKE ? OR supplier_code LIKE ?)";
    $search_param = "%{$search}%";
    $params[]     = $search_param;
    $params[]     = $search_param;
    $params[]     = $search_param;
    $params[]     = $search_param;
    $param_types .= 'ssss';
}
if ($status === 'active')   { $where_conditions[] = "is_active = 1"; }
elseif ($status === 'inactive') { $where_conditions[] = "is_active = 0"; }

$where_sql = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$count_sql  = "SELECT COUNT(*) as total FROM suppliers {$where_sql}";
$count_stmt = $conn->prepare($count_sql);
if (count($params) > 0) { $count_stmt->bind_param($param_types, ...$params); }
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages   = ceil($total_records / $page_size);
$count_stmt->close();

$sql  = "SELECT * FROM suppliers {$where_sql} ORDER BY updated_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[]     = $page_size;
$params[]     = $offset;
$param_types .= 'ii';
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stats = $conn->query("
    SELECT COUNT(*) as total_suppliers,
           SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as active_suppliers,
           SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) as inactive_suppliers
    FROM suppliers WHERE " . tenantWhere() . "
")->fetch_assoc();

$conn->close();
?>
<?php $page_title = '供应商管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

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

        .btn-small { padding: 6px 10px; font-size: 12px; white-space: nowrap; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: nowrap; align-items: center; }

        .filter-section {
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px;
        }
        .filter-row { display: flex; gap: 16px; align-items: flex-end; flex-wrap: nowrap; }
        .filter-group { flex: 1; display: flex; flex-direction: column; gap: 8px; min-width: 0; }
        .filter-group.filter-buttons { flex: 0 0 auto; flex-direction: row; gap: 12px; }
        .filter-group label { font-size: 14px; font-weight: 500; color: #4a5568; }
        .form-control {
            padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 8px;
            font-size: 14px; transition: all 0.2s;
        }
        .form-control:focus { outline: none; border-color: #1f6dad; box-shadow: 0 0 0 3px rgba(31,109,173,0.1); }

        .table-container { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .table { width: 100%; border-collapse: collapse; table-layout: fixed; min-width: 810px; }
        .table thead { background: #f7fafc; }
        .table th { padding: 10px 8px; text-align: left; font-weight: 600; font-size: 12px; color: #4a5568; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
        .table td { padding: 10px 8px; border-bottom: 1px solid #e2e8f0; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .table tbody tr:hover { background: #f7fafc; }
        .table tbody tr:last-child td { border-bottom: none; }
        .contact-info { font-size: 12px; color: #718096; margin-top: 4px; }

        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger  { background: #fee2e2; color: #991b1b; }

        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 8px; padding: 24px; border-top: 1px solid #e2e8f0;
        }
        .pagination a, .pagination span {
            padding: 8px 12px; border-radius: 6px; text-decoration: none;
            color: #4a5568; font-size: 14px; transition: all 0.2s;
        }
        .pagination a:hover { background: #f7fafc; }
        .pagination span.active { background: linear-gradient(135deg, #1f6dad 0%, #135286 100%); color: white; font-weight: 600; }

        .empty-state-title { font-size: 20px; font-weight: 600; color: #1a202c; margin-bottom: 8px; }
        .empty-state-description { color: #718096; font-size: 14px; }

        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .alert-close { margin-left: auto; cursor: pointer; font-size: 20px; font-weight: bold; color: inherit; opacity: 0.6; }
        .alert-close:hover { opacity: 1; }

        @media (max-width: 768px) {
            .filter-row { flex-direction: column; align-items: stretch; }
            .filter-group { width: 100%; }
            .filter-group.filter-buttons { flex-direction: row; }
            .table-container { overflow-x: auto; }
        }
    </style>
</head>
<body>

<!-- 导航栏（与 index.php 完全一致） -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏（与 index.php 完全一致） -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区（功能逻辑完全不变） -->
<main class="main-content">
<div style="max-width:1100px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">🚚 供应商管理</h1>
    </div>

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

    <div class="filter-section">
        <form method="GET" action="suppliers.php">
            <div class="filter-row">
                <div class="filter-group" style="flex:0 0 auto;">
                    <label>搜索</label>
                    <input type="text" name="search" class="form-control" style="width:200px;"
                           placeholder="公司名称、联系人、电话、编号"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group" style="flex:0 0 auto;">
                    <label>状态</label>
                    <select name="status" class="form-control" style="width:120px;">
                        <option value="all"      <?php echo $status=='all'      ?'selected':''; ?>>全部状态</option>
                        <option value="active"   <?php echo $status=='active'   ?'selected':''; ?>>合作中</option>
                        <option value="inactive" <?php echo $status=='inactive' ?'selected':''; ?>>已停用</option>
                    </select>
                </div>
                <div class="filter-group filter-buttons">
                    <button type="submit" class="btn btn-primary">🔍 搜索</button>
                    <a href="suppliers.php" class="btn btn-secondary">🔄 重置</a>
                    <a href="supplier_add.php" class="btn btn-primary">+ 新增供应商</a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container">
        <?php if (count($suppliers) > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th width="110">供应商编号</th>
                    <th width="160">公司名称</th>
                    <th width="90">简称</th>
                    <th width="80">联系人</th>
                    <th width="110">联系电话</th>
                    <th width="80">地址</th>
                    <th width="70">账期(天)</th>
                    <th width="280">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?php echo htmlspecialchars($supplier['supplier_code']); ?></td>
                    <td title="<?php echo htmlspecialchars($supplier['company_name']); ?>">
                        <strong><?php echo htmlspecialchars($supplier['company_name']); ?></strong>
                        <?php if ($supplier['tax_number']): ?>
                            <div class="contact-info">税号: <?php echo htmlspecialchars($supplier['tax_number']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="sn-cell" data-id="<?php echo $supplier['id']; ?>"
                        title="点击编辑简称" style="cursor:pointer;">
                        <span class="sn-text" style="color:<?php echo $supplier['short_name']?'#1a202c':'#a0aec0'; ?>">
                            <?php echo htmlspecialchars($supplier['short_name'] ?: '点击设置'); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($supplier['contact_person']); ?></td>
                    <td><?php echo htmlspecialchars($supplier['contact_phone']); ?></td>
                    <td title="<?php echo htmlspecialchars($supplier['address']); ?>">
                        <?php echo htmlspecialchars($supplier['address']); ?>
                    </td>
                    <td><?php echo $supplier['payment_terms']; ?> 天</td>
                    <td>
                        <div class="action-buttons">
                            <a href="supplier_view.php?id=<?php echo $supplier['id']; ?>" class="btn btn-secondary btn-small">👁️ 查看</a>
                            <a href="supplier_edit.php?id=<?php echo $supplier['id']; ?>" class="btn btn-secondary btn-small">✏️ 编辑</a>
                            <?php if ($supplier['is_active'] == 1): ?>
                                <button onclick="disableSupplier(<?php echo $supplier['id']; ?>)" class="btn btn-secondary btn-small">⏸️ 停用</button>
                            <?php endif; ?>
                            <button onclick="deleteSupplier(<?php echo $supplier['id']; ?>, '<?php echo htmlspecialchars($supplier['company_name']); ?>')"
                                    class="btn btn-secondary btn-small" style="font-size:13px;color:#ef4444;border-color:#fecaca;">🗑️ 删除</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">← 上一页</a>
            <?php endif; ?>
            <?php
            $start_page = max(1, $page-2);
            $end_page   = min($total_pages, $page+2);
            for ($i=$start_page; $i<=$end_page; $i++):
            ?>
                <?php if ($i==$page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">下一页 →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">🚚</div>
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
</div><!-- end max-width -->
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const successAlert = document.getElementById('successAlert');
    const errorAlert   = document.getElementById('errorAlert');
    if (successAlert) {
        setTimeout(() => { successAlert.style.transition='opacity 0.5s'; successAlert.style.opacity='0'; setTimeout(()=>successAlert.remove(),500); }, 3000);
    }
    if (errorAlert) {
        setTimeout(() => { errorAlert.style.transition='opacity 0.5s'; errorAlert.style.opacity='0'; setTimeout(()=>errorAlert.remove(),500); }, 5000);
    }
});

// 简称内联编辑
document.querySelectorAll('.sn-cell').forEach(cell => {
    cell.addEventListener('click', function() {
        if (this.querySelector('input')) return;
        const sid  = this.dataset.id;
        const span = this.querySelector('.sn-text');
        const cur  = span.textContent.trim() === '点击设置' ? '' : span.textContent.trim();
        const inp  = document.createElement('input');
        inp.value = cur;
        inp.style.cssText = 'width:80px;padding:2px 6px;border:1px solid #1f6dad;border-radius:4px;font-size:13px;';
        inp.placeholder = '简称';
        span.replaceWith(inp);
        inp.focus();
        const save = async () => {
            const val = inp.value.trim();
            const fd  = new FormData();
            fd.append('action', 'save_short_name');
            fd.append('id', sid);
            fd.append('short_name', val);
            const r = await fetch('suppliers.php', {method:'POST', body: fd});
            const d = await r.json();
            const newSpan = document.createElement('span');
            newSpan.className = 'sn-text';
            newSpan.style.color = val ? '#1a202c' : '#a0aec0';
            newSpan.textContent = val || '点击设置';
            inp.replaceWith(newSpan);
            if (d.success && typeof sapToast === 'function') sapToast('简称已保存', 'success');
        };
        inp.addEventListener('blur', save);
        inp.addEventListener('keydown', e => { if (e.key === 'Enter') inp.blur(); if (e.key === 'Escape') { inp.value = cur; inp.blur(); } });
    });
});

async function deleteSupplier(id, name) {
    if (!confirm(`确定要删除供应商 "${name}" 吗？\n\n此操作将永久删除供应商信息，无法恢复。`)) return;
    try {
        const response = await fetch('supplier_delete.php?id=' + id + '&ajax=1&force=1');
        const data = await response.json();
        if (data.success) {
            sapToast ? sapToast('删除成功', 'success') : alert('删除成功');
            location.reload();
        } else {
            sapToast ? sapToast('删除失败: ' + data.message, 'error') : alert('删除失败: ' + data.message);
        }
    } catch(e) {
        alert('请求失败');
    }
}

async function disableSupplier(id) {
    if (!confirm('确定要停用这个供应商吗？\n\n供应商将被标记为"已停用"状态，不会真正删除数据。')) return;
    try {
        const response = await fetch('supplier_delete.php?id=' + id + '&ajax=1');
        if (!response.ok) throw new Error('网络请求失败');
        const result = await response.json();
        if (result.success) { sapToast('✓ ' + result.message, 'info'); window.location.reload(); }
        else { sapToast('✗ 停用失败：' + result.message, 'error'); }
    } catch (error) {
        console.error('停用错误:', error);
        sapToast('✗ 停用出错，请重试', 'error');
    }
}
</script>

<!-- SAP Toast + Confirm -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:260px;max-width:400px;padding:13px 16px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:16px;flex-shrink:0;"></span>
  <span id="sapToastMsg"  style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<div id="sapConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9000;"></div>
<div id="sapConfirmBox" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:400px;max-width:92vw;background:white;border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,0.18);z-index:9001;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:15px 20px;display:flex;align-items:center;gap:10px;">
    <span id="sapConfirmIcon"  style="font-size:18px;">⚠️</span>
    <span id="sapConfirmTitle" style="font-size:15px;font-weight:600;color:white;">确认操作</span>
  </div>
  <div style="padding:22px 20px;">
    <p id="sapConfirmMsg" style="font-size:14px;color:#2d3748;line-height:1.7;margin:0;"></p>
  </div>
  <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:10px;">
    <button id="sapConfirmCancel"
      style="padding:8px 20px;border:1px solid #e2e8f0;border-radius:7px;background:#f7fafc;
             color:#4a5568;font-size:14px;cursor:pointer;"
      onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='#f7fafc'">取消</button>
    <button id="sapConfirmOk"
      style="padding:8px 20px;border:none;border-radius:7px;
             background:linear-gradient(135deg,#1f6dad,#135286);
             color:white;font-size:14px;cursor:pointer;"
      onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">确认</button>
  </div>
</div>
<script>
function sapToast(msg,type,dur){
    type=type||'info';dur=dur||3000;
    var C={success:'#16a34a',error:'#dc2626',warning:'#d97706',info:'#1f6dad'};
    var I={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
    var el=document.getElementById('sapToast');
    if(!el)return;
    document.getElementById('sapToastIcon').textContent=I[type]||'ℹ️';
    document.getElementById('sapToastMsg').textContent=msg;
    el.style.background=C[type]||'#1f6dad';
    el.style.display='flex';el.style.opacity='1';
    clearTimeout(el._t);
    el._t=setTimeout(function(){el.style.opacity='0';
        setTimeout(function(){el.style.display='none';},300);},dur);
}
function sapConfirm(msg,onOk,opts){
    opts=opts||{};
    document.getElementById('sapConfirmMsg').innerHTML=msg.replace(/\n/g,'<br>');
    document.getElementById('sapConfirmTitle').textContent=opts.title||'确认操作';
    document.getElementById('sapConfirmIcon').textContent=opts.icon||'⚠️';
    document.getElementById('sapConfirmOk').textContent=opts.okText||'确认';
    document.getElementById('sapConfirmOk').style.background=
        opts.danger?'linear-gradient(135deg,#ef4444,#dc2626)':'linear-gradient(135deg,#1f6dad,#135286)';
    document.getElementById('sapConfirmOverlay').style.display='block';
    document.getElementById('sapConfirmBox').style.display='block';
    var ok=document.getElementById('sapConfirmOk');
    var cancel=document.getElementById('sapConfirmCancel');
    var close=function(){
        document.getElementById('sapConfirmOverlay').style.display='none';
        document.getElementById('sapConfirmBox').style.display='none';
        ok.onclick=null;cancel.onclick=null;};
    ok.onclick=function(){close();onOk();};
    cancel.onclick=function(){close();};
}
</script>

</body>
</html>