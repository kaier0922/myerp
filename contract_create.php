<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$nickname = $_SESSION['nickname'] ?? ''; $role = $_SESSION['role'] ?? '';
$conn = getDBConnection();
$customers = $conn->query("SELECT id, company_name, COALESCE(short_name, company_name) AS display_name, address, contact_name, phone FROM customers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);
$contract_no = 'HT' . date('Ymd') . str_pad(rand(1,9999),4,'0',STR_PAD_LEFT);
require_once 'settings_helper.php';
$cfg = getUserSettings($conn, intval($_SESSION['user_id']));

// 分类层级（供明细行级联选择用）
$_cats_all = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE is_active=1 ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
$_npc_parents = array_values(array_filter($_cats_all, fn($c) => $c['parent_id'] == 0));
$_npc_by_parent = [];
foreach ($_cats_all as $_c) { if ($_c['parent_id'] > 0) $_npc_by_parent[$_c['parent_id']][] = $_c; }
$npcParentsJson  = json_encode($_npc_parents,   JSON_UNESCAPED_UNICODE);
$npcByParentJson = json_encode($_npc_by_parent, JSON_UNESCAPED_UNICODE);

$conn->close();
function e($v){return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
?>
<?php $page_title = '新建合同 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.card-title{font-size:15px;font-weight:600;color:#1a202c;}
.card-
.form-row.cols3{grid-template-columns:1fr 1fr 1fr;}
.form-label .req{color:#e53e3e;}
.form-control{padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
textarea.form-control{min-height:110px;resize:vertical;line-height:1.6;}
.btn-danger{background:#fee2e2;color:#c53030;border:1px solid #fca5a5;}
.type-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
.type-card{border:2px solid #e2e8f0;border-radius:10px;padding:14px;cursor:pointer;transition:all .2s;text-align:center;background:white;}
.type-card:hover{border-color:#1f6dad;}
.type-card.sel-sales{border-color:#1f6dad;background:#eef2ff;}
.type-card.sel-construction{border-color:#f59e0b;background:#fffbeb;}
.type-card.sel-outsource{border-color:#0891b2;background:#ecfeff;}
.type-card.sel-rental{border-color:#135286;background:#f5f3ff;}
.type-icon{font-size:28px;margin-bottom:6px;}
.type-name{font-size:13px;font-weight:600;color:#1a202c;}
.type-desc{font-size:11px;color:#718096;margin-top:3px;}
.items-wrap{overflow-x:auto;border-radius:8px;border:1px solid #e2e8f0;}
.items-table{width:100%;border-collapse:collapse;min-width:700px;}
.items-table thead th{background:#f7fafc;padding:10px 8px;text-align:left;font-size:12px;font-weight:700;color:#4a5568;letter-spacing:.3px;border-bottom:2px solid #e2e8f0;white-space:nowrap;}
.items-table tbody tr:hover{background:#f8faff;}
.items-table tbody td{padding:8px 7px;border-bottom:1px solid #f0f4ff;vertical-align:top;}
.items-table tbody tr:last-child td{border-bottom:none;}
.items-table td input[type=text],
.items-table td input[type=number]{width:100%;padding:7px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;transition:border-color .2s,box-shadow .2s;box-sizing:border-box;background:#fff;}
.items-table td input:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 2px rgba(31,109,173,.12);}
.items-table td input[readonly],.items-table td input[readonly]:focus{background:#f0f4ff;color:#3730a3;font-weight:700;border-color:#c7d2fe;box-shadow:none;cursor:default;}
.items-table .seq-no{text-align:center;color:#94a3b8;font-size:13px;font-weight:600;width:40px;padding-top:14px;}
.items-table .del-cell{text-align:center;padding-top:10px;}
.btn-del-row{padding:5px 12px;background:#fff5f5;color:#c53030;border:1px solid #fca5a5;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap;transition:all .15s;}
.btn-del-row:hover{background:#fee2e2;}
.btn-add-row{display:flex;align-items:center;justify-content:center;gap:8px;padding:11px 20px;background:#1f6dad;color:white;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;width:100%;margin-top:10px;transition:background .2s;}
.btn-add-row:hover{background:#1a5c94;}
.total-bar{display:flex;justify-content:flex-end;align-items:center;gap:16px;padding:14px 20px;background:linear-gradient(135deg,#eef2ff,#e0e7ff);border-radius:8px;margin-top:12px;font-size:16px;font-weight:700;color:#3730a3;border:1px solid #c7d2fe;}
/* 产品库级联选择 */
.prod-lib select,.prod-lib input[type=text]{width:100%;margin-bottom:4px;font-size:12px;padding:5px 8px;border:1px solid #e2e8f0;border-radius:5px;box-sizing:border-box;font-family:inherit;transition:border-color .2s;}
.prod-lib select:focus,.prod-lib input[type=text]:focus{outline:none;border-color:#1f6dad;}
.prod-lib select:disabled{background:#f7fafc;color:#a0aec0;}
.prod-cust-label{font-size:11px;color:#92400e;background:#fffbeb;padding:3px 8px;border-radius:4px;margin-bottom:4px;display:flex;align-items:center;gap:4px;}
.prod-cust input[type=text]{width:100%;font-size:13px;padding:7px 10px;border:1px solid #f59e0b;border-radius:6px;box-sizing:border-box;font-family:inherit;margin-bottom:4px;transition:border-color .2s;}
.prod-cust input[type=text]:focus{outline:none;border-color:#d97706;box-shadow:0 0 0 2px rgba(245,158,11,.15);}
.prod-back-btn{font-size:11px;color:#1f6dad;background:none;border:none;cursor:pointer;padding:0;text-decoration:underline;opacity:.8;}
.prod-back-btn:hover{opacity:1;}
/* 施工/外包专属字段 */
.extra-fields{display:none;}
.extra-fields.show{display:block;}
.loading-mask{display:none;position:absolute;inset:0;background:rgba(255,255,255,.7);border-radius:12px;align-items:center;justify-content:center;font-size:14px;color:#1f6dad;z-index:10;}
.loading-mask.show{display:flex;}
</style></head><body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
<div class="page-header">
    <h1 class="page-title" id="pageTitle">📝 新建合同</h1>
    <a href="contracts.php" class="btn btn-secondary btn-sm">← 返回列表</a>
</div>
<form method="POST" action="contract_save.php" onsubmit="return prepareRentalSla()">
<input type="hidden" name="contract_type" id="contractType" value="sales">
<input type="hidden" name="contract_no"   value="<?=$contract_no?>">

<!-- 合同类型 -->
<div class="content-card">
    <div class="card-header"><span class="card-title">📌 合同类型</span></div>
    <div class="card-body">
        <div class="type-grid" style="grid-template-columns:repeat(3,1fr);">
            <div class="type-card sel-sales" id="tab-sales" onclick="setType('sales')">
                <div class="type-icon">💼</div><div class="type-name">销售合同</div>
                <div class="type-desc">产品/设备销售</div>
            </div>
            <div class="type-card" id="tab-construction" onclick="setType('construction')">
                <div class="type-icon">🏗️</div><div class="type-name">施工合同</div>
                <div class="type-desc">综合布线/机房/监控</div>
            </div>
            <div class="type-card" id="tab-outsource" onclick="setType('outsource')">
                <div class="type-icon">💻</div><div class="type-name">IT外包合同</div>
                <div class="type-desc">运维/驻场/远程支持</div>
            </div>
            <div class="type-card" id="tab-rental" onclick="setType('rental')">
                <div class="type-icon">📋</div><div class="type-name">租赁合同</div>
                <div class="type-desc">设备租赁</div>
            </div>
            <div class="type-card" id="tab-software" onclick="setType('software')">
                <div class="type-icon">💾</div><div class="type-name">软件开发合同</div>
                <div class="type-desc">系统定制开发</div>
            </div>
            <div class="type-card" id="tab-loan" onclick="setType('loan')">
                <div class="type-icon">📦</div><div class="type-name">设备借用合同</div>
                <div class="type-desc">设备临时借用</div>
            </div>
        </div>
    </div>
</div>

<!-- 基本信息 -->
<div class="content-card">
    <div class="card-header"><span class="card-title">👤 基本信息</span></div>
    <div class="card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;">
            <div class="form-group">
                <label class="form-label">合同编号</label>
                <input class="form-control" value="<?=$contract_no?>" readonly>
            </div>
            <div class="form-group">
                <label class="form-label">客户<span class="req">*</span></label>
                <select class="form-control" id="customerSelect" onchange="fillCustomer(this)">
                    <option value="">-- 手动输入 / 选择客户 --</option>
                    <?php foreach($customers as $c): ?>
                    <option value="<?=$c['id']?>"
                        data-name="<?=e($c['company_name'])?>"
                        data-address="<?=e($c['address'])?>"
                        data-contact="<?=e($c['contact_name'])?>"
                        data-phone="<?=e($c['phone'])?>"><?=e($c['display_name'])?></option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="customer_id" id="customerId">
                <input type="text" name="customer_name" id="customerName"
                       class="form-control" style="margin-top:8px;"
                       placeholder="客户名称（公司全称，从上方选择或手动输入）" required>
            </div>
            <div class="form-group">
                <label class="form-label">客户地址</label>
                <input type="text" name="customer_address" id="customerAddress"
                       class="form-control" placeholder="客户地址（选填，自动带出可编辑）">
            </div>
            <div class="form-group">
                <label class="form-label">联系人</label>
                <input type="text" name="contact_person" id="contactPerson"
                       class="form-control" placeholder="联系人姓名（选填）">
            </div>
            <div class="form-group">
                <label class="form-label">联系电话</label>
                <input type="text" name="contact_phone" id="contactPhone"
                       class="form-control" placeholder="联系电话（选填）">
            </div>
            <div class="form-group">
                <label class="form-label">合同日期<span class="req">*</span></label>
                <input type="date" name="contract_date" class="form-control" value="<?=date('Y-m-d')?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">状态</label>
                <select name="status" class="form-control">
                    <option value="draft">草稿</option>
                    <option value="active">生效</option>
                </select>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:18px;margin-top:14px;padding-top:14px;border-top:1px dashed #e2e8f0;">
            <div class="form-group">
                <label class="form-label">本公司名称</label>
                <input type="text" name="own_name" class="form-control" value="<?=e($cfg['company_name'])?>">
            </div>
            <div class="form-group">
                <label class="form-label">本公司地址</label>
                <input type="text" name="own_address" class="form-control" value="<?=e($cfg['company_address'])?>">
            </div>
            <div class="form-group">
                <label class="form-label">本公司联系人</label>
                <input type="text" name="own_contact" class="form-control" value="<?=e($cfg['contact_person'])?>">
            </div>
            <div class="form-group">
                <label class="form-label">本公司联系电话</label>
                <input type="text" name="own_phone" class="form-control" value="<?=e($cfg['contact_phone'])?>">
            </div>
        </div>
        <!-- 施工/外包/租赁：工期/服务期 -->
        <div class="extra-fields" id="extra-dates">
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
                <div class="form-group">
                    <label class="form-label" id="date-start-label">开始日期</label>
                    <input type="date" name="work_start" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label" id="date-end-label">结束日期</label>
                    <input type="date" name="work_end" class="form-control">
                </div>
                <div class="extra-fields" id="extra-service-mode">
                    <div class="form-group">
                        <label class="form-label">服务方式</label>
                        <select name="service_mode" class="form-control">
                            <option value="onsite">驻场服务</option>
                            <option value="visit">上门服务</option>
                            <option value="remote">远程服务</option>
                            <option value="hybrid">驻场+远程混合</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 合同条款（从模板库动态加载） -->
<div class="content-card" style="position:relative;">
    <div class="loading-mask" id="loadingMask">⏳ 加载条款模板...</div>
    <div class="card-header">
        <span class="card-title" id="clauseTitle">📄 合同条款</span>
        <a href="contract_templates.php" target="_blank" style="font-size:12px;color:#1f6dad;text-decoration:none;">⚙️ 管理模板</a>
    </div>
    <div class="card-body" id="clauseBody">
        <!-- AJAX动态填入 -->
        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
            <div class="form-group">
                <label class="form-label">付款条款</label>
                <textarea name="payment_terms"  class="form-control" id="f-payment_terms"></textarea>
            </div>
            <div class="form-group" id="grp-delivery_terms">
                <label class="form-label" id="lbl-delivery_terms">交付条款</label>
                <textarea name="delivery_terms" class="form-control" id="f-delivery_terms"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" id="lbl-warranty_terms">质保条款</label>
                <textarea name="warranty_terms" class="form-control" id="f-warranty_terms"></textarea>
            </div>
        </div>
        <div class="form-row" style="grid-template-columns:1fr 1fr;margin-top:12px;">
            <div class="form-group">
                <label class="form-label">违约责任</label>
                <textarea name="breach_terms" class="form-control" id="f-breach_terms"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">争议解决</label>
                <textarea name="dispute_terms" class="form-control" id="f-dispute_terms"></textarea>
            </div>
        </div>
        <!-- 施工专属：工程范围+验收标准 -->
        <div class="extra-fields" id="grp-construction">
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">工程范围与内容</label>
                <textarea name="service_scope" class="form-control" id="f-service_scope" rows="3" placeholder="详细描述工程内容"></textarea>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">验收标准</label>
                <textarea name="acceptance_terms" class="form-control" id="f-acceptance_terms"></textarea>
            </div>
        </div>
        <!-- IT外包专属：服务范围+SLA -->
        <div class="extra-fields" id="grp-outsource">
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">服务范围与内容</label>
                <textarea name="service_scope" class="form-control" id="f-service_scope2" rows="3" placeholder="详细描述服务内容"></textarea>
            </div>
            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">SLA服务级别协议</label>
                <textarea name="sla_terms" class="form-control" id="f-sla_terms"></textarea>
            </div>
        </div>
        <!-- 租赁专属：租金单价/计费周期/押金（真实租金，跟下面设备明细的市场估值是两回事） -->
        <div class="extra-fields" id="grp-rental">
            <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;margin-top:12px;">
                <div class="form-group">
                    <label class="form-label">租金单价(¥)</label>
                    <input type="number" id="f-rent_price" class="form-control" value="0" step="0.01" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">计费周期</label>
                    <select id="f-rent_cycle" class="form-control">
                        <option value="day">天</option>
                        <option value="week">周</option>
                        <option value="month" selected>月</option>
                        <option value="year">年</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">押金(¥)</label>
                    <input type="number" id="f-rent_deposit" class="form-control" value="0" step="0.01" min="0">
                </div>
            </div>
            <div style="font-size:12px;color:#888;margin-top:-6px;">💡 这里填的是真实要收的租金单价，跟下面"租赁设备清单"里的市场估值（只用于损坏赔偿参考）是两回事，保存时会按租赁期限自动折算成租金合计</div>
        </div>
    </div>
</div>

<!-- 合同明细 -->
<div class="content-card">
    <div class="card-header"><span class="card-title" id="itemsTitle">📦 合同明细</span></div>
    <div class="card-body" style="padding:16px;">
        <div class="items-wrap">
        <table class="items-table"><thead><tr>
            <th style="width:42px;">序号</th>
            <th id="col-name" style="min-width:180px;">产品/服务名称<span style="color:#e53e3e;margin-left:2px;">*</span></th>
            <th>规格说明</th>
            <th style="width:58px;">单位</th>
            <th style="width:78px;">数量</th>
            <th style="width:110px;">单价(¥)</th>
            <th style="width:110px;">金额(¥)</th>
            <th style="width:54px;text-align:center;">删除</th>
        </tr></thead>
        <tbody id="itemsBody"></tbody></table>
        </div>
        <button type="button" class="btn-add-row" onclick="addRow()">➕ 添加明细行</button>
        <div class="total-bar">合计金额：¥<span id="totalAmt">0.00</span>
            <input type="hidden" name="total_amount" id="totalInput" value="0"></div>
    </div>
</div>

<!-- 备注 -->
<div class="content-card">
    <div class="card-header"><span class="card-title">📝 备注</span></div>
    <div class="card-body" style="padding:16px 20px;">
        <textarea name="notes" class="form-control" rows="4"
            style="width:100%;min-height:100px;resize:vertical;display:block;font-size:14px;"
            placeholder="其他说明、特殊约定等..."></textarea>
    </div>
</div>
<div class="content-card"><div class="form-actions">
    <a href="contracts.php" class="btn btn-secondary">取消</a>
    <button type="submit" class="btn btn-primary">💾 保存合同</button>
</div></div>
</form></div>

<script>
let curType = 'sales';
let rowNum  = 1;
const TYPE_META = {
    sales:        {title:'💼 新建销售合同',    items:'📦 产品/服务明细',   col:'产品/服务名称',   unit:'台', delivery_label:'交付条款',   warranty_label:'质保条款'},
    construction: {title:'🏗️ 新建施工合同',    items:'🔧 工程费用清单',   col:'工程项目名称',   unit:'项', delivery_label:'',           warranty_label:'质保条款'},
    outsource:    {title:'💻 新建IT外包合同',  items:'💻 服务费用清单',   col:'服务项目名称',   unit:'月', delivery_label:'',           warranty_label:'保密条款'},
    rental:       {title:'📋 新建租赁合同',    items:'📦 租赁设备清单',   col:'设备名称',       unit:'台', delivery_label:'交付与归还', warranty_label:'损坏赔偿条款'},
    software:     {title:'💾 新建软件开发合同', items:'💾 开发费用清单',   col:'开发模块/功能',  unit:'项', delivery_label:'',           warranty_label:'维护与培训'},
    loan:         {title:'📦 新建设备借用合同', items:'📦 借用设备清单',   col:'设备名称',       unit:'台', delivery_label:'借用期限及归还', warranty_label:'损坏赔偿条款'},
};
const SEL_CLASS = {sales:'sel-sales',construction:'sel-construction',outsource:'sel-outsource',rental:'sel-rental',software:'sel-outsource',loan:'sel-rental'};

async function setType(t) {
    curType = t;
    document.getElementById('contractType').value = t;

    // 类型卡片样式
    ['sales','construction','outsource','rental','software','loan'].forEach(k => {
        document.getElementById('tab-'+k).className = 'type-card' + (k===t?' '+SEL_CLASS[k]:'');
    });

    const m = TYPE_META[t];
    document.getElementById('pageTitle').textContent  = m.title;
    document.getElementById('itemsTitle').textContent = m.items;
    document.getElementById('col-name').innerHTML     = m.col + '<span style="color:#e53e3e;">*</span>';

    // 工期/服务期日期
    const showDates = ['construction','outsource','rental'].includes(t);
    document.getElementById('extra-dates').className = 'extra-fields' + (showDates?' show':'');
    document.getElementById('extra-service-mode').className = 'extra-fields' + (t==='outsource'?' show':'');
    const dsl = document.getElementById('date-start-label');
    const del = document.getElementById('date-end-label');
    if(t==='construction'){dsl.textContent='工程开始日期';del.textContent='工程竣工日期';}
    else if(t==='outsource'){dsl.textContent='服务开始日期';del.textContent='服务结束日期';}
    else if(t==='rental'){dsl.textContent='租赁开始日期';del.textContent='租赁结束日期';}

    // 条款字段标签
    document.getElementById('lbl-warranty_terms').textContent = m.warranty_label;
    if(m.delivery_label) {
        document.getElementById('grp-delivery_terms').style.display = '';
        document.getElementById('lbl-delivery_terms').textContent = m.delivery_label;
    } else {
        document.getElementById('grp-delivery_terms').style.display = 'none';
    }

    // 专属字段
    document.getElementById('grp-construction').className = 'extra-fields' + (t==='construction'?' show':'');
    document.getElementById('grp-outsource').className    = 'extra-fields' + (t==='outsource'  ?' show':'');
    document.getElementById('grp-rental').className       = 'extra-fields' + (t==='rental'      ?' show':'');

    // AJAX 读取模板条款
    document.getElementById('loadingMask').className = 'loading-mask show';
    try {
        const resp = await fetch('contract_template_api.php?type=' + t);
        const d    = await resp.json();
        document.getElementById('f-payment_terms').value  = d.payment_terms  || '';
        document.getElementById('f-delivery_terms').value = d.delivery_terms  || '';
        document.getElementById('f-warranty_terms').value = d.warranty_terms  || '';
        document.getElementById('f-acceptance_terms').value = d.acceptance_terms || '';
        document.getElementById('f-sla_terms').value      = d.sla_terms       || '';
        document.getElementById('f-breach_terms').value   = d.breach_terms    || '';
        document.getElementById('f-dispute_terms').value  = d.dispute_terms   || '';
    } catch(e) {
        console.error('模板加载失败', e);
    }
    document.getElementById('loadingMask').className = 'loading-mask';
}

function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customerId').value    = opt.value || '';
    document.getElementById('customerName').value  = opt.dataset.name    || '';
    if(document.getElementById('customerAddress')) document.getElementById('customerAddress').value = opt.dataset.address || '';
    if(document.getElementById('contactPerson')) document.getElementById('contactPerson').value = opt.dataset.contact || '';
    if(document.getElementById('contactPhone'))  document.getElementById('contactPhone').value  = opt.dataset.phone   || '';
}

/* ── 产品库级联选择 ── */
const NPC_PARENT_CATS    = <?php echo $npcParentsJson; ?>;
const NPC_CATS_BY_PARENT = <?php echo $npcByParentJson; ?>;
const _npcParentOpts = NPC_PARENT_CATS.map(c=>`<option value="${c.id}">${c.name.replace(/</g,'&lt;')}</option>`).join('');
const _npcProdCache  = {};

function _libHtml(n, defUnit) {
    return `<select onchange="cParentChange(${n},this.value)"><option value="">① 选产品大类</option>${_npcParentOpts}</select>
<select id="csub_${n}" onchange="cSubChange(${n},this)" disabled><option value="">② 选子类</option></select>
<input id="ckw_${n}" placeholder="③ 关键字过滤..." oninput="cFilter(${n},this.value)" disabled style="width:100%;margin-bottom:3px;font-size:12px;padding:3px 6px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
<select id="cpsel_${n}" onchange="cProdSel(${n},this)" disabled><option value="">④ 选具体型号</option></select>`;
}

// 租赁合同保存前，把租金单价/周期/押金这几个结构化字段组装成contract_view.php要读的sla_terms JSON，
// 复用现成的sla_terms隐藏字段（跟IT外包那个文本框共用同一个name，避免表单里出现两个同名字段）
function prepareRentalSla() {
    if (curType !== 'rental') return true;
    const rentPrice = parseFloat(document.getElementById('f-rent_price').value) || 0;
    const rentCycle = document.getElementById('f-rent_cycle').value;
    const deposit   = parseFloat(document.getElementById('f-rent_deposit').value) || 0;
    const startStr   = document.querySelector('input[name="work_start"]').value;
    const endStr     = document.querySelector('input[name="work_end"]').value;
    const cycleLabelMap = {day:'天', week:'周', month:'月', year:'年'};
    const cycleLabel = cycleLabelMap[rentCycle] || '天';

    let totalQty = 0;
    document.querySelectorAll('#itemsBody input[name$="[quantity]"]').forEach(inp => {
        totalQty += parseFloat(inp.value) || 0;
    });
    totalQty = totalQty || 1;

    let days = 0;
    if (startStr && endStr) {
        days = Math.max(1, Math.round((new Date(endStr) - new Date(startStr)) / 86400000));
    }
    const cyclesMap = {day: days, week: Math.max(1, Math.round(days/7)), month: Math.max(1, Math.round(days/30)), year: Math.max(1, Math.round(days/365))};
    const cycles = cyclesMap[rentCycle] || days;
    const totalRent = rentPrice * cycles;

    const sla = {
        rent_price: rentPrice,
        rent_cycle: rentCycle,
        cycle_label: cycleLabel,
        total_qty: totalQty,
        total_rent: totalRent,
        start_date: startStr,
        end_date: endStr,
        deposit: deposit
    };
    document.getElementById('f-sla_terms').value = JSON.stringify(sla);
    // 合同总金额用真实租金合计，不能用设备明细里的市场估值合计（那个只是损坏赔偿参考）
    document.getElementById('totalInput').value = totalRent.toFixed(2);
    return true;
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const m = TYPE_META[curType];
    const n = rowNum++;
    const tr = document.createElement('tr');
    tr.innerHTML = `
    <td class="seq-no">${n}</td>
    <td>
        <input type="hidden" name="items[${n}][product_name]" id="cpname_${n}" value="">
        <input type="hidden" name="items[${n}][custom_name]"  id="ccname_${n}" value="">
        <div id="clib_${n}" class="prod-lib">${_libHtml(n, m.unit)}</div>
        <div id="ccust_${n}" class="prod-cust" style="display:none;">
            <div class="prod-cust-label">✏️ 自定义（保存自动入库）</div>
            <input type="text" id="ccustinp_${n}" placeholder="产品/项目名称 *" oninput="cSyncPname(${n},this.value)">
            <button type="button" class="prod-back-btn" onclick="cEnterLib(${n})">← 返回产品库</button>
        </div>
    </td>
    <td><input type="text"   name="items[${n}][specification]" id="cspec_${n}" placeholder="规格型号"></td>
    <td><input type="text"   name="items[${n}][unit]"     id="cunit_${n}" value="${m.unit}"></td>
    <td><input type="number" name="items[${n}][quantity]" value="1" step="0.01" oninput="calcRow(this)"></td>
    <td><input type="number" name="items[${n}][price]"    id="cprice_${n}" value="0" step="0.01" oninput="calcRow(this)"></td>
    <td><input type="number" name="items[${n}][amount]"   value="0" readonly></td>
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
    const catId=sel.value, kw=document.getElementById('ckw_'+n), psel=document.getElementById('cpsel_'+n);
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
    const libDiv=document.getElementById('clib_'+n);
    const firstSel=libDiv&&libDiv.querySelector('select');
    const actualCatId=sub&&sub.value?sub.value:(firstSel?firstSel.value:0);
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

function calcRow(inp) {
    const tr  = inp.closest('tr');
    const qty = parseFloat(tr.querySelector('input[name*="[quantity]"]').value)||0;
    const prc = parseFloat(tr.querySelector('input[name*="[price]"]').value)||0;
    tr.querySelector('input[name*="[amount]"]').value = (qty*prc).toFixed(2);
    calcTotal();
}
function calcTotal() {
    let t=0;
    document.querySelectorAll('input[name*="[amount]"]').forEach(i=>t+=parseFloat(i.value)||0);
    document.getElementById('totalAmt').textContent=t.toFixed(2);
    document.getElementById('totalInput').value=t.toFixed(2);
}
function delRow(btn) {
    if(document.getElementById('itemsBody').rows.length>1){btn.closest('tr').remove();calcTotal();}
    else sapToast('至少保留一行', 'info');
}

// 初始化：加载默认类型后添加 3 行
setType('sales').then(()=>{for(let i=0;i<3;i++) addRow();});
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
