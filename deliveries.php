<?php
/**
 * =====================================================
 * 文件名：deliveries.php
 * 版本：  1.3
 * 日期：  2026-03-20
 * 功能：  送货管理列表页面
 * =====================================================
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

$search         = trim($_GET['search']         ?? '');
$status         = trim($_GET['status']         ?? '');
$payment_status = trim($_GET['payment_status'] ?? '');
$date_from      = trim($_GET['date_from']      ?? '');
$date_to        = trim($_GET['date_to']        ?? '');
$page           = max(1, intval($_GET['page']  ?? 1));
$per_page       = 20;
$offset         = ($page - 1) * $per_page;

$conn = getDBConnection();

$where_conditions = [tenantWhere()];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(delivery_no LIKE ? OR customer_name LIKE ? OR contact_phone LIKE ? OR delivery_address LIKE ?)";
    $sp = "%{$search}%";
    array_push($params, $sp, $sp, $sp, $sp);
    $param_types .= 'ssss';
}
if (!empty($status))         { $where_conditions[] = "status = ?";         $params[] = $status;         $param_types .= 's'; }
if (!empty($payment_status)) { $where_conditions[] = "payment_status = ?"; $params[] = $payment_status; $param_types .= 's'; }
if (!empty($date_from))      { $where_conditions[] = "delivery_date >= ?"; $params[] = $date_from;      $param_types .= 's'; }
if (!empty($date_to))        { $where_conditions[] = "delivery_date <= ?"; $params[] = $date_to;        $param_types .= 's'; }

$where_sql = implode(' AND ', $where_conditions);

$cnt_stmt = $conn->prepare("SELECT COUNT(*) as total FROM deliveries WHERE {$where_sql}");
if (!empty($params)) { $cnt_stmt->bind_param($param_types, ...$params); }
$cnt_stmt->execute();
$total_records = $cnt_stmt->get_result()->fetch_assoc()['total'];
$cnt_stmt->close();
$total_pages = ceil($total_records / $per_page);

$list_stmt = $conn->prepare("
    SELECT id,delivery_no,customer_name,contact_name,contact_phone,delivery_address,
           delivery_date,delivery_time,delivery_person,status,total_amount,paid_amount,
           payment_status,collect_on_delivery,approved,created_at
    FROM deliveries WHERE {$where_sql}
    ORDER BY delivery_date DESC, created_at DESC LIMIT ? OFFSET ?");
$lp = array_merge($params, [$per_page, $offset]);
$lt = $param_types . 'ii';
$list_stmt->bind_param($lt, ...$lp);
$list_stmt->execute();
$deliveries = $list_stmt->get_result();
$list_stmt->close();

$stats_stmt = $conn->prepare("
    SELECT COUNT(*) as total_count,
        SUM(CASE WHEN status='pending'    THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status='delivering' THEN 1 ELSE 0 END) as delivering_count,
        SUM(CASE WHEN status='completed'  THEN 1 ELSE 0 END) as completed_count,
        SUM(total_amount) as total_revenue,
        SUM(paid_amount)  as total_paid,
        SUM(total_amount - paid_amount) as total_unpaid
    FROM deliveries WHERE {$where_sql}");
if (!empty($params)) { $stats_stmt->bind_param($param_types, ...$params); }
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$conn->close();
?>
<?php $page_title = '送货管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.page-subtitle { color: #718096; font-size: 14px; }
.page-actions  { display: flex; gap: 12px; }
.stats-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 16px; margin-bottom: 24px;
}
.stat-box {
    background: white; border-radius: 12px; padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.stat-box-label { font-size: 13px; color: #718096; margin-bottom: 8px; }
.stat-box-value { font-size: 24px; font-weight: 700; color: #1a202c; }
.filter-bar {
    background: white; padding: 16px 20px; border-radius: 12px;
    margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: flex; gap: 10px; align-items: center; flex-wrap: wrap;
}
.filter-group { display: flex; flex-direction: column; gap: 6px; }
.filter-group label { font-size: 13px; font-weight: 500; color: #4a5568; }
.filter-group input,
.filter-group select {
    padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px;
}
.filter-group input:focus, .filter-group select:focus {
    outline: none; border-color: #1f6dad; box-shadow: 0 0 0 3px rgba(31,109,173,0.1);
}
.card-title { font-size: 18px; font-weight: 600; color: #1a202c; }
.table-wrapper { overflow-x: auto; }
thead tr { background: #f7fafc; border-bottom: 2px solid #e2e8f0; }
tbody
.badge-pending    { background: #e0e7ff; color: #3730a3; }
.badge-delivering { background: #dbeafe; color: #135286; }
.badge-completed  { background: #d1fae5; color: #065f46; }
.badge-failed     { background: #fee2e2; color: #991b1b; }
.badge-cancelled  { background: #f3f4f6; color: #6b7280; }
.badge-unpaid  { background: #fee2e2; color: #991b1b; }
.badge-partial { background: #fef3c7; color: #92400e; }
.badge-paid    { background: #d1fae5; color: #065f46; }
.badge-cod     { background: #fef3c7; color: #92400e; font-size: 11px; }
.btn-group { display: flex; gap: 6px; flex-wrap: nowrap; align-items: center; }
.pagination {
    display: flex; justify-content: center; align-items: center;
    gap: 6px; padding: 20px;
}
.pagination a, .pagination span {
    padding: 7px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
    text-decoration: none; color: #4a5568; font-size: 14px; transition: all 0.2s;
}
.pagination a:hover { background: #f7fafc; border-color: #1f6dad; color: #1f6dad; }
.pagination span.cur { background: linear-gradient(135deg,#1f6dad 0%,#135286 100%); color: white; border-color: transparent; }
@media (max-width: 768px) { }
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">🚚 送货管理</h1>
            <p class="page-subtitle">管理送货单，支持状态跟踪、审核及收款记录</p>
        </div>
    </div>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-box-label">总送货单</div>
            <div class="stat-box-value"><?php echo number_format($stats['total_count'] ?? 0); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">待送货</div>
            <div class="stat-box-value" style="color:#f59e0b;"><?php echo number_format($stats['pending_count'] ?? 0); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">配送中</div>
            <div class="stat-box-value" style="color:#3b82f6;"><?php echo number_format($stats['delivering_count'] ?? 0); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">已完成</div>
            <div class="stat-box-value" style="color:#1f6dad;"><?php echo number_format($stats['completed_count'] ?? 0); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">总营收</div>
            <div class="stat-box-value">¥<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">已收款</div>
            <div class="stat-box-value" style="color:#1f6dad;">¥<?php echo number_format($stats['total_paid'] ?? 0, 2); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">未收款</div>
            <div class="stat-box-value" style="color:#ef4444;">¥<?php echo number_format($stats['total_unpaid'] ?? 0, 2); ?></div>
        </div>
    </div>

    <form method="GET" action="deliveries.php">
    <div class="filter-bar">
        <input type="text" name="search" placeholder="单号 / 客户 / 电话 / 地址"
               value="<?php echo htmlspecialchars($search); ?>"
               style="width:360px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
        <select name="status" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
            <option value="">全部状态</option>
            <option value="pending"    <?php echo $status==='pending'    ?'selected':''; ?>>待送货</option>
            <option value="delivering" <?php echo $status==='delivering' ?'selected':''; ?>>配送中</option>
            <option value="completed"  <?php echo $status==='completed'  ?'selected':''; ?>>已完成</option>
            <option value="failed"     <?php echo $status==='failed'     ?'selected':''; ?>>失败</option>
            <option value="cancelled"  <?php echo $status==='cancelled'  ?'selected':''; ?>>已取消</option>
        </select>
        <select name="payment_status" style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
            <option value="">全部状态</option>
            <option value="unpaid"  <?php echo $payment_status==='unpaid'  ?'selected':''; ?>>未收款</option>
            <option value="partial" <?php echo $payment_status==='partial' ?'selected':''; ?>>部分收款</option>
            <option value="paid"    <?php echo $payment_status==='paid'    ?'selected':''; ?>>已收款</option>
        </select>
        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>"
               style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>"
               style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;">
        <button type="submit" class="btn btn-primary">🔍 搜索</button>
        <a href="deliveries.php" class="btn btn-secondary">清除</a>
    </div>
    </form>

    <div class="content-card">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <h2 class="card-title">送货单列表</h2>
                <a href="sales_report.php" class="btn btn-secondary">📊 销售统计</a>
                <a href="delivery_add.php" class="btn btn-primary">➕ 新建送货单</a>
            </div>
            <span style="font-size:13px;color:#718096;">共 <?php echo $total_records; ?> 条记录</span>
        </div>
        <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th><input type="checkbox" onclick="document.querySelectorAll('.delivery-checkbox').forEach(c=>c.checked=this.checked)"></th>
                    <th>送货单号</th>
                    <th>客户</th>
                    <th>送货日期</th>
                    <th>送货人</th>
                    <th>状态</th>
                    <th>总金额</th>
                    <th>支付状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $status_labels  = ['pending'=>'待送货','delivering'=>'配送中','completed'=>'已完成','failed'=>'失败','cancelled'=>'已取消'];
            $payment_labels = ['unpaid'=>'未收款','partial'=>'部分收款','paid'=>'已收款'];
            ?>
            <?php if ($deliveries->num_rows > 0): ?>
                <?php while ($d = $deliveries->fetch_assoc()): ?>
                <tr>
                    <td><input type="checkbox" class="delivery-checkbox" value="<?php echo $d['id']; ?>"></td>
                    <td>
                        <strong><?php echo htmlspecialchars($d['delivery_no']); ?></strong>
                        <?php if ($d['collect_on_delivery']): ?>&nbsp;<span class="badge badge-cod">货到付款</span><?php endif; ?>
                    </td>
                    <td title="<?php echo htmlspecialchars($d['customer_name'] ?? ''); ?>">
                        <?php
                            $cn = $d['customer_name'] ?? '';
                            echo htmlspecialchars(mb_substr($cn, 0, 8, 'UTF-8'));
                            if (mb_strlen($cn, 'UTF-8') > 8) echo '…';
                        ?>
                    </td>
                    <td>
                        <?php echo $d['delivery_date']; ?>
                    </td>
                    <td><?php echo htmlspecialchars($d['delivery_person'] ?? '') ?: '-'; ?></td>
                    <td><span class="badge badge-<?php echo $d['status']; ?>"><?php echo $status_labels[$d['status']] ?? $d['status']; ?></span></td>
                    <td><strong style="color:#047857;">¥<?php echo number_format($d['total_amount'], 2); ?></strong></td>
                    <td><span class="badge badge-<?php echo $d['payment_status']; ?>"><?php echo $payment_labels[$d['payment_status']] ?? $d['payment_status']; ?></span></td>
                    <td>
                        <div class="btn-group">
                            <button class="btn btn-secondary btn-sm" onclick="window.location.href='delivery_view.php?id=<?php echo $d['id']; ?>'">👁️ 查看</button>
                            <?php if (!$d['approved']): ?>
                                <button class="btn btn-secondary btn-sm" style="background:#d1fae5;color:#065f46;"
                                        onclick="approveDelivery(<?php echo $d['id']; ?>,'<?php echo htmlspecialchars($d['delivery_no']); ?>')">✓ 审核</button>
                            <?php else: ?>
                                <span class="badge" style="background:#1f6dad;color:white;">已审核</span>
                            <?php endif; ?>
                            <?php if ($d['status']!='completed' && $d['status']!='cancelled' && !$d['approved']): ?>
                                <button class="btn btn-secondary btn-sm" onclick="window.location.href='delivery_edit.php?id=<?php echo $d['id']; ?>'">✏️ 编辑</button>
                            <?php endif; ?>
                            <button class="btn btn-secondary btn-sm" onclick="copyDelivery(<?php echo $d['id']; ?>)">📋 复制</button>
                            <button class="btn btn-secondary btn-sm" style="background:#dbeafe;color:#1f6dad;border-color:#93c5fd;"
                                    onclick="showStatusModal(<?php echo $d['id']; ?>,'<?php echo $d['status']; ?>')">📋 更新状态</button>
                            <?php if (!$d['approved']): ?>
                                <button class="btn btn-secondary btn-sm" style="background:#fee2e2;color:#991b1b;"
                                        onclick="deleteDelivery(<?php echo $d['id']; ?>,'<?php echo htmlspecialchars($d['delivery_no']); ?>')">🗑️ 删除</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="12">
                    <div class="empty-state">
                        <div class="empty-state-icon">🚚</div>
                        <div>暂无送货单数据</div>
                    </div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total_pages > 1):
            $qs = http_build_query(['search'=>$search,'status'=>$status,'payment_status'=>$payment_status,'date_from'=>$date_from,'date_to'=>$date_to]);
        ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page-1; ?>&<?php echo $qs; ?>">← 上一页</a>
            <?php endif; ?>
            <?php
            $sp = max(1,$page-3); $ep = min($total_pages,$page+3);
            if ($sp>1) { echo '<a href="?page=1&'.$qs.'">1</a>'; if ($sp>2) echo '<span>…</span>'; }
            for ($i=$sp;$i<=$ep;$i++) {
                if ($i==$page) echo '<span class="cur">'.$i.'</span>';
                else echo '<a href="?page='.$i.'&'.$qs.'">'.$i.'</a>';
            }
            if ($ep<$total_pages) { if ($ep<$total_pages-1) echo '<span>…</span>'; echo '<a href="?page='.$total_pages.'&'.$qs.'">'.$total_pages.'</a>'; }
            ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page+1; ?>&<?php echo $qs; ?>">下一页 →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

</main>

<script>
function deleteDelivery(id, no) {
    const btn = event.currentTarget || event.target;
    const orig = btn.textContent;
    sapConfirm(`确定要删除送货单 "${no}" 吗？<br><br>此操作将永久删除送货单及相关记录。`,
    function() {
        btn.disabled = true; btn.textContent = '⏳';
        fetch('delivery_delete.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` })
            .then(r => r.json())
            .then(d => {
                if (d.success) { sapToast(d.message, 'success'); setTimeout(()=>location.reload(),1200); }
                else { sapToast('删除失败: ' + d.message, 'error'); btn.disabled=false; btn.textContent=orig; }
            })
            .catch(e => { sapToast('删除出错: '+e.message, 'error'); btn.disabled=false; btn.textContent=orig; });
    }, {danger:true, title:'删除送货单', okText:'确认删除'});
}

async function approveDelivery(id, no) {
    sapConfirm(`确定要审核送货单 "${no}" 吗？<br><br>审核后将自动生成应收账款记录，且无法撤销。`,
    async function() {
        try {
            const r = await fetch('delivery_approve.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` });
            const d = await r.json();
            if (d.success) { sapToast('审核成功：' + d.message + (d.ar_no ? ' 应收单号: '+d.ar_no : ''), 'success'); setTimeout(()=>location.reload(),1200); }
            else sapToast('审核失败：' + d.message, 'error');
        } catch(e) { sapToast('审核时发生错误：' + e.message, 'error'); }
    }, {title:'审核送货单', okText:'确认审核'});
}

async function copyDelivery(id) {
    sapConfirm('确定要复制这个送货单吗？<br><br>将创建一个相同内容的新送货单。',
    async function() {
        try {
            const r = await fetch('delivery_copy.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:`id=${id}` });
            const d = await r.json();
            if (d.success) { sapToast('复制成功，新送货单号: ' + d.new_no, 'success'); setTimeout(()=>location.href='delivery_edit.php?id='+d.new_id, 1200); }
            else sapToast('复制失败：' + d.message, 'error');
        } catch(e) { sapToast('复制时发生错误：' + e.message, 'error'); }
    }, {title:'复制送货单', okText:'确认复制'});
}

let _statusDeliveryId = 0;
function showStatusModal(id, currentStatus) {
    _statusDeliveryId = id;
    document.getElementById('statusModalNo').textContent = '送货单ID：' + id + '　当前状态：' + ({pending:'待送货',delivering:'配送中',completed:'已完成',failed:'配送失败',cancelled:'已取消'}[currentStatus]||currentStatus);
    const modal = document.getElementById('statusModal');
    modal.style.display = 'flex';
}
function hideStatusModal() {
    document.getElementById('statusModal').style.display = 'none';
}
async function updateStatus(status) {
    const labels = {pending:'待送货',delivering:'配送中',completed:'已完成',failed:'配送失败',cancelled:'已取消'};
    sapConfirm('确定将状态更新为「' + labels[status] + '」吗？',
    async function() {
        hideStatusModal();
    try {
        const r = await fetch('delivery_status_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'delivery_id=' + _statusDeliveryId + '&status=' + status
        });
        const d = await r.json();
        if (d.success) { sapToast('✅ ' + d.message, 'info'); window.location.reload(); }
        else { sapToast('❌ ' + d.message, 'info'); }
    } catch(e) { sapToast('更新失败：' + e.message, 'error'); }
    }, {title:'更新送货状态', okText:'确认更新'});
}
</script>

<!-- 更新状态弹窗 -->
<div id="statusModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:12px;padding:28px;min-width:360px;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <h3 style="margin:0 0 6px;font-size:16px;color:#1a202c;">📋 更新送货状态</h3>
        <p id="statusModalNo" style="margin:0 0 18px;font-size:13px;color:#718096;"></p>
        <div style="display:flex;flex-direction:column;gap:8px;">
            <button onclick="updateStatus('pending')"    class="status-opt-btn" style="background:#e0e7ff;color:#3730a3;">⏳ 待送货</button>
            <button onclick="updateStatus('delivering')" class="status-opt-btn" style="background:#dbeafe;color:#135286;">🚚 配送中</button>
            <button onclick="updateStatus('completed')"  class="status-opt-btn" style="background:#d1fae5;color:#065f46;">✅ 已完成</button>
            <button onclick="updateStatus('failed')"     class="status-opt-btn" style="background:#fee2e2;color:#991b1b;">❌ 配送失败</button>
            <button onclick="updateStatus('cancelled')"  class="status-opt-btn" style="background:#f3f4f6;color:#6b7280;">🚫 已取消</button>
        </div>
        <button onclick="hideStatusModal()" style="margin-top:14px;width:100%;padding:10px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:14px;">取消</button>
    </div>
</div>
<style>
.status-opt-btn{padding:11px 16px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;text-align:left;transition:opacity .2s;}
.status-opt-btn:hover{opacity:0.75;}
</style>

<!-- SAP Toast + Confirm -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:260px;max-width:400px;padding:13px 16px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:16px;flex-shrink:0;"></span>
  <span id="sapToastMsg"  style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<div id="sapConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:10000;"></div>
<div id="sapConfirmBox" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:400px;max-width:92vw;background:white;border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,0.18);z-index:10001;overflow:hidden;">
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
