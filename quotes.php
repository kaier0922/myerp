<?php
/**
 * =====================================================
 * 文件名：quotes.php
 * 版本：  1.2
 * 日期：  2026-03-18
 * 功能：  报价单管理主页面（带审核功能+分页）
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
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];

$conn = getDBConnection();

$status_filter   = $_GET['status']   ?? '';
$template_filter = $_GET['template'] ?? '';
$search          = $_GET['search']   ?? '';
$approved_filter = $_GET['approved'] ?? '';
$page     = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where = tenantWhere("q");
if ($status_filter)   $where .= " AND q.status = '"        . $conn->real_escape_string($status_filter)   . "'";
if ($template_filter) $where .= " AND q.template_type = '" . $conn->real_escape_string($template_filter) . "'";
if ($search) {
    $s = $conn->real_escape_string($search);
    $where .= " AND (q.quote_no LIKE '%$s%' OR COALESCE(c.short_name, c.company_name) LIKE '%$s%' OR q.project_name LIKE '%$s%')";
}
if ($approved_filter !== '') $where .= " AND q.approved = " . intval($approved_filter);

// ── 自动将过期报价单标记为已过期 ──
$conn->query("
    UPDATE quotes 
    SET status='已过期'
    WHERE status IN ('草稿','已确认')
    AND quote_date IS NOT NULL
    AND DATE_ADD(STR_TO_DATE(quote_date,'%Y-%m-%d'), INTERVAL valid_days DAY) < CURDATE()
    AND user_id = " . (int)getCurrentUserId() . "
");

$stats = $conn->query("
    SELECT COUNT(*) as total_count,
        SUM(CASE WHEN status='草稿'   THEN 1 ELSE 0 END) as draft_count,
        SUM(CASE WHEN status='已确认' THEN 1 ELSE 0 END) as sent_count,
        SUM(CASE WHEN status='已成交' THEN 1 ELSE 0 END) as deal_count,
        SUM(CASE WHEN status='已过期' THEN 1 ELSE 0 END) as expired_count,
        SUM(CASE WHEN approved=1      THEN 1 ELSE 0 END) as approved_count,
        SUM(final_amount) as total_amount,
        SUM(CASE WHEN status='已成交' THEN final_amount ELSE 0 END) as deal_amount
    FROM quotes WHERE " . tenantWhere() . "
")->fetch_assoc();

// 总数（用于分页，带筛选条件）
$total_records = $conn->query("
    SELECT COUNT(*) as cnt
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE $where
")->fetch_assoc()['cnt'];
$total_pages = max(1, ceil($total_records / $per_page));

$quotes = $conn->query("
    SELECT q.*, COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name, c.phone,
           u.nickname as approved_by_name
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    LEFT JOIN users u ON q.approved_by = u.id
    WHERE $where
    ORDER BY q.created_at DESC
    LIMIT $per_page OFFSET $offset
");

$conn->close();
?>
<?php $page_title = '报价单管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">
<style>

.page-subtitle { color: #718096; font-size: 14px; }

.btn-group { display: flex; gap: 8px; flex-wrap: wrap; }

.stats-row {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px; margin-bottom: 24px;
}
.stat-box {
    background: white; border-radius: 12px; padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.3s;
}
.stat-box:hover { transform: translateY(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.stat-box-label { font-size: 13px; color: #718096; margin-bottom: 8px; }
.stat-box-value { font-size: 24px; font-weight: 700; color: #1a202c; }

.filter-bar {
    background: white; border-radius: 12px; padding: 20px;
    margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    display: grid; grid-template-columns: auto auto auto auto 1fr;
    gap: 16px; align-items: center;
}
.filter-item { display: flex; align-items: center; gap: 8px; }
.filter-item label { font-size: 14px; color: #4a5568; font-weight: 500; }
.filter-item select, .filter-item input {
    padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;
    font-size: 14px; min-width: 120px;
}
.filter-item select:focus, .filter-item input:focus {
    outline: none; border-color: #1f6dad; box-shadow: 0 0 0 3px rgba(31,109,173,0.1);
}
.filter-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

.card-title { font-size: 18px; font-weight: 600; color: #1a202c; }

thead tr { background: #f7fafc; border-bottom: 2px solid #e2e8f0; }
tbody

.badge.draft     { background: #e2e8f0; color: #4a5568; }
.badge.sent      { background: #dbeafe; color: #135286; }
.badge.deal      { background: #d1fae5; color: #065f46; }
.badge.expired   { background: #fee2e2; color: #991b1b; }
.badge.approved  { background: #d1fae5; color: #065f46; }
.badge.unapproved { background: #fef3c7; color: #92400e; }
.badge.assembled_pc   { background: #dbeafe; color: #135286; }
.badge.brand_pc       { background: #fce7f3; color: #9f1239; }
.badge.weak_current   { background: #d1fae5; color: #065f46; }
.badge.strong_current { background: #fed7aa; color: #9a3412; }

.pagination-bar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 24px; border-top: 1px solid #e2e8f0;
}
.pagination-info { font-size: 13px; color: #718096; }
.pagination { display: flex; gap: 6px; align-items: center; }
.pagination a, .pagination span {
    padding: 6px 11px; border: 1px solid #e2e8f0; border-radius: 6px;
    text-decoration: none; color: #4a5568; font-size: 13px; transition: all 0.2s;
}
.pagination a:hover { background: #f7fafc; border-color: #1f6dad; color: #1f6dad; }
.pagination span.cur { background: linear-gradient(135deg,#1f6dad,#135286); color: white; border-color: transparent; }
.pagination span.ellipsis { border: none; color: #a0aec0; padding: 6px 4px; }

.success-message { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; padding: 16px 24px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
.error-message   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; padding: 16px 24px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }

@media (max-width: 768px) { .filter-bar { grid-template-columns: 1fr; } }
</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容区 -->
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">📋 报价单管理</h1>
        <p class="page-subtitle">管理报价单、审核客户确定的报价</p>
    </div>

    <?php if (isset($_SESSION['message'])): ?>
        <div class="success-message">✓ <?php echo $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error-message">✗ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="stats-row">
        <div class="stat-box">
            <div class="stat-box-label">总报价单数</div>
            <div class="stat-box-value"><?php echo $stats['total_count'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">已确认报价</div>
            <div class="stat-box-value" style="color:#1f6dad;"><?php echo $stats['sent_count'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">草稿</div>
            <div class="stat-box-value"><?php echo $stats['draft_count'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">已成交</div>
            <div class="stat-box-value" style="color:#1f6dad;"><?php echo $stats['deal_count'] ?? 0; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-box-label">成交金额</div>
            <div class="stat-box-value" style="color:#f59e0b;">¥<?php echo number_format($stats['deal_amount'] ?? 0, 2); ?></div>
        </div>
    </div>

    <div class="filter-bar">
        <div class="filter-item">
            <label>状态:</label>
            <select onchange="filterByStatus(this.value)">
                <option value="">全部</option>
                <option value="草稿"   <?php echo $status_filter=='草稿'   ?'selected':''; ?>>草稿</option>
                <option value="已确认" <?php echo $status_filter=='已确认' ?'selected':''; ?>>已确认</option>
                <option value="已成交" <?php echo $status_filter=='已成交' ?'selected':''; ?>>已成交</option>
                <option value="已过期" <?php echo $status_filter=='已过期' ?'selected':''; ?>>已过期</option>
            </select>
        </div>

        <div class="filter-item">
            <label>模板:</label>
            <select onchange="filterByTemplate(this.value)">
                <option value="">全部</option>
                <option value="assembled_pc"  <?php echo $template_filter=='assembled_pc'  ?'selected':''; ?>>组装电脑</option>
                <option value="brand_pc"      <?php echo $template_filter=='brand_pc'      ?'selected':''; ?>>品牌整机</option>
                <option value="weak_current"  <?php echo $template_filter=='weak_current'  ?'selected':''; ?>>弱电工程</option>
                <option value="strong_current"<?php echo $template_filter=='strong_current'?'selected':''; ?>>强电工程</option>
            </select>
        </div>
        <div class="filter-item">
            <input type="text" placeholder="搜索报价单号、客户、项目..."
                   value="<?php echo htmlspecialchars($search); ?>"
                   onkeypress="if(event.key==='Enter') searchQuotes(this.value)"
                   style="min-width:250px;">
        </div>
        <div class="filter-actions">
            <div class="btn-group">
                <button class="btn" style="background:linear-gradient(135deg,#fa8c16,#d46b08);color:#fff;" onclick="window.location.href='pc_config_list.php'">⚡ 常用配置</button>
                <button class="btn btn-primary" onclick="window.location.href='quote_create.php'">💻 组装电脑</button>
                <button class="btn btn-primary" onclick="window.location.href='quote_create_brand_pc.php'">🖥️ 品牌整机</button>
                <button class="btn btn-primary" onclick="window.location.href='quote_create_weak_current.php'">🔌 弱电工程</button>
                <button class="btn btn-primary" onclick="window.location.href='quote_create_strong_current.php'">⚡ 强电工程</button>
            </div>
            <button class="btn btn-secondary" onclick="clearFilters()">清除筛选</button>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2 class="card-title">报价单列表</h2>
            <button id="batchDeleteBtn" class="btn btn-secondary btn-sm" onclick="batchDelete()" style="display:none;">
                🗑️ 批量删除 (<span id="selectedCount">0</span>)
            </button>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width:50px; text-align:center;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" title="全选/取消全选">
                    </th>
                    <th>报价单号</th>
                    <th>客户信息</th>
                    <th>项目名称</th>
                    <th>模板类型</th>
                    <th>报价金额</th>
                    <th>报价日期</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($quotes && $quotes->num_rows > 0): ?>
                    <?php while ($row = $quotes->fetch_assoc()): ?>
                    <tr>
                        <td style="text-align:center;">
                            <input type="checkbox" class="quote-checkbox" value="<?php echo $row['id']; ?>"
                                   onchange="updateBatchDeleteButton()">
                        </td>
                        <td><strong><?php echo htmlspecialchars($row['quote_no']); ?></strong></td>
                        <td>
                            <?php echo htmlspecialchars($row['company_name'] ?? '未指定客户'); ?>
                            <?php if ($row['contact_name']): ?>
                                <br><small style="color:#a0aec0;"><?php echo htmlspecialchars($row['contact_name']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['project_name']): ?>
                                <div style="max-width:150px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    <?php echo htmlspecialchars($row['project_name']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color:#a0aec0;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $template_map = ['assembled_pc'=>'组装电脑','brand_pc'=>'品牌整机','weak_current'=>'弱电工程','strong_current'=>'强电工程'];
                            $template_name = $template_map[$row['template_type']] ?? $row['template_type'];
                            ?>
                            <span class="badge <?php echo $row['template_type']; ?>"><?php echo $template_name; ?></span>
                        </td>
                        <td style="font-weight:600;">¥<?php echo number_format($row['final_amount'], 2); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['quote_date'])); ?></td>
                        <td>
                            <?php
                            $status_class = ['草稿'=>'draft','已确认'=>'sent','已成交'=>'deal','已过期'=>'expired'];
                            $class = $status_class[$row['status']] ?? 'draft';
                            ?>
                            <span class="badge <?php echo $class; ?>"><?php echo $row['status']; ?></span>
                        </td>

                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-secondary" onclick="viewQuote(<?php echo $row['id']; ?>)">👁️ 查看</button>
                                <?php if ($row['status'] === '草稿'): ?>
                                    <button class="btn btn-sm btn-secondary" style="background:#dbeafe;color:#1f6dad;border-color:#93c5fd;"
                                            onclick="approveQuote(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['quote_no']); ?>')">✓ 客户确认</button>
                                <?php elseif ($row['status'] === '已确认'): ?>
                                    <button class="btn btn-sm btn-secondary"
                                            onclick="unapproveQuote(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['quote_no']); ?>')">↩ 退回草稿</button>
                                <?php endif; ?>
                                <?php if ($row['status'] == '草稿'): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="editQuote(<?php echo $row['id']; ?>,'<?php echo $row['template_type']; ?>')">✏️ 编辑</button>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-secondary" onclick="copyQuote(<?php echo $row['id']; ?>)" title="快速复制创建新报价单">📋 复制</button>
                                <?php if ($row['status'] == '草稿'): ?>
                                    <button class="btn btn-sm btn-secondary" onclick="deleteQuote(<?php echo $row['id']; ?>)">🗑️ 删除</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align:center; color:#a0aec0; padding:40px;">暂无报价单数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- 分页 -->
        <?php
        $qs = http_build_query(array_filter([
            'status'   => $status_filter,
            'template' => $template_filter,
            'search'   => $search,
            'approved' => $approved_filter,
        ]));
        $qs = $qs ? $qs . '&' : '';
        ?>
        <div class="pagination-bar">
            <span class="pagination-info">
                共 <?php echo $total_records; ?> 条，每页 <?php echo $per_page; ?> 条，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页
            </span>
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo $qs; ?>page=<?php echo $page - 1; ?>">← 上一页</a>
                <?php endif; ?>
                <?php
                $sp = max(1, $page - 3);
                $ep = min($total_pages, $page + 3);
                if ($sp > 1) {
                    echo '<a href="?'.$qs.'page=1">1</a>';
                    if ($sp > 2) echo '<span class="ellipsis">…</span>';
                }
                for ($i = $sp; $i <= $ep; $i++) {
                    if ($i == $page) echo '<span class="cur">'.$i.'</span>';
                    else echo '<a href="?'.$qs.'page='.$i.'">'.$i.'</a>';
                }
                if ($ep < $total_pages) {
                    if ($ep < $total_pages - 1) echo '<span class="ellipsis">…</span>';
                    echo '<a href="?'.$qs.'page='.$total_pages.'">'.$total_pages.'</a>';
                }
                ?>
                <?php if ($page < $total_pages): ?>
                    <a href="?<?php echo $qs; ?>page=<?php echo $page + 1; ?>">下一页 →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

function filterByStatus(status) {
    const url = new URL(window.location.href);
    url.searchParams.delete('page');
    status ? url.searchParams.set('status', status) : url.searchParams.delete('status');
    window.location.href = url.toString();
}
function filterByTemplate(template) {
    const url = new URL(window.location.href);
    url.searchParams.delete('page');
    template ? url.searchParams.set('template', template) : url.searchParams.delete('template');
    window.location.href = url.toString();
}
function searchQuotes(keyword) {
    const url = new URL(window.location.href);
    url.searchParams.delete('page');
    keyword ? url.searchParams.set('search', keyword) : url.searchParams.delete('search');
    window.location.href = url.toString();
}
function clearFilters() { window.location.href = 'quotes.php'; }
function viewQuote(id)  { window.location.href = 'quote_view.php?id=' + id; }
function editQuote(id, tpl) {
    const map = {
        assembled_pc:   'quote_edit_assembled_pc.php',
        brand_pc:       'quote_edit_brand_pc.php',
        weak_current:   'quote_edit_weak_current.php',
        strong_current: 'quote_edit_strong_current.php',
    };
    window.location.href = (map[tpl] || 'quote_edit.php') + '?id=' + id;
}

async function approveQuote(id, quoteNo) {
    const button = event.currentTarget || event.target;
    const orig = button.innerHTML;
    sapConfirm(`确定要确认报价单 "${quoteNo}" 吗？<br><br>确认后状态变为「已确认」，可用于创建送货单。`,
    async function() {
        button.disabled = true; button.innerHTML = '⏳ 审核中...';
        try {
            const r = await fetch('quote_approve.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF_TOKEN},body:`quote_id=${id}&action=approve`});
            const d = await r.json();
            if (d.success) { sapToast(d.message, 'success'); setTimeout(()=>window.location.reload(),1200); }
            else { sapToast(d.message, 'error'); button.disabled=false; button.innerHTML=orig; }
        } catch(e) { sapToast('审核时发生错误：'+e.message, 'error'); button.disabled=false; button.innerHTML=orig; }
    }, {title:'客户确认', okText:'确认'});
}
async function unapproveQuote(id, quoteNo) {
    const button = event.currentTarget || event.target;
    const orig = button.innerHTML;
    sapConfirm(`确定要将报价单 "${quoteNo}" 退回草稿吗？`,
    async function() {
        button.disabled = true; button.innerHTML = '⏳ 处理中...';
        try {
            const r = await fetch('quote_approve.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF_TOKEN},body:`quote_id=${id}&action=unapprove`});
            const d = await r.json();
            if (d.success) { sapToast(d.message, 'success'); setTimeout(()=>window.location.reload(),1200); }
            else { sapToast(d.message, 'error'); button.disabled=false; button.innerHTML=orig; }
        } catch(e) { sapToast('操作时发生错误：'+e.message, 'error'); button.disabled=false; button.innerHTML=orig; }
    }, {title:'退回草稿', okText:'确认退回'});
}
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.quote-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateBatchDeleteButton();
}
function updateBatchDeleteButton() {
    const checked = document.querySelectorAll('.quote-checkbox:checked');
    const btn = document.getElementById('batchDeleteBtn');
    if (checked.length > 0) { btn.style.display='inline-flex'; document.getElementById('selectedCount').textContent=checked.length; }
    else { btn.style.display='none'; document.getElementById('selectAll').checked=false; }
}
async function batchDelete() {
    const checkboxes = document.querySelectorAll('.quote-checkbox:checked');
    if (!checkboxes.length) { sapToast('请先选择要删除的报价单', 'warning'); return; }
    const ids = Array.from(checkboxes).map(cb => cb.value);
    const btn = document.getElementById('batchDeleteBtn');
    const orig = btn.innerHTML;
    sapConfirm(`确定要删除选中的 <strong>${ids.length}</strong> 个报价单吗？<br><br>⚠️ 此操作不可撤销！`,
    async function() {
        btn.disabled=true; btn.innerHTML='⏳ 删除中...';
        try {
            const r = await fetch('quote_batch_delete.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN},body:JSON.stringify({ids})});
            const d = await r.json();
            if (d.success) { sapToast(d.message, 'success'); setTimeout(()=>window.location.reload(),1200); }
            else { sapToast('删除失败：'+d.message, 'error'); btn.disabled=false; btn.innerHTML=orig; }
        } catch(e) { sapToast('删除时发生错误：'+e.message, 'error'); btn.disabled=false; btn.innerHTML=orig; }
    }, {danger:true, title:'批量删除报价单', okText:'确认删除'});
}
function deleteQuote(id) {
    sapConfirm('确定要删除这个报价单吗？<br><br>此操作不可撤销。',
    async function() {
        try {
            const r = await fetch('quote_delete.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':CSRF_TOKEN}, body:`id=${id}`});
            const d = await r.json();
            if (d.success) { sapToast(d.message||'删除成功', 'success'); setTimeout(()=>window.location.reload(),1000); }
            else { sapToast(d.message||'删除失败', 'error'); }
        } catch(e) { sapToast('删除时发生错误：'+e.message, 'error'); }
    },
    {danger:true, title:'删除报价单', okText:'确认删除'});
}
function copyQuote(id) {
    const btn = event.currentTarget || event.target;
    sapConfirm('确定要复制这个报价单吗？<br><br>将创建一个新的草稿报价单，您可以在此基础上修改。',
    function() {
        btn.disabled=true; btn.textContent='复制中...';
        window.location.href='quote_copy.php?id='+id;
    }, {title:'复制报价单', okText:'确认复制'});
}
document.addEventListener('DOMContentLoaded', function() {
    const successMsg = document.querySelector('.success-message');
    const errorMsg   = document.querySelector('.error-message');
    if (successMsg) setTimeout(() => { successMsg.style.opacity='0'; successMsg.style.transition='opacity 0.5s'; setTimeout(()=>successMsg.remove(),500); }, 3000);
    if (errorMsg)   setTimeout(() => { errorMsg.style.opacity='0';   errorMsg.style.transition='opacity 0.5s';   setTimeout(()=>errorMsg.remove(),500);   }, 5000);
});
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
