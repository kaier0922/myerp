<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$nickname=$_SESSION['nickname']??''; $role=$_SESSION['role']??'';
$conn=getDBConnection();
$id=intval($_GET['id']??0);
$stmt=$conn->prepare("SELECT * FROM sales_contracts WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("i",$id); $stmt->execute();
$c=$stmt->get_result()->fetch_assoc();
if(!$c){die('合同不存在');}
$stmt2=$conn->prepare("SELECT * FROM sales_contract_items WHERE contract_id=? ORDER BY item_no");
$stmt2->bind_param("i",$id); $stmt2->execute();
$items=$stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$customers=$conn->query("SELECT id, company_name, COALESCE(short_name, company_name) AS display_name, address, contact_name, phone FROM customers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

// 违约责任/争议解决：合同本身未填写时，用行业通用条款模板兜底显示（仍可编辑覆盖）
$_tpl_stmt = $conn->prepare("SELECT breach_terms, dispute_terms FROM contract_templates WHERE user_id=? AND contract_type=?");
$_tpl_uid = intval($_SESSION['user_id']);
$_ct_for_tpl = $c['contract_type'] ?? 'sales';
$_tpl_stmt->bind_param("is", $_tpl_uid, $_ct_for_tpl);
$_tpl_stmt->execute();
$_tpl = $_tpl_stmt->get_result()->fetch_assoc() ?: [];
if (empty($c['breach_terms']))  $c['breach_terms']  = $_tpl['breach_terms']  ?? '';
if (empty($c['dispute_terms'])) $c['dispute_terms'] = $_tpl['dispute_terms'] ?? '';

// 本公司信息：合同本身未填写时，用系统设置兜底显示（仍可编辑覆盖）
require_once 'settings_helper.php';
$_cfg = getUserSettings($conn, intval($_SESSION['user_id']));
if (empty($c['own_name']))    $c['own_name']    = $_cfg['company_name']    ?? '';
if (empty($c['own_address'])) $c['own_address'] = $_cfg['company_address'] ?? '';
if (empty($c['own_contact'])) $c['own_contact'] = $_cfg['contact_person']  ?? '';
if (empty($c['own_phone']))   $c['own_phone']   = $_cfg['contact_phone']   ?? '';

// 分类层级（供明细行级联选择用）
$_cats_all = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE is_active=1 ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
$_npc_parents = array_values(array_filter($_cats_all, fn($c) => $c['parent_id'] == 0));
$_npc_by_parent = [];
foreach ($_cats_all as $_c) { if ($_c['parent_id'] > 0) $_npc_by_parent[$_c['parent_id']][] = $_c; }
$npcParentsJson  = json_encode($_npc_parents,   JSON_UNESCAPED_UNICODE);
$npcByParentJson = json_encode($_npc_by_parent, JSON_UNESCAPED_UNICODE);

$conn->close();
function e($v){return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
$ct=$c['contract_type']??'sales';
$type_titles=['sales'=>'💼 编辑销售合同','construction'=>'🏗️ 编辑施工合同','outsource'=>'💻 编辑IT外包合同','rental'=>'📋 编辑租赁合同'];
?>
<?php $page_title = '编辑合同'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.card-title { font-size:15px;font-weight:600;color:#1a202c; }
.card-
.form-row.cols2 { grid-template-columns:1fr 1fr; }
.form-row.cols3 { grid-template-columns:1fr 1fr 1fr; }
.form-row.full  { grid-template-columns:1fr; }
.form-label .req { color:#e53e3e; }
.form-label .hint { font-weight:400;color:#a0aec0;font-size:12px;margin-left:4px; }
.form-control { padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s; }
.form-control:focus { outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1); }
textarea.form-control { min-height:90px;resize:vertical; }
.form-control[readonly] { background:#f7fafc;color:#718096; }
.btn-danger { background:#fee2e2;color:#c53030;border:1px solid #fca5a5; }

/* ── 合同明细表 ── */
.items-wrap { overflow-x:auto; border-radius:8px; border:1px solid #e2e8f0; }
.items-table { width:100%; border-collapse:collapse; min-width:780px; }
.items-table thead th {
    background:#f7fafc; padding:10px 8px; text-align:left;
    font-size:12px; font-weight:700; color:#4a5568; letter-spacing:0.3px;
    border-bottom:2px solid #e2e8f0; white-space:nowrap;
}
.items-table thead th:first-child { border-radius:8px 0 0 0; }
.items-table thead th:last-child  { border-radius:0 8px 0 0; }
.items-table tbody tr { transition:background .15s; }
.items-table tbody tr:hover { background:#f8faff; }
.items-table tbody td {
    padding:8px 7px; border-bottom:1px solid #f0f4ff; vertical-align:top;
}
.items-table tbody tr:last-child td { border-bottom:none; }
/* 单元格内 input/select 统一样式 */
.items-table td input[type=text],
.items-table td input[type=number] {
    width:100%; padding:7px 10px; border:1px solid #e2e8f0; border-radius:6px;
    font-size:13px; font-family:inherit; transition:border-color .2s,box-shadow .2s;
    box-sizing:border-box; background:#fff;
}
.items-table td input[type=text]:focus,
.items-table td input[type=number]:focus {
    outline:none; border-color:#1f6dad; box-shadow:0 0 0 2px rgba(31,109,173,.12);
}
.items-table td input[readonly],
.items-table td input[readonly]:focus {
    background:#f0f4ff; color:#3730a3; font-weight:700;
    border-color:#c7d2fe; box-shadow:none; cursor:default;
}
.items-table .seq-no {
    text-align:center; color:#94a3b8; font-size:13px; font-weight:600;
    width:40px; padding-top:14px;
}
.items-table .del-cell { text-align:center; padding-top:10px; }

/* 产品库级联选择 */
.prod-lib select, .prod-lib input[type=text] {
    width:100%; margin-bottom:4px; font-size:12px; padding:5px 8px;
    border:1px solid #e2e8f0; border-radius:5px; box-sizing:border-box;
    font-family:inherit; transition:border-color .2s;
}
.prod-lib select:focus, .prod-lib input[type=text]:focus {
    outline:none; border-color:#1f6dad;
}
.prod-lib select:disabled { background:#f7fafc; color:#a0aec0; }
.prod-cust-label {
    font-size:11px; color:#92400e; background:#fffbeb;
    padding:3px 8px; border-radius:4px; margin-bottom:4px;
    display:flex; align-items:center; gap:4px;
}
.prod-cust input[type=text] {
    width:100%; font-size:13px; padding:7px 10px;
    border:1px solid #f59e0b; border-radius:6px; box-sizing:border-box;
    font-family:inherit; margin-bottom:4px; transition:border-color .2s;
}
.prod-cust input[type=text]:focus { outline:none; border-color:#d97706; box-shadow:0 0 0 2px rgba(245,158,11,.15); }
.prod-back-btn {
    font-size:11px; color:#1f6dad; background:none; border:none;
    cursor:pointer; padding:0; text-decoration:underline; opacity:.8;
}
.prod-back-btn:hover { opacity:1; }

/* 添加行按钮 */
.btn-add-row {
    display:flex; align-items:center; justify-content:center; gap:8px;
    padding:11px 20px; background:#1f6dad; color:white; border:none;
    border-radius:8px; font-size:14px; font-weight:600; cursor:pointer;
    width:100%; margin-top:10px; transition:background .2s;
}
.btn-add-row:hover { background:#1a5c94; }

/* 删除行按钮 */
.btn-del-row {
    padding:5px 12px; background:#fff5f5; color:#c53030;
    border:1px solid #fca5a5; border-radius:6px; font-size:12px;
    cursor:pointer; white-space:nowrap; transition:all .15s;
}
.btn-del-row:hover { background:#fee2e2; }

/* 合计栏 */
.total-bar {
    display:flex; justify-content:flex-end; align-items:center; gap:16px;
    padding:14px 20px; background:linear-gradient(135deg,#eef2ff,#e0e7ff);
    border-radius:8px; margin-top:12px; font-size:16px; font-weight:700;
    color:#3730a3; border:1px solid #c7d2fe;
}
.type-grid { display:grid;grid-template-columns:repeat(4,1fr);gap:12px; }
.type-card { border:2px solid #e2e8f0;border-radius:10px;padding:14px;cursor:pointer;transition:all .2s;text-align:center;background:white; }
.type-card:hover { border-color:#1f6dad; }
.type-card.active { border-color:#1f6dad;background:#eef2ff; }
.type-card.active-construction { border-color:#f59e0b;background:#fffbeb; }
.type-card.active-outsource { border-color:#135286;background:#ecfdf5; }
.type-card.active-rental { border-color:#135286;background:#f5f3ff; }
.type-icon { font-size:28px;margin-bottom:6px; }
.type-name { font-size:13px;font-weight:600;color:#1a202c; }
.type-desc { font-size:11px;color:#718096;margin-top:3px; }
.section-block { display:none; }
.section-block.show { display:block; }
.badge-type { display:inline-block;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600; }
.badge-sales { background:#dbeafe;color:#135286; }
.badge-rental { background:#ede9fe;color:#6d28d9; }
.badge-construction { background:#fef3c7;color:#92400e; }
.badge-outsource { background:#d1fae5;color:#065f46; }
/* 产品库级联选择 */
.prod-lib select,.prod-lib input[type=text]{width:100%;margin-bottom:3px;font-size:12px;padding:3px 6px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;font-family:inherit;}
.prod-cust-label{font-size:11px;color:#92400e;background:#fffbeb;padding:2px 6px;border-radius:3px;margin-bottom:3px;}
.prod-cust input[type=text]{width:100%;font-size:12px;padding:3px 6px;border:1px solid #f59e0b;border-radius:4px;box-sizing:border-box;margin-bottom:2px;font-family:inherit;}
.prod-back-btn{font-size:11px;color:#1f6dad;background:none;border:none;cursor:pointer;padding:0;}</style></head><body>

<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main-content">
<div class="page-header">
    <h1 class="page-title"><?=$type_titles[$ct]??'编辑合同'?></h1>
    <a href="contract_view.php?id=<?=$id?>" class="btn btn-secondary btn-sm">← 返回查看</a>
</div>
<div style="max-width:1100px;margin:0 auto;">
<form method="POST" action="contract_update.php">
<input type="hidden" name="id" value="<?=$id?>">

<!-- 基本信息 -->
<div class="content-card">
    <div class="card-header"><span class="card-title">👤 基本信息</span></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:18px;">
            <div class="form-group"><label class="form-label">合同编号</label>
                <input class="form-control" value="<?=e($c['contract_no'])?>" readonly></div>
            <div class="form-group"><label class="form-label">客户<span class="req">*</span></label>
                <select name="customer_id" class="form-control" required onchange="fillCustomer(this)">
                    <?php foreach($customers as $cu): ?>
                    <option value="<?=$cu['id']?>"
                        data-name="<?=e($cu['company_name'])?>"
                        data-address="<?=e($cu['address'])?>"
                        data-contact="<?=e($cu['contact_name'])?>"
                        data-phone="<?=e($cu['phone'])?>"
                        <?=$cu['id']==$c['customer_id']?'selected':''?>><?=e($cu['display_name'])?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="customer_name" id="customerName"
                    class="form-control" style="margin-top:8px;"
                    placeholder="客户名称（公司全称）" value="<?=e($c['customer_name'])?>"></div>
            <div class="form-group"><label class="form-label">合同日期</label>
                <input type="date" name="contract_date" class="form-control" value="<?=e($c['contract_date'])?>"></div>
            <div class="form-group"><label class="form-label">状态</label>
                <select name="status" class="form-control">
                    <option value="draft"  <?=$c['status']==='draft' ?'selected':''?>>草稿</option>
                    <option value="active" <?=$c['status']==='active'?'selected':''?>>生效</option>
                </select></div>
            <div class="form-group"><label class="form-label">客户地址</label>
                <input type="text" name="customer_address" id="customerAddress" class="form-control" value="<?=e($c['customer_address'])?>"></div>
            <div class="form-group"><label class="form-label">联系人</label>
                <input type="text" name="contact_person" id="contactPerson" class="form-control" value="<?=e($c['customer_contact'])?>"></div>
            <div class="form-group"><label class="form-label">联系电话</label>
                <input type="text" name="contact_phone" id="contactPhone" class="form-control" value="<?=e($c['customer_phone'])?>"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:18px;margin-top:14px;padding-top:14px;border-top:1px dashed #e2e8f0;">
            <div class="form-group"><label class="form-label">本公司名称</label>
                <input type="text" name="own_name" class="form-control" value="<?=e($c['own_name'])?>"></div>
            <div class="form-group"><label class="form-label">本公司地址</label>
                <input type="text" name="own_address" class="form-control" value="<?=e($c['own_address'])?>"></div>
            <div class="form-group"><label class="form-label">本公司联系人</label>
                <input type="text" name="own_contact" class="form-control" value="<?=e($c['own_contact'])?>"></div>
            <div class="form-group"><label class="form-label">本公司联系电话</label>
                <input type="text" name="own_phone" class="form-control" value="<?=e($c['own_phone'])?>"></div>
        </div>
    </div>
</div>

<!-- 条款（按类型显示） -->
<?php if($ct==='sales'||$ct==='rental'): ?>
<div class="content-card">
    <div class="card-header"><span class="card-title">📄 合同条款</span></div>
    <div class="card-body"><div class="form-row cols3">
        <div class="form-group"><label class="form-label"><?=$ct==='rental'?'租金及付款方式':'付款条款'?></label>
            <textarea name="payment_terms" class="form-control"><?=e($c['payment_terms'])?></textarea></div>
        <div class="form-group"><label class="form-label"><?=$ct==='rental'?'交付与归还':'交付条款'?></label>
            <textarea name="delivery_terms" class="form-control"><?=e($c['delivery_terms'])?></textarea></div>
        <div class="form-group"><label class="form-label"><?=$ct==='rental'?'损坏赔偿条款':'质保条款'?></label>
            <textarea name="warranty_terms" class="form-control"><?=e($c['warranty_terms'])?></textarea></div>
    </div>
    <div class="form-row cols2" style="margin-top:12px;">
        <div class="form-group"><label class="form-label">违约责任</label>
            <textarea name="breach_terms" class="form-control"><?=e($c['breach_terms'])?></textarea></div>
        <div class="form-group"><label class="form-label">争议解决</label>
            <textarea name="dispute_terms" class="form-control"><?=e($c['dispute_terms'])?></textarea></div>
    </div>
    <?php if($ct==='rental'): ?>
    <div class="form-row cols2" style="margin-top:12px;">
        <div class="form-group"><label class="form-label">租赁开始日期</label>
            <input type="date" name="work_start" class="form-control" value="<?=e($c['work_start'])?>"></div>
        <div class="form-group"><label class="form-label">租赁结束日期</label>
            <input type="date" name="work_end" class="form-control" value="<?=e($c['work_end'])?>"></div>
    </div><?php endif; ?>
    </div>
</div>
<?php elseif($ct==='construction'): ?>
<div class="content-card">
    <div class="card-header"><span class="card-title">🏗️ 工程信息</span></div>
    <div class="card-body">
        <div class="form-row cols2">
            <div class="form-group"><label class="form-label">工程开始日期</label>
                <input type="date" name="work_start" class="form-control" value="<?=e($c['work_start'])?>"></div>
            <div class="form-group"><label class="form-label">工程结束日期</label>
                <input type="date" name="work_end" class="form-control" value="<?=e($c['work_end'])?>"></div>
        </div>
        <div class="form-row full"><div class="form-group"><label class="form-label">工程范围与内容</label>
            <textarea name="service_scope" class="form-control" rows="4"><?=e($c['service_scope'])?></textarea></div></div>
        <div class="form-row cols3">
            <div class="form-group"><label class="form-label">付款方式</label>
                <textarea name="payment_terms" class="form-control"><?=e($c['payment_terms'])?></textarea></div>
            <div class="form-group"><label class="form-label">验收标准</label>
                <textarea name="acceptance_terms" class="form-control"><?=e($c['acceptance_terms'])?></textarea></div>
            <div class="form-group"><label class="form-label">质保条款</label>
                <textarea name="warranty_terms" class="form-control"><?=e($c['warranty_terms'])?></textarea></div>
        </div>
        <div class="form-row cols2" style="margin-top:12px;">
            <div class="form-group"><label class="form-label">违约责任</label>
                <textarea name="breach_terms" class="form-control"><?=e($c['breach_terms'])?></textarea></div>
            <div class="form-group"><label class="form-label">争议解决</label>
                <textarea name="dispute_terms" class="form-control"><?=e($c['dispute_terms'])?></textarea></div>
        </div>
    </div>
</div>
<?php elseif($ct==='outsource'): ?>
<div class="content-card">
    <div class="card-header"><span class="card-title">💻 服务信息</span></div>
    <div class="card-body">
        <div class="form-row cols3">
            <div class="form-group"><label class="form-label">服务开始日期</label>
                <input type="date" name="work_start" class="form-control" value="<?=e($c['work_start'])?>"></div>
            <div class="form-group"><label class="form-label">服务结束日期</label>
                <input type="date" name="work_end" class="form-control" value="<?=e($c['work_end'])?>"></div>
            <div class="form-group"><label class="form-label">服务方式</label>
                <select name="service_mode" class="form-control">
                    <option value="onsite"  <?=$c['service_mode']==='onsite' ?'selected':''?>>驻场服务</option>
                    <option value="remote"  <?=$c['service_mode']==='remote' ?'selected':''?>>远程服务</option>
                    <option value="hybrid"  <?=$c['service_mode']==='hybrid' ?'selected':''?>>驻场+远程混合</option>
                </select></div>
        </div>
        <div class="form-row full"><div class="form-group"><label class="form-label">服务范围</label>
            <textarea name="service_scope" class="form-control" rows="4"><?=e($c['service_scope'])?></textarea></div></div>
        <div class="form-row cols3">
            <div class="form-group"><label class="form-label">SLA / 响应时间</label>
                <textarea name="sla_terms" class="form-control"><?=e($c['sla_terms'])?></textarea></div>
            <div class="form-group"><label class="form-label">付款方式</label>
                <textarea name="payment_terms" class="form-control"><?=e($c['payment_terms'])?></textarea></div>
            <div class="form-group"><label class="form-label">保密条款</label>
                <textarea name="warranty_terms" class="form-control"><?=e($c['warranty_terms'])?></textarea></div>
        </div>
        <div class="form-row cols2" style="margin-top:12px;">
            <div class="form-group"><label class="form-label">违约责任</label>
                <textarea name="breach_terms" class="form-control"><?=e($c['breach_terms'])?></textarea></div>
            <div class="form-group"><label class="form-label">争议解决</label>
                <textarea name="dispute_terms" class="form-control"><?=e($c['dispute_terms'])?></textarea></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 合同明细 -->
<div class="content-card">
    <div class="card-header">
        <span class="card-title">📦 合同明细</span>
        <span style="font-size:12px;color:#94a3b8;font-weight:400;">选大类→选子类→过滤→选型号；产品库没有的点"✏️ 自定义输入"</span>
    </div>
    <div class="card-body" style="padding:16px;">
    <div class="items-wrap">
    <table class="items-table">
    <thead><tr>
        <th style="width:40px;text-align:center;">序号</th>
        <th style="min-width:210px;">名称 <span style="color:#e53e3e;">*</span></th>
        <th>规格说明</th>
        <th style="width:64px;">单位</th>
        <th style="width:84px;">数量</th>
        <th style="width:114px;">单价(¥)</th>
        <th style="width:114px;color:#3730a3;">金额(¥)</th>
        <th style="width:58px;text-align:center;">删除</th>
    </tr></thead>
    <tbody id="itemsBody">
    <?php foreach($items as $it): $n=$it['item_no']; ?>
    <tr>
        <td class="seq-no"><?=$n?></td>
        <td>
            <input type="hidden" name="items[<?=$n?>][product_name]" id="cpname_<?=$n?>" value="<?=e($it['product_name'])?>">
            <input type="hidden" name="items[<?=$n?>][custom_name]"  id="ccname_<?=$n?>" value="">
            <div id="clib_<?=$n?>" style="display:none;" class="prod-lib">
                <select onchange="cParentChange(<?=$n?>,this.value)"><option value="">① 选产品大类</option></select>
                <select id="csub_<?=$n?>" onchange="cSubChange(<?=$n?>,this)" disabled><option value="">② 选子类</option></select>
                <input id="ckw_<?=$n?>" placeholder="③ 关键字过滤..." oninput="cFilter(<?=$n?>,this.value)" disabled>
                <select id="cpsel_<?=$n?>" onchange="cProdSel(<?=$n?>,this)" disabled><option value="">④ 选具体型号</option></select>
            </div>
            <div id="ccust_<?=$n?>" class="prod-cust">
                <div class="prod-cust-label">✏️ 自定义产品名称</div>
                <input type="text" id="ccustinp_<?=$n?>" value="<?=e($it['product_name'])?>"
                    oninput="cSyncPname(<?=$n?>,this.value)" placeholder="产品/项目名称 *">
                <button type="button" class="prod-back-btn" onclick="cEnterLib(<?=$n?>)">← 从产品库选择</button>
            </div>
        </td>
        <td><input type="text" name="items[<?=$n?>][specification]" value="<?=e($it['specification'])?>"></td>
        <td><input type="text"   name="items[<?=$n?>][unit]"     value="<?=e($it['unit'])?>"></td>
        <td><input type="number" name="items[<?=$n?>][quantity]"  value="<?=$it['quantity']?>" step="0.01" oninput="calcRow(this)"></td>
        <td><input type="number" name="items[<?=$n?>][price]"     value="<?=$it['price']?>"    step="0.01" oninput="calcRow(this)"></td>
        <td><input type="number" name="items[<?=$n?>][amount]"    value="<?=$it['amount']?>"   readonly></td>
        <td class="del-cell"><button type="button" class="btn-del-row" onclick="delRow(this)">删除</button></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </div>
    <button type="button" class="btn-add-row" onclick="addRow()">＋ 添加明细行</button>
    <div class="total-bar">
        合计金额：<span style="font-size:22px;">¥<span id="totalAmt"><?=number_format($c['total_amount'],2)?></span></span>
        <input type="hidden" name="total_amount" id="totalInput" value="<?=$c['total_amount']?>">
    </div>
    </div>
</div>
<div class="content-card">
    <div class="card-header"><span class="card-title">📝 备注</span></div>
    <div class="card-body" style="padding:16px 20px;">
        <textarea name="notes" class="form-control" rows="4" style="width:100%;min-height:100px;resize:vertical;display:block;font-size:14px;"><?=e($c['notes'])?></textarea>
    </div>
</div>
<div class="content-card"><div class="form-actions">
    <a href="contract_view.php?id=<?=$id?>" class="btn btn-secondary">取消</a>
    <button type="submit" class="btn btn-primary">💾 保存修改</button>
</div></div>
</form></div><!-- end max-width -->
<script>
let rowNum = <?=count($items)>0?max(array_column($items,'item_no'))+1:1?>;
function fillCustomer(sel){
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customerName').value = opt.dataset.name || '';
    if(document.getElementById('customerAddress')) document.getElementById('customerAddress').value = opt.dataset.address || '';
    if(document.getElementById('contactPerson'))    document.getElementById('contactPerson').value    = opt.dataset.contact || '';
    if(document.getElementById('contactPhone'))     document.getElementById('contactPhone').value     = opt.dataset.phone   || '';
}

/* ── 产品库级联选择 ── */
const NPC_PARENT_CATS   = <?php echo $npcParentsJson; ?>;
const NPC_CATS_BY_PARENT = <?php echo $npcByParentJson; ?>;
const _npcParentOpts = NPC_PARENT_CATS.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
const _npcProdCache  = {};

function _libHtml(n) {
    return `<select onchange="cParentChange(${n},this.value)"><option value="">① 选产品大类</option>${_npcParentOpts}</select>
<select id="csub_${n}" onchange="cSubChange(${n},this)" disabled><option value="">② 选子类</option></select>
<input id="ckw_${n}" placeholder="③ 关键字过滤..." oninput="cFilter(${n},this.value)" disabled style="width:100%;margin-bottom:3px;font-size:12px;padding:3px 6px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
<select id="cpsel_${n}" onchange="cProdSel(${n},this)" disabled><option value="">④ 选具体型号</option></select>`;
}

function addRow(){
    const tbody=document.getElementById('itemsBody'),n=rowNum++,tr=document.createElement('tr');
    tr.innerHTML=`
    <td class="seq-no">${n}</td>
    <td>
        <input type="hidden" name="items[${n}][product_name]" id="cpname_${n}" value="">
        <input type="hidden" name="items[${n}][custom_name]"  id="ccname_${n}" value="">
        <div id="clib_${n}" class="prod-lib">${_libHtml(n)}</div>
        <div id="ccust_${n}" class="prod-cust" style="display:none;">
            <div class="prod-cust-label">✏️ 自定义（保存自动入库）</div>
            <input type="text" id="ccustinp_${n}" placeholder="产品/项目名称 *" oninput="cSyncPname(${n},this.value)">
            <button type="button" class="prod-back-btn" onclick="cEnterLib(${n})">← 返回产品库</button>
        </div>
    </td>
    <td><input type="text"   name="items[${n}][specification]" id="cspec_${n}"></td>
    <td><input type="text"   name="items[${n}][unit]"  id="cunit_${n}"  value="项"></td>
    <td><input type="number" name="items[${n}][quantity]" value="1" step="0.01" oninput="calcRow(this)"></td>
    <td><input type="number" name="items[${n}][price]" id="cprice_${n}" value="0" step="0.01" oninput="calcRow(this)"></td>
    <td><input type="number" name="items[${n}][amount]" value="0" readonly></td>
    <td class="del-cell"><button type="button" class="btn-del-row" onclick="delRow(this)">删除</button></td>`;
    tbody.appendChild(tr);
}

function cParentChange(n, parentId) {
    const sub=document.getElementById('csub_'+n), kw=document.getElementById('ckw_'+n), psel=document.getElementById('cpsel_'+n);
    sub.innerHTML='<option value="">② 选子类</option>'; sub.disabled=true;
    if(kw){kw.value=''; kw.disabled=true;}
    psel.innerHTML='<option value="">④ 选具体型号</option>'; psel.disabled=true;
    document.getElementById('cpname_'+n).value='';
    if(!parentId) return;
    const children=NPC_CATS_BY_PARENT[parentId]||[];
    if(!children.length){ _cLoadProds(n,parentId); }
    else { children.forEach(c=>sub.add(new Option(c.name,c.id))); sub.disabled=false; }
}
function cSubChange(n, sel) {
    const catId=sel.value;
    const kw=document.getElementById('ckw_'+n), psel=document.getElementById('cpsel_'+n);
    if(kw){kw.value=''; kw.disabled=true;}
    psel.innerHTML='<option value="">加载中...</option>'; psel.disabled=true;
    document.getElementById('cpname_'+n).value='';
    if(!catId) return;
    _cLoadProds(n,catId);
}
async function _cLoadProds(n, catId) {
    if(!_npcProdCache[catId]){
        const r=await fetch('api_products_by_category.php?cat_id='+catId);
        _npcProdCache[catId]=await r.json();
    }
    _cRenderSel(n,_npcProdCache[catId],'');
}
function _cRenderSel(n, prods, kw) {
    const psel=document.getElementById('cpsel_'+n), kwEl=document.getElementById('ckw_'+n);
    const filtered=kw?prods.filter(p=>(p.name||'').toLowerCase().includes(kw.toLowerCase())||(p.spec||'').toLowerCase().includes(kw.toLowerCase())):prods;
    let html='<option value="">④ 选具体型号</option>';
    html+='<option value="__custom__" style="background:#fef3c7;font-weight:bold;">✏️ 自定义输入（不在产品库）</option>';
    filtered.forEach(p=>{
        const lbl=p.name+(p.spec?' · '+p.spec.substring(0,20):'')+'  ¥'+parseFloat(p.default_price||0).toFixed(0);
        html+=`<option value="${p.id}" data-name="${(p.name||'').replace(/"/g,'&quot;')}"
            data-spec="${(p.spec||'').replace(/"/g,'&quot;')}" data-unit="${(p.unit||'项').replace(/"/g,'&quot;')}"
            data-price="${p.default_price||0}">${lbl}</option>`;
    });
    psel.innerHTML=html; psel.disabled=false; if(kwEl) kwEl.disabled=false;
}
function cFilter(n, kw) {
    const sub=document.getElementById('csub_'+n);
    const catId=sub&&!sub.disabled&&sub.value?sub.value:(NPC_PARENT_CATS.find(c=>true)||{}).id||0;
    const libDiv=document.getElementById('clib_'+n);
    const firstSel=libDiv&&libDiv.querySelector('select');
    const pid=firstSel?firstSel.value:0;
    const actualCatId=sub&&sub.value?sub.value:pid;
    if(actualCatId&&_npcProdCache[actualCatId]) _cRenderSel(n,_npcProdCache[actualCatId],kw);
}
function cProdSel(n, sel) {
    if(sel.value==='__custom__'){
        document.getElementById('clib_'+n).style.display='none';
        document.getElementById('ccust_'+n).style.display='';
        document.getElementById('cpname_'+n).value='';
        document.getElementById('ccname_'+n).value='';
        setTimeout(()=>document.getElementById('ccustinp_'+n)?.focus(),50);
        return;
    }
    if(!sel.value) return;
    const opt=sel.options[sel.selectedIndex];
    document.getElementById('cpname_'+n).value=opt.dataset.name||'';
    const sp=document.getElementById('cspec_'+n); if(sp) sp.value=opt.dataset.spec||'';
    const un=document.getElementById('cunit_'+n); if(un) un.value=opt.dataset.unit||'项';
    const pr=document.getElementById('cprice_'+n);
    if(pr){pr.value=parseFloat(opt.dataset.price||0).toFixed(2);pr.dispatchEvent(new Event('input'));}
}
function cEnterLib(n) {
    document.getElementById('ccust_'+n).style.display='none';
    document.getElementById('clib_'+n).style.display='';
    document.getElementById('ccustinp_'+n).value='';
    document.getElementById('cpname_'+n).value='';
    document.getElementById('ccname_'+n).value='';
}
function cSyncPname(n, val) {
    document.getElementById('cpname_'+n).value=val;
    document.getElementById('ccname_'+n).value=val;
}

function calcRow(inp){
    const tr=inp.closest('tr');
    const qty=parseFloat(tr.querySelector('input[name*="[quantity]"]').value)||0;
    const prc=parseFloat(tr.querySelector('input[name*="[price]"]').value)||0;
    tr.querySelector('input[name*="[amount]"]').value=(qty*prc).toFixed(2);
    calcTotal();
}
function calcTotal(){
    let t=0;document.querySelectorAll('input[name*="[amount]"]').forEach(i=>t+=parseFloat(i.value)||0);
    document.getElementById('totalAmt').textContent=t.toFixed(2);
    document.getElementById('totalInput').value=t.toFixed(2);
}
function delRow(btn){
    if(document.getElementById('itemsBody').rows.length>1){btn.closest('tr').remove();calcTotal();}
    else sapToast('至少保留一行', 'info');
}
calcTotal();
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

</body></html>
