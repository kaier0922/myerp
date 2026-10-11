<?php
/**
 * 文件名: sn_tracker.php
 * 版本: 2.0
 * 功能: 序列号全生命周期管理（列表/搜索/新增/编辑/删除/状态流转）
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();

require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

/* ============================================================
   AJAX 接口
   ============================================================ */
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);

    // ── 状态变更 ──
    if ($action === 'update_status') {
        $sn  = trim($_POST['sn'] ?? '');
        $sta = trim($_POST['status'] ?? '');
        $allowed = ['in_stock','sold','repair','returned'];
        if (!$sn || !in_array($sta, $allowed)) { echo json_encode(['success'=>false,'message'=>'参数错误']); exit; }
        $out_date = in_array($sta, ['sold','returned']) ? date('Y-m-d H:i:s') : null;
        $stmt = $conn->prepare("UPDATE product_serials SET status=?, out_date=? WHERE sn_code=?");
        $stmt->bind_param('sss', $sta, $out_date, $sn);
        echo json_encode(['success'=>$stmt->execute(), 'message'=>$stmt->error ?: '已更新']);
        exit;
    }

    // ── 删除 ──
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM product_serials WHERE id=? AND (user_id=0 OR user_id=" . intval($_SESSION['user_id']) . ")");
        $stmt->bind_param('i', $id);
        echo json_encode(['success'=>$stmt->execute(), 'message'=>$stmt->error ?: '已删除']);
        exit;
    }

    // ── 新增 ──
    if ($action === 'add') {
        $fields = ['product_id','sn_code','supplier_id','customer_id','sale_date','warranty_until',
                   'host_sn','delivery_no','purchase_price','operator','status','in_date'];
        $d = [];
        foreach ($fields as $f) $d[$f] = trim($_POST[$f] ?? '');
        if (!$d['sn_code'] || !$d['product_id']) { echo json_encode(['success'=>false,'message'=>'序列号和产品不能为空']); exit; }
        $d['product_id']   = intval($d['product_id']);
        $d['supplier_id']  = $d['supplier_id'] ? intval($d['supplier_id']) : null;
        $d['customer_id']  = $d['customer_id'] ? intval($d['customer_id']) : null;
        $d['purchase_price']= $d['purchase_price'] ? floatval($d['purchase_price']) : null;
        $d['sale_date']    = $d['sale_date'] ?: null;
        $d['warranty_until']=$d['warranty_until'] ?: null;
        $d['in_date']      = $d['in_date'] ?: date('Y-m-d H:i:s');
        $d['status']       = $d['status'] ?: 'in_stock';
        $d['supplier_warranty_until'] = trim($_POST['supplier_warranty_until'] ?? '') ?: null;
        $stmt = $conn->prepare("INSERT INTO product_serials (product_id,sn_code,supplier_id,customer_id,sale_date,warranty_until,supplier_warranty_until,host_sn,delivery_no,purchase_price,operator,status,in_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isiiissssdsss', $d['product_id'],$d['sn_code'],$d['supplier_id'],$d['customer_id'],$d['sale_date'],$d['warranty_until'],$d['supplier_warranty_until'],$d['host_sn'],$d['delivery_no'],$d['purchase_price'],$d['operator'],$d['status'],$d['in_date']);
        echo json_encode(['success'=>$stmt->execute(), 'message'=>$stmt->error ?: '新增成功']);
        exit;
    }

    // ── 编辑保存 ──
    if ($action === 'edit') {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false,'message'=>'ID错误']); exit; }
        $fields = ['sn_code','product_id','supplier_id','customer_id','sale_date','warranty_until',
                   'host_sn','delivery_no','purchase_price','operator','status','in_date','out_date'];
        $d = [];
        foreach ($fields as $f) $d[$f] = trim($_POST[$f] ?? '');
        $d['product_id']    = intval($d['product_id']);
        $d['supplier_id']   = $d['supplier_id'] ? intval($d['supplier_id']) : null;
        $d['customer_id']   = $d['customer_id'] ? intval($d['customer_id']) : null;
        $d['purchase_price']= $d['purchase_price'] !== '' ? floatval($d['purchase_price']) : null;
        $d['sale_date']     = $d['sale_date']     ?: null;
        $d['warranty_until']= $d['warranty_until']?: null;
        $d['out_date']      = $d['out_date']      ?: null;
        $d['supplier_warranty_until'] = trim($_POST['supplier_warranty_until'] ?? '') ?: null;
        $stmt = $conn->prepare("UPDATE product_serials SET sn_code=?,product_id=?,supplier_id=?,customer_id=?,sale_date=?,warranty_until=?,supplier_warranty_until=?,host_sn=?,delivery_no=?,purchase_price=?,operator=?,status=?,in_date=?,out_date=? WHERE id=?");
        $stmt->bind_param('siiiiisssdssss i', $d['sn_code'],$d['product_id'],$d['supplier_id'],$d['customer_id'],$d['sale_date'],$d['warranty_until'],$d['host_sn'],$d['delivery_no'],$d['purchase_price'],$d['operator'],$d['status'],$d['in_date'],$d['out_date'],$id);
        echo json_encode(['success'=>$stmt->execute(), 'message'=>$stmt->error ?: '保存成功']);
        exit;
    }

    // ── 获取单条记录（编辑回填）──
    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        $stmt_tmp=$conn->prepare("SELECT * FROM product_serials WHERE id=?"); $stmt_tmp->bind_param("i",$id); $stmt_tmp->execute(); $row=$stmt_tmp->get_result()->fetch_assoc(); $stmt_tmp->close();
        echo json_encode(['success'=>(bool)$row, 'data'=>$row]);
        exit;
    }

    echo json_encode(['success'=>false,'message'=>'未知操作']);
    exit;
}

/* ============================================================
   列表页数据
   ============================================================ */
$kw     = trim($_GET['kw'] ?? '');
$sta    = $_GET['sta'] ?? '';
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$where = ['1=1'];
$params = []; $types = '';
if ($kw) {
    $where[] = "(ps.sn_code LIKE ? OR p.name LIKE ? OR COALESCE(c.short_name, c.company_name) AS company_name LIKE ?)";
    $kw2 = "%$kw%"; $params = [$kw2,$kw2,$kw2]; $types = 'sss';
}
if ($sta) { $where[] = "ps.status = '$sta'"; }
array_unshift($where, tenantWhere("ps"));
$where_sql = implode(' AND ', $where);

$count_res = $conn->query("SELECT COUNT(*) as n FROM product_serials ps LEFT JOIN products p ON ps.product_id=p.id LEFT JOIN customers c ON ps.customer_id=c.id WHERE $where_sql");
$total = 0;
if ($count_res) {
    if ($kw) {
        $stmt = $conn->prepare("SELECT COUNT(*) as n FROM product_serials ps LEFT JOIN products p ON ps.product_id=p.id LEFT JOIN customers c ON ps.customer_id=c.id WHERE $where_sql");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['n'];
    } else {
        $total = $count_res->fetch_assoc()['n'];
    }
}
$total_pages = ceil($total / $limit);

$list_sql = "SELECT ps.*, p.name as p_name, p.sku, s.company_name as s_name, COALESCE(c.short_name, c.company_name) AS company_name as c_name
             FROM product_serials ps
             LEFT JOIN products p ON ps.product_id=p.id
             LEFT JOIN suppliers s ON ps.supplier_id=s.id
             LEFT JOIN customers c ON ps.customer_id=c.id
             WHERE $where_sql ORDER BY ps.id DESC LIMIT $limit OFFSET $offset";

if ($kw) {
    $stmt = $conn->prepare($list_sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $list = $conn->query($list_sql)->fetch_all(MYSQLI_ASSOC);
}

// 统计
$stats = $conn->query("SELECT status, COUNT(*) as n FROM product_serials WHERE " . tenantWhere() . " GROUP BY status")->fetch_all(MYSQLI_ASSOC);
$stat_map = ['in_stock'=>0,'sold'=>0,'repair'=>0,'returned'=>0];
foreach ($stats as $s) $stat_map[$s['status']] = $s['n'];

// 下拉数据
$parent_cats = $conn->query("SELECT id, name FROM product_categories WHERE parent_id=0 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$child_cats  = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id>0 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$products    = $conn->query("SELECT p.id, p.name, p.sku, p.category_id, pc.parent_id as parent_cat_id FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.is_active=1 AND (p.user_id=0 OR p.user_id=" . intval($_SESSION['user_id']) . ") ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);
$suppliers = $conn->query("SELECT id, company_name FROM suppliers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<?php $page_title = '序列号管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

.
.page-sub { color:#718096; font-size:14px; margin-bottom:28px; }

.stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:white; border-radius:12px; padding:18px 20px; box-shadow:0 1px 3px rgba(0,0,0,.08); cursor:pointer; border:2px solid transparent; transition:all .2s; }
.stat-card:hover { transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.1); }
.stat-card.active { border-color:var(--c); }
.stat-card .n { font-size:28px; font-weight:700; color:var(--c); }
.stat-card .l { font-size:12px; color:#718096; margin-top:4px; }
.c-stock  { --c:#1f6dad; }
.c-sold   { --c:#3b82f6; }
.c-repair { --c:#f59e0b; }
.c-ret    { --c:#6b7280; }

.toolbar { background:white; border-radius:12px; padding:16px 20px; margin-bottom:16px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
.search-input { flex:1; min-width:200px; padding:10px 16px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; }
.search-input:focus { border-color:#1f6dad; }
.btn-gray { background:#f7fafc; color:#4a5568; border:1px solid #e2e8f0; }
.btn-gray:hover { background:#edf2f7; }
.btn-edit   { background:#ebf4ff; color:#2b6cb0; }
.btn-edit:hover { background:#bee3f8; }
.btn-del    { background:#fff5f5; color:#c53030; }
.btn-del:hover  { background:#fed7d7; }
.btn-status { background:#f0fff4; color:#276749; }
.btn-status:hover { background:#c6f6d5; }

.table-wrap { background:white; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; }
thead
tbody
tbody
tbody tr:last-child

/* 状态徽章 */
.badge-stock  { background:#d1fae5; color:#065f46; }
.badge-sold   { background:#dbeafe; color:#135286; }
.badge-repair { background:#fef3c7; color:#92400e; }
.badge-ret    { background:#f1f5f9; color:#475569; }

/* 保修状态 */
.w-ok  { color:#1f6dad; font-size:11px; }
.w-exp { color:#ef4444; font-size:11px; }
.w-na  { color:#a0aec0; font-size:11px; }

.pagination { display:flex; justify-content:center; gap:8px; padding:20px; }
.pg-btn { padding:8px 14px; border:1px solid #e2e8f0; border-radius:8px; background:white; color:#4a5568; font-size:13px; cursor:pointer; text-decoration:none; transition:all .2s; }
.pg-btn:hover { border-color:#1f6dad; color:#1f6dad; }
.pg-btn.active { background:linear-gradient(135deg,#1f6dad,#135286); color:white; border-color:transparent; }
.pg-btn.disabled { opacity:.4; pointer-events:none; }

.modal-overlay { position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:500; display:none; align-items:center; justify-content:center; }
.modal-overlay.show { display:flex; }
.modal { background:white; border-radius:16px; width:680px; max-width:95vw; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,.2); }
.modal-header { padding:20px 24px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; background:white; z-index:1; border-radius:16px 16px 0 0; }
.modal-title { font-size:16px; font-weight:700; color:#1a202c; }
.modal-close { width:32px; height:32px; background:#f7fafc; border:none; border-radius:8px; font-size:18px; cursor:pointer; color:#718096; }
.modal-
.modal-footer { padding:16px 24px; border-top:1px solid #e2e8f0; display:flex; justify-content:flex-end; gap:10px; }

/* 表单 */
.form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
.form-grid .full { grid-column:1/-1; }
.form-group label { display:block; font-size:12px; font-weight:600; color:#4a5568; margin-bottom:6px; }
.form-group input, .form-group select, .form-group textarea {
    width:100%; padding:10px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; outline:none; font-family:inherit;
}
.form-group input:focus, .form-group select:focus { border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }

.sn-detail { background:white; border-radius:12px; padding:28px; box-shadow:0 1px 3px rgba(0,0,0,.08); }
.sn-detail .field-row { display:flex; border-bottom:1px solid #f0f0f0; padding:12px 0; }
.sn-detail .fl { width:140px; font-size:13px; font-weight:600; color:#718096; flex-shrink:0; }
.sn-detail .fv { font-size:14px; color:#1a202c; font-weight:500; }
.timeline { margin-top:20px; }
.tl-item { display:flex; gap:16px; margin-bottom:16px; }
.tl-dot { width:12px; height:12px; border-radius:50%; background:#1f6dad; margin-top:4px; flex-shrink:0; position:relative; }
.tl-dot::before { content:''; position:absolute; top:12px; left:5px; width:2px; height:calc(100% + 8px); background:#e2e8f0; }
.tl-item:last-child .tl-dot::before { display:none; }
.tl-text .tl-time { font-size:11px; color:#a0aec0; }
.tl-text .tl-label { font-size:13px; font-weight:600; color:#2d3748; }

.empty { text-align:center; padding:60px 20px; color:#a0aec0; }
.empty .icon { font-size:48px; margin-bottom:12px; }

/* 流转按钮区 */
.action-bar { background:#f7fafc; border-radius:10px; padding:16px 20px; margin-top:16px; border:1px solid #e2e8f0; }

@media (max-width: 1024px) {
    .stat-row { grid-template-columns:repeat(2,1fr); }
    .form-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- 导航 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容 -->
<div class="main">
    <div style="display:flex; align-items:center; gap:16px; margin-bottom:6px;">
        <div class="page-title">🔍 序列号管理</div>
        <a href="products.php" class="btn btn-gray" style="font-size:13px; padding:8px 14px;">← 返回产品管理</a>
    </div>
    <div class="page-sub">产品序列号全生命周期：入库 → 销售 → 返修 → 退回，支持溯源查询与信息修改</div>

    <!-- 统计卡片 -->
    <div class="stat-row">
        <div class="stat-card c-stock <?=!$sta?'active':''?>" onclick="filterStat('')">
            <div class="n" style="color:#1f6dad;"><?=array_sum($stat_map)?></div>
            <div class="l">全部序列号</div>
        </div>
        <div class="stat-card c-stock <?=$sta==='in_stock'?'active':''?>" onclick="filterStat('in_stock')">
            <div class="n"><?=$stat_map['in_stock']?></div>
            <div class="l">📦 在库</div>
        </div>
        <div class="stat-card c-sold <?=$sta==='sold'?'active':''?>" onclick="filterStat('sold')">
            <div class="n"><?=$stat_map['sold']?></div>
            <div class="l">✅ 已售出</div>
        </div>
        <div class="stat-card c-repair <?=$sta==='repair'?'active':''?>" onclick="filterStat('repair')">
            <div class="n"><?=$stat_map['repair']?></div>
            <div class="l">🛠️ 返修中</div>
        </div>
    </div>

    <!-- 搜索工具栏 -->
    <div class="toolbar">
        <input class="search-input" id="kwInput" type="text" placeholder="🔍 搜索序列号 / 产品名称 / 客户名称..."
               value="<?=e($kw)?>" onkeydown="if(event.key==='Enter')doSearch()">
        <button class="btn btn-gray" onclick="doSearch()">搜索</button>
        <?php if($kw||$sta): ?>
        <a class="btn btn-gray" href="sn_tracker.php">清除筛选</a>
        <?php endif; ?>
        <button class="btn btn-primary" onclick="showAdd()">＋ 新增序列号</button>
    </div>

    <!-- 列表 -->
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>序列号</th>
                    <th>产品名称</th>
                    <th>供应商</th>
                    <th>客户</th>
                    <th>状态</th>
                    <th>渠道保修</th>
                    <th>渠道保修</th>
                    <th>客户保修</th>
                    <th>入库时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($list)): ?>
                <tr><td colspan="8"><div class="empty"><div class="icon">📭</div><div>暂无序列号数据</div></div></td></tr>
            <?php else: ?>
            <?php foreach ($list as $row):
                $w_ok = false; $w_exp = false; $w_str = '-';
                if ($row['warranty_until'] && $row['warranty_until'] !== '0000-00-00') {
                    $days = ceil((strtotime($row['warranty_until']) - time()) / 86400);
                    $w_exp = $days < 0;
                    $w_ok  = !$w_exp;
                    $w_str = $w_exp ? '已过保' : "剩 {$days} 天";
                }
                $badge_class = ['in_stock'=>'badge-stock','sold'=>'badge-sold','repair'=>'badge-repair','returned'=>'badge-ret'][$row['status']]??'badge-ret';
                $badge_label = ['in_stock'=>'在库','sold'=>'已售','repair'=>'返修','returned'=>'已退厂'][$row['status']]??$row['status'];
            ?>
            <tr>
                <td><code style="font-family:monospace;font-size:12px;background:#f7fafc;padding:3px 8px;border-radius:4px;"><?=e($row['sn_code'])?></code></td>
                <td>
                    <div style="font-weight:600;font-size:13px;"><?=e($row['p_name']??'-')?></div>
                    <?php if($row['sku']): ?><div style="font-size:11px;color:#a0aec0;"><?=e($row['sku'])?></div><?php endif; ?>
                </td>
                <td style="font-size:12px;color:#4a5568;"><?=e($row['s_name']??'-')?></td>
                <td style="font-size:12px;color:#4a5568;"><?=e($row['c_name']??'-')?></td>
                <td><span class="badge <?=$badge_class?>"><?=$badge_label?></span></td>
                <td>
                    <?php
                    $sw_str = '-'; $sw_ok = false; $sw_exp = false;
                    if ($row['supplier_warranty_until'] && $row['supplier_warranty_until'] !== '0000-00-00') {
                        $sw_days = ceil((strtotime($row['supplier_warranty_until']) - time()) / 86400);
                        $sw_exp = $sw_days < 0; $sw_ok = !$sw_exp;
                        $sw_str = $sw_exp ? '已过保' : "剩 {$sw_days} 天";
                    }
                    ?>
                    <?php if($sw_ok): ?><span class="w-ok">✓ <?=$sw_str?></span>
                    <?php elseif($sw_exp): ?><span class="w-exp">✗ <?=$sw_str?></span>
                    <?php else: ?><span class="w-na">未设置</span><?php endif; ?>
                </td>
                <td>
                    <?php if($w_ok): ?><span class="w-ok">✓ <?=$w_str?></span>
                    <?php elseif($w_exp): ?><span class="w-exp">✗ <?=$w_str?></span>
                    <?php else: ?><span class="w-na">未设置</span><?php endif; ?>
                </td>
                <td style="font-size:12px;color:#718096;"><?=substr($row['in_date']??'',0,10)?></td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button class="btn-sm btn-edit" onclick='showEdit(<?=json_encode($row)?>)'>✏️ 编辑</button>
                        <button class="btn-sm btn-status" onclick="showStatus('<?=e($row['sn_code'])?>','<?=$row['status']?>')">🔄 流转</button>
                        <button class="btn-sm btn-del" onclick="delRow(<?=$row['id']?>,'<?=e($row['sn_code'])?>')">🗑️</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- 分页 -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <a class="pg-btn <?=$page<=1?'disabled':''?>" href="?kw=<?=urlencode($kw)?>&sta=<?=$sta?>&page=<?=$page-1?>">‹ 上一页</a>
            <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
            <a class="pg-btn <?=$i==$page?'active':''?>" href="?kw=<?=urlencode($kw)?>&sta=<?=$sta?>&page=<?=$i?>"><?=$i?></a>
            <?php endfor; ?>
            <a class="pg-btn <?=$page>=$total_pages?'disabled':''?>" href="?kw=<?=urlencode($kw)?>&sta=<?=$sta?>&page=<?=$page+1?>">下一页 ›</a>
        </div>
        <?php endif; ?>
        <div style="padding:12px 20px;font-size:12px;color:#a0aec0;border-top:1px solid #f0f0f0;">
            共 <?=$total?> 条记录，第 <?=$page?>/<?=max(1,$total_pages)?> 页
        </div>
    </div>
</div>

<!-- ── 新增/编辑 模态框 ── -->
<div class="modal-overlay" id="formModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title" id="modalTitle">新增序列号</div>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="f_id">
            <div class="form-grid">
                <div class="form-group full">
                    <label>序列号 SN *</label>
                    <input type="text" id="f_sn" placeholder="如：SN20240001" style="font-family:monospace;">
                </div>
                <!-- 产品三级联动 -->
                <div class="form-group">
                    <label>产品大类</label>
                    <select id="f_parent_cat" onchange="onParentCatChange()">
                        <option value="">请选择大类...</option>
                        <?php foreach($parent_cats as $c): ?>
                        <option value="<?=$c['id']?>"><?=e($c['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>产品子类</label>
                    <select id="f_child_cat" onchange="onChildCatChange()" disabled>
                        <option value="">请先选大类...</option>
                        <?php foreach($child_cats as $c): ?>
                        <option value="<?=$c['id']?>" data-parent="<?=$c['parent_id']?>"><?=e($c['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>关键字过滤产品</label>
                    <input type="text" id="f_product_kw" placeholder="输入产品名或SKU关键字..." oninput="filterProducts()" disabled>
                </div>
                <div class="form-group full">
                    <label>选择产品 *</label>
                    <select id="f_product" size="5" style="height:120px;">
                        <option value="">请先选择分类...</option>
                    </select>
                    <!-- 隐藏所有产品数据供JS过滤 -->
                    <div id="all_products" style="display:none"><?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?></div>
                </div>
                <div class="form-group">
                    <label>供应商</label>
                    <select id="f_supplier">
                        <option value="">不指定</option>
                        <?php foreach($suppliers as $s): ?>
                        <option value="<?=$s['id']?>"><?=e($s['company_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>客户</label>
                    <select id="f_customer">
                        <option value="">不指定</option>
                        <?php foreach($customers as $c): ?>
                        <option value="<?=$c['id']?>"><?=e($c['company_name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>采购价 (¥)</label>
                    <input type="number" step="any" id="f_price" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select id="f_status">
                        <option value="in_stock">📦 在库</option>
                        <option value="sold">✅ 已售出</option>
                        <option value="repair">🛠️ 返修中</option>
                        <option value="returned">🔙 已退厂</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>入库时间</label>
                    <input type="datetime-local" id="f_indate">
                </div>
                <div class="form-group">
                    <label>销售日期</label>
                    <input type="date" id="f_saledate">
                </div>
                <div class="form-group">
                    <label>客户保修截止</label>
                    <input type="date" id="f_warranty">
                </div>
                <div class="form-group">
                    <label>渠道/上家保修截止</label>
                    <input type="date" id="f_sup_warranty">
                </div>
                <div class="form-group">
                    <label>渠道/上家保修截止</label>
                    <input type="date" id="f_sup_warranty">
                </div>
                <div class="form-group">
                    <label>出库时间</label>
                    <input type="datetime-local" id="f_outdate">
                </div>
                <div class="form-group">
                    <label>主机序列号</label>
                    <input type="text" id="f_hostsn" placeholder="所属整机SN">
                </div>
                <div class="form-group">
                    <label>关联送货单号</label>
                    <input type="text" id="f_delivery" placeholder="如：DL-20240101-001">
                </div>
                <div class="form-group full">
                    <label>操作人</label>
                    <input type="text" id="f_operator" placeholder="录入人姓名">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-gray" onclick="closeModal()">取消</button>
            <button class="btn btn-primary" onclick="saveForm()">💾 保存</button>
        </div>
    </div>
</div>

<!-- ── 状态流转 模态框 ── -->
<div class="modal-overlay" id="statusModal">
    <div class="modal" style="width:420px;">
        <div class="modal-header">
            <div class="modal-title">🔄 状态流转</div>
            <button class="modal-close" onclick="closeStatusModal()">×</button>
        </div>
        <div class="modal-body">
            <div style="font-size:14px;color:#4a5568;margin-bottom:16px;">
                序列号：<code id="sta_sn" style="font-family:monospace;background:#f7fafc;padding:2px 8px;border-radius:4px;"></code>
            </div>
            <div style="font-size:13px;color:#718096;margin-bottom:16px;">当前状态：<span id="sta_cur" style="font-weight:600;"></span></div>
            <div id="sta_btns" style="display:flex;flex-direction:column;gap:10px;"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-gray" onclick="closeStatusModal()">关闭</button>
        </div>
    </div>
</div>

<script>
/* ── 搜索/筛选 ── */
function doSearch() {
    const kw = document.getElementById('kwInput').value;
    location.href = 'sn_tracker.php?kw=' + encodeURIComponent(kw) + '&sta=<?=e($sta)?>';
}
function filterStat(sta) {
    location.href = 'sn_tracker.php?kw=<?=urlencode($kw)?>&sta=' + sta;
}

/* ── 新增 ── */
function showAdd() {
    document.getElementById('modalTitle').textContent = '新增序列号';
    document.getElementById('f_id').value = '';
    ['f_sn','f_product','f_supplier','f_customer','f_price','f_status','f_indate','f_saledate','f_warranty','f_sup_warranty','f_outdate','f_hostsn','f_delivery','f_operator'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = id === 'f_status' ? 'in_stock' : '';
    });
    // 重置三级联动
    document.getElementById('f_parent_cat').value = '';
    document.getElementById('f_child_cat').value = '';
    document.getElementById('f_child_cat').disabled = true;
    document.getElementById('f_product_kw').value = '';
    document.getElementById('f_product_kw').disabled = true;
    document.getElementById('f_product').innerHTML = '<option value="">请先选择分类...</option>';
    // 入库时间默认当前
    document.getElementById('f_indate').value = new Date().toISOString().slice(0,16);
    document.getElementById('f_operator').value = '<?=e($_SESSION['nickname']??'')?>';
    document.getElementById('formModal').classList.add('show');
}

/* ── 编辑 ── */
function showEdit(row) {
    document.getElementById('modalTitle').textContent = '编辑序列号';
    document.getElementById('f_id').value = row.id;
    document.getElementById('f_sn').value = row.sn_code || '';
    document.getElementById('f_product').value = row.product_id || '';
    document.getElementById('f_supplier').value = row.supplier_id || '';
    document.getElementById('f_customer').value = row.customer_id || '';
    document.getElementById('f_price').value = row.purchase_price || '';
    document.getElementById('f_status').value = row.status || 'in_stock';
    document.getElementById('f_indate').value = (row.in_date||'').replace(' ','T').slice(0,16);
    document.getElementById('f_outdate').value = (row.out_date||'').replace(' ','T').slice(0,16);
    document.getElementById('f_saledate').value = row.sale_date || '';
    document.getElementById('f_warranty').value = row.warranty_until || '';
    // 编辑时预选产品：直接设值并显示名称
    const allP = JSON.parse(document.getElementById('all_products').textContent);
    const curP = allP.find(p => p.id == row.product_id);
    if (curP) {
        // 设置大类
        document.getElementById('f_parent_cat').value = curP.parent_cat_id || '';
        onParentCatChange();
        // 设置子类
        document.getElementById('f_child_cat').value = curP.category_id || '';
        onChildCatChange();
        // 选中产品
        document.getElementById('f_product').value = row.product_id;
    }
    document.getElementById('f_sup_warranty').value = row.supplier_warranty_until || '';
    document.getElementById('f_sup_warranty').value = row.supplier_warranty_until || '';
    document.getElementById('f_hostsn').value = row.host_sn || '';
    document.getElementById('f_delivery').value = row.delivery_no || '';
    document.getElementById('f_operator').value = row.operator || '';
    document.getElementById('formModal').classList.add('show');
}

// ── 产品三级联动 ──
const _allProducts = JSON.parse(document.getElementById('all_products')?.textContent || '[]');

function onParentCatChange() {
    const parentId = parseInt(document.getElementById('f_parent_cat').value);
    const childSel = document.getElementById('f_child_cat');
    // 过滤子类
    Array.from(childSel.options).forEach(o => {
        if (!o.value) { o.style.display=''; return; }
        o.style.display = parseInt(o.dataset.parent) === parentId ? '' : 'none';
    });
    childSel.value = '';
    childSel.disabled = !parentId;
    document.getElementById('f_product_kw').disabled = true;
    document.getElementById('f_product_kw').value = '';
    document.getElementById('f_product').innerHTML = '<option value="">请先选择子类...</option>';
}

function onChildCatChange() {
    const childId = parseInt(document.getElementById('f_child_cat').value);
    document.getElementById('f_product_kw').disabled = !childId;
    document.getElementById('f_product_kw').value = '';
    filterProducts();
}

function filterProducts() {
    const childId = parseInt(document.getElementById('f_child_cat').value);
    const kw = document.getElementById('f_product_kw').value.toLowerCase();
    const sel = document.getElementById('f_product');
    const filtered = _allProducts.filter(p => {
        if (parseInt(p.category_id) !== childId) return false;
        if (!kw) return true;
        return (p.name||'').toLowerCase().includes(kw) || (p.sku||'').toLowerCase().includes(kw);
    });
    sel.innerHTML = filtered.length
        ? filtered.map(p => `<option value="${p.id}">${p.name}${p.sku?' ['+p.sku+']':''}</option>`).join('')
        : '<option value="">无匹配产品</option>';
    sel.value = '';
}

function closeModal() { document.getElementById('formModal').classList.remove('show'); }

/* ── 保存 ── */
async function saveForm() {
    const id = document.getElementById('f_id').value;
    const action = id ? 'edit' : 'add';
    const body = new URLSearchParams({
        action, id,
        sn_code:       document.getElementById('f_sn').value,
        product_id:    document.getElementById('f_product').value,
        supplier_id:   document.getElementById('f_supplier').value,
        customer_id:   document.getElementById('f_customer').value,
        purchase_price:document.getElementById('f_price').value,
        status:        document.getElementById('f_status').value,
        in_date:       document.getElementById('f_indate').value.replace('T',' '),
        out_date:      document.getElementById('f_outdate').value.replace('T',' '),
        sale_date:     document.getElementById('f_saledate').value,
        warranty_until:document.getElementById('f_warranty').value,
        supplier_warranty_until:document.getElementById('f_sup_warranty').value,
        supplier_warranty_until:document.getElementById('f_sup_warranty').value,
        host_sn:       document.getElementById('f_hostsn').value,
        delivery_no:   document.getElementById('f_delivery').value,
        operator:      document.getElementById('f_operator').value,
    });
    const r = await fetch('sn_tracker.php', {method:'POST', body});
    const d = await r.json();
    if (d.success) { sapToast('✅ ' + d.message, 'info'); closeModal(); location.reload(); }
    else sapToast('❌ ' + d.message, 'info');
}

/* ── 删除 ── */
async function delRow(id, sn) {
    if (!confirm(`确定删除序列号 [${sn}]？此操作不可恢复！`)) return;
    const r = await fetch('sn_tracker.php', {method:'POST', body: new URLSearchParams({action:'delete', id})});
    const d = await r.json();
    if (d.success) { sapToast('✅ 已删除', 'success'); location.reload(); }
    else sapToast('❌ ' + d.message, 'info');
}

/* ── 状态流转 ── */
const STATUS_FLOW = {
    in_stock: [
        {to:'sold',   label:'✅ 标记为已售出',   cls:'background:#1f6dad;color:white;'},
        {to:'repair', label:'🛠️ 标记为返修中',   cls:'background:#f59e0b;color:white;'},
    ],
    sold: [
        {to:'repair', label:'🛠️ 客户坏件返修',   cls:'background:#f59e0b;color:white;'},
        {to:'in_stock',label:'📦 撤销售出转回库存', cls:'background:#1f6dad;color:white;'},
    ],
    repair: [
        {to:'in_stock',label:'✅ 修好回库',       cls:'background:#1f6dad;color:white;'},
        {to:'returned',label:'🔙 退回厂家核销',   cls:'background:#ef4444;color:white;'},
    ],
    returned: [
        {to:'in_stock',label:'📦 重新入库',       cls:'background:#1f6dad;color:white;'},
    ]
};
const STATUS_LABEL = {in_stock:'在库',sold:'已售出',repair:'返修中',returned:'已退厂'};

let _sta_sn = '', _sta_cur = '';
function showStatus(sn, cur) {
    _sta_sn = sn; _sta_cur = cur;
    document.getElementById('sta_sn').textContent = sn;
    document.getElementById('sta_cur').textContent = STATUS_LABEL[cur] || cur;
    const flows = STATUS_FLOW[cur] || [];
    const container = document.getElementById('sta_btns');
    if (!flows.length) {
        container.innerHTML = '<div style="color:#a0aec0;font-size:13px;">当前状态无可用流转操作</div>';
    } else {
        container.innerHTML = flows.map(f =>
            `<button onclick="doStatus('${f.to}')" style="padding:14px 20px;border:none;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;${f.cls}transition:all .2s;">${f.label}</button>`
        ).join('');
    }
    document.getElementById('statusModal').classList.add('show');
}
function closeStatusModal() { document.getElementById('statusModal').classList.remove('show'); }

async function doStatus(newSta) {
    if (!confirm(`确定将 [${_sta_sn}] 状态改为「${STATUS_LABEL[newSta]}」？`)) return;
    const r = await fetch('sn_tracker.php', {method:'POST', body: new URLSearchParams({action:'update_status', sn:_sta_sn, status:newSta})});
    const d = await r.json();
    if (d.success) { sapToast('✅ 状态已更新', 'success'); closeStatusModal(); location.reload(); }
    else sapToast('❌ ' + d.message, 'info');
}

// ESC关闭
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeModal(); closeStatusModal(); }
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
