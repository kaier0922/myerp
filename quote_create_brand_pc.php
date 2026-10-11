<?php
/**
 * quote_create_brand_pc.php — v5.1 (2026-03-20)
 * 三层选产品：大类下拉 → 关键字过滤 → 产品下拉
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$template_type = 'brand_pc';
$conn = getDBConnection();
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name");
$_tpl_stmt = $conn->prepare("SELECT * FROM quote_templates WHERE template_code = ? AND user_id = ?");
$_tpl_uid = intval($_SESSION['user_id']);
$_tpl_stmt->bind_param("si", $template_type, $_tpl_uid);
$_tpl_stmt->execute();
$tpl = $_tpl_stmt->get_result();
$template = $tpl ? $tpl->fetch_assoc() : null;

// 产品大类→次级类级联数据（与 quote_create.php 非组装机模板的加载逻辑一致）
$_cats_all = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE is_active=1 ORDER BY sort_order, id")->fetch_all(MYSQLI_ASSOC);
$_npc_parents = array_values(array_filter($_cats_all, fn($c) => $c['parent_id'] == 0));
// 品牌整机报价单要求的大类展示顺序：整机设备优先，其余依次；仅本页排序，不改全局 sort_order
$_npc_order_ids = [2, 1, 3, 4, 6, 7, 5, 83]; // 整机设备,硬件配件,网络设备,外设周边,软件授权,技术服务,监控安防,强弱电工程
usort($_npc_parents, function($a, $b) use ($_npc_order_ids) {
    $ai = array_search((int)$a['id'], $_npc_order_ids); $ai = $ai === false ? 999 : $ai;
    $bi = array_search((int)$b['id'], $_npc_order_ids); $bi = $bi === false ? 999 : $bi;
    return $ai <=> $bi;
});
$_npc_by_parent = [];
foreach ($_cats_all as $_c) { if ($_c['parent_id'] > 0) $_npc_by_parent[$_c['parent_id']][] = $_c; }
$npcParentsJson  = json_encode($_npc_parents, JSON_UNESCAPED_UNICODE);
$npcByParentJson = json_encode($_npc_by_parent, JSON_UNESCAPED_UNICODE);

$conn->close();
?>
<?php $page_title = '品牌整机报价单 v5.1'; include __DIR__ . '/head.php'; ?>
<style>
.navbar-actions{display:flex;gap:12px;}
.section-title{font-size:18px;font-weight:600;color:#1a202c;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid #e2e8f0;}
.form-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;margin-bottom:20px;}
.form-label.required::after{content:'*';color:#ef4444;margin-left:4px;}
.form-input,.form-select,.form-textarea{padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;transition:all .2s;}
.form-input:focus,.form-select:focus,.form-textarea:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.form-textarea{resize:vertical;min-height:80px;}
.items-wrap{border:1px solid #e2e8f0;border-radius:8px;overflow:auto;margin-bottom:12px;}
.items-table{width:100%;border-collapse:collapse;min-width:700px;}
.items-table th{padding:10px 8px;background:#f7fafc;font-size:12px;font-weight:600;color:#4a5568;border-bottom:2px solid #e2e8f0;white-space:nowrap;text-align:center;}
.items-table td{padding:8px;border-bottom:1px solid #f0f4f8;}
.items-table input,.items-table select{width:100%;padding:5px 7px;border:1px solid #e2e8f0;border-radius:4px;font-size:13px;}
.items-table input:focus,.items-table select:focus{outline:none;border-color:#1f6dad;}
.btn-add-row{padding:10px 20px;background:#1f6dad;color:white;border:none;border-radius:6px;cursor:pointer;font-size:14px;margin-bottom:16px;}
.btn-add-row:hover{background:#135286;}
.btn-delete-row{padding:4px 8px;background:#ef4444;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;}
.summary-box{background:#f7fafc;border-radius:8px;padding:20px;margin-top:20px;}
.summary-row{display:flex;justify-content:space-between;margin-bottom:12px;font-size:14px;}
.summary-row.total{font-size:18px;font-weight:700;padding-top:12px;border-top:2px solid #e2e8f0;}
/* 三层控件 */
.sel-cat{background:#eff6ff!important;border-color:#93c5fd!important;font-weight:600!important;color:#135286!important;margin-bottom:4px!important;}
.sel-filter{background:#fafafa!important;margin-bottom:4px!important;}
.cost-col{display:none;}
body.cost-mode .cost-col{display:table-cell;}
.cost-row{display:none;}
body.cost-mode .cost-row{display:flex;}
.btn-cost-toggle{padding:8px 18px;border:2px solid #94a3b8;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;background:#f1f5f9;color:#64748b;transition:all .2s;white-space:nowrap;}
.btn-cost-toggle.on{background:#16a34a;border-color:#16a34a;color:#fff;}
.btn-cost-toggle:hover{opacity:.85;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="page-header">
  <h1 class="page-title">📝 创建品牌整机报价单</h1>
  <div style="display:flex;gap:10px;">
    <a href="quotes.php" class="btn btn-secondary">← 返回列表</a>
    <button class="btn btn-primary" onclick="saveQuote('草稿')" style="min-width:120px;">💾 保存草稿</button>
  </div>
</div>
<div style="max-width:1200px;margin:0 auto;">
<form id="quoteForm">
  <input type="hidden" name="template_type" value="brand_pc">

  <!-- 页面标题居中 -->
  <div style="text-align:center;margin-bottom:24px;">
    <h1 style="font-size:24px;font-weight:700;color:#1a202c;">品牌整机报价单</h1>
    <p style="font-size:13px;color:#718096;margin-top:4px;">填写完成后点击右上角「保存草稿」，审核后可生成送货单</p>
  </div>

  <div class="form-card">
    <h2 class="section-title">基本信息</h2>
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px;">
        <div class="form-group" style="margin:0;">
          <label class="form-label required">客户</label>
          <select id="customerSelect" class="form-select" onchange="fillCustomer(this)">
            <option value="">-- 选择已有客户 / 或下方手动输入 --</option>
            <?php if($customers) while($c=$customers->fetch_assoc()): ?>
            <option value="<?=$c['id']?>"
              data-contact="<?=htmlspecialchars($c['contact_name']??'')?>"
              data-phone="<?=htmlspecialchars($c['phone']??'')?>">
              <?=htmlspecialchars($c['company_name'])?><?=$c['contact_name']?' ('.htmlspecialchars($c['contact_name']).')':''?>
            </option>
            <?php endwhile; ?>
          </select>
          <input type="hidden" name="customer_id" id="customerId">
          <input type="text" name="customer_name_manual" id="customerNameManual"
                 class="form-input" style="margin-top:6px;"
                 placeholder="或手动输入新客户名称">
        </div>
        <div class="form-group" style="margin:0;"><label class="form-label required">报价日期</label><input type="date" name="quote_date" class="form-input" value="<?=date('Y-m-d')?>" required></div>
        <div class="form-group" style="margin:0;"><label class="form-label">有效期(天)</label><input type="number" name="valid_days" class="form-input" value="3"></div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-top:14px;">
        <div class="form-group" style="margin:0;"><label class="form-label">联系人</label><input type="text" name="contact_person" class="form-input" placeholder="联系人姓名"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">联系电话</label><input type="text" name="contact_phone" class="form-input" placeholder="联系电话"></div>
        <div class="form-group" style="margin:0;"><label class="form-label">报价单号</label><input type="text" name="quote_no" class="form-input" placeholder="留空自动生成"></div>
      </div>
  </div>

  <div class="form-card">
    <h2 class="section-title" style="display:flex;justify-content:space-between;align-items:center;">产品清单<button type="button" id="costToggleBtn" class="btn-cost-toggle" onclick="toggleCostMode()">💰 利润核算 OFF</button></h2>
    <div class="alert alert-info">
      💡 <strong>选产品四步走：</strong>
      ① 选<b>产品大类</b> → ② 选<b>次级类</b> → ③ 可选输入<b>关键字</b>过滤 → ④ 选<b>具体型号</b>
    </div>
    <button type="button" class="btn-add-row" onclick="addRow()">+ 添加行</button>
    <table class="items-table">
      <thead>
        <tr>
          <th style="width:40px;white-space:nowrap">序号</th>
          <th style="width:240px">产品类型</th>
          <th style="width:100px">品牌/型号</th>
          <th>配置参数</th>
          <th style="width:58px">单位</th>
          <th style="width:68px">数量</th>
          <th style="width:105px">单价</th>
          <th class="cost-col" style="width:100px">成本价</th>
          <th style="width:88px">小计</th>
          <th style="width:66px">操作</th>
        </tr>
      </thead>
      <tbody id="itemsBody"></tbody>
    </table>
    <div class="summary-box">
      <div class="summary-row"><span>小计:</span><span id="subtotal">¥0.00</span></div>
      <div class="summary-row">
        <span>折扣金额:</span>
        <input type="number" name="discount" class="form-input" style="width:140px;text-align:right" value="0" step="any" onchange="calcTotal()">
      </div>
      <div class="summary-row total"><span>合计:</span><span id="totalAmount">¥0.00</span></div>
      <div class="summary-row cost-row" style="color:#888;"><span>成本合计:</span><span id="totalCost">¥0.00</span></div>
      <div class="summary-row cost-row" style="color:#16a34a;font-weight:600;"><span>毛利润:</span><span id="totalProfit">¥0.00</span></div>
    </div>
  </div>

  <div class="form-card">
    <h2 class="section-title">备注</h2>
    <textarea id="remark" name="remark" class="form-textarea" rows="3" placeholder="补充说明、特殊要求、交货条件等..." style="width:100%;"></textarea>
  </div>

  <div class="form-card">
    <h2 class="section-title">条款说明</h2>
    <textarea name="terms" class="form-textarea" rows="6"
        style="width:100%;min-height:130px;resize:vertical;display:block;font-size:14px;line-height:1.6;"
    ><?=htmlspecialchars($template['default_terms']??'')?></textarea>
  </div>
</form>
</div><!-- end max-width -->
</main>

<script>
// ─── 数据 ────────────────────────────────────────────────
let rowIdx = 0;

function esc(s){ return (s||'').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

/* ══════════════════════════════════════════════════
   产品库级联选择（大类→次级类→关键字→型号），与 quote_create.php 一致
   ══════════════════════════════════════════════════ */
const NPC_PARENT_CATS    = <?php echo $npcParentsJson; ?>;
const NPC_CATS_BY_PARENT = <?php echo $npcByParentJson; ?>;
const _npcParentOpts = NPC_PARENT_CATS.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
const _npcProdCache = {};

function npcParentChange(idx, parentId) {
    const sub = document.getElementById('npc_sub_'+idx);
    const kw  = document.getElementById('npc_kw_'+idx);
    const ps  = document.getElementById('npc_ps_'+idx);
    sub.innerHTML = '<option value="">② 选次级类</option>'; sub.disabled = true;
    kw.value = ''; kw.disabled = true;
    ps.innerHTML = '<option value="">④ 选具体型号</option>'; ps.disabled = true;
    document.getElementById('npc_catid_'+idx).value = '';
    document.getElementById('npc_catlbl_'+idx).value = '';
    npcClearRow(idx);
    if (!parentId) return;
    const children = NPC_CATS_BY_PARENT[parentId] || [];
    if (!children.length) {
        // 该大类本身就是叶子，直接加载产品
        const pc = NPC_PARENT_CATS.find(c => c.id == parentId);
        document.getElementById('npc_catid_'+idx).value  = parentId;
        document.getElementById('npc_catlbl_'+idx).value = pc ? pc.name : '';
        npcLoadProds(idx, parentId);
    } else {
        children.forEach(c => sub.add(new Option(c.name, c.id)));
        sub.disabled = false;
    }
}

function npcSubChange(idx, sel) {
    const catId = sel.value;
    const catName = sel.options[sel.selectedIndex]?.text || '';
    document.getElementById('npc_catid_'+idx).value  = catId;
    document.getElementById('npc_catlbl_'+idx).value = catName;
    const kw = document.getElementById('npc_kw_'+idx);
    const ps = document.getElementById('npc_ps_'+idx);
    kw.value = ''; kw.disabled = true;
    ps.innerHTML = '<option value="">加载中...</option>'; ps.disabled = true;
    npcClearRow(idx);
    if (!catId) return;
    npcLoadProds(idx, catId);
}

async function npcLoadProds(idx, catId) {
    if (!_npcProdCache[catId]) {
        const r = await fetch('api_products_by_category.php?cat_id=' + catId);
        _npcProdCache[catId] = await r.json();
    }
    npcRenderSel(idx, _npcProdCache[catId], '');
}

function npcRenderSel(idx, prods, kw) {
    const ps   = document.getElementById('npc_ps_'+idx);
    const kwEl = document.getElementById('npc_kw_'+idx);
    const kl   = (kw||'').toLowerCase().trim();
    const filtered = kl ? prods.filter(p =>
        (p.name||'').toLowerCase().includes(kl) ||
        (p.spec||'').toLowerCase().includes(kl) ||
        (p.supplier_name||'').toLowerCase().includes(kl)
    ) : prods;
    let html = `<option value="">④ 选具体型号 (${filtered.length})</option>`;
    html += '<option value="" data-custom="1" style="background:#fef3c7;font-weight:bold;">✏️ 自定义输入（不在产品库）</option>';
    filtered.forEach(p => {
        const stock = p.stock_quantity==0 ? ' [缺货]' : (p.stock_quantity<5 ? ` [库存:${p.stock_quantity}]` : '');
        html += `<option value="${p.id}"
            data-name="${esc(p.name)}"
            data-spec="${esc(p.spec||'')}"
            data-supplier="${esc(p.supplier_name||'')}"
            data-price="${p.default_price||0}"
            data-cost="${p.cost_price||0}">
            ${esc(p.name)}${p.spec?' - '+esc(p.spec):''}${stock} — ¥${p.default_price||0}
        </option>`;
    });
    ps.innerHTML = html; ps.disabled = false; kwEl.disabled = false;
}

function npcFilter(idx, kw) {
    const catId = document.getElementById('npc_catid_'+idx).value;
    if (catId && _npcProdCache[catId]) npcRenderSel(idx, _npcProdCache[catId], kw);
}

function npcProdSel(idx, sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('npc_pid_'+idx).value = '';
    document.getElementById('npc_cname_'+idx).value = '';

    if (opt && opt.dataset.custom === '1') {
        // 自定义输入：清空让用户直接在“品牌/型号”“配置参数”里手打
        npcClearRow(idx);
        const sp = document.getElementById('sp_'+idx);
        if (sp) { sp.focus(); sp.oninput(); }
        return;
    }

    const val = sel.value;
    if (!val) { npcClearRow(idx); return; }

    document.getElementById('npc_pid_'+idx).value = val;
    // sp_ → name="product_name" → 存品牌/型号（supplier_name）
    document.getElementById('sp_'+idx).value = opt.dataset.supplier || opt.dataset.name || '';
    document.getElementById('pr_'+idx).value = opt.dataset.price    || 0;
    const ctEl = document.getElementById('ct_'+idx);
    if (ctEl) ctEl.value = opt.dataset.cost || 0;
    // rm_ → name="spec" → 存配置参数（产品名 + 规格合并）
    const rmEl = document.getElementById('rm_'+idx);
    if (rmEl) {
        const nameStr = opt.dataset.name || '';
        const specStr = opt.dataset.spec || '';
        rmEl.value = specStr ? nameStr + ' ' + specStr : nameStr;
    }
    document.getElementById('sp_'+idx).oninput();
    calcRow(idx);
}

function npcClearRow(idx) {
    document.getElementById('npc_pid_'+idx).value = '';
    ['sp_','rm_','pr_','ct_'].forEach(p => {
        const el = document.getElementById(p+idx);
        if (el) el.value = '';
    });
    const sub = document.getElementById('sub_'+idx);
    if (sub) sub.textContent = '0.00';
    calcTotal();
}

// ─── 添加行 ──────────────────────────────────────────────
function addRow() {
    rowIdx++;
    const id  = rowIdx;
    const tr  = document.createElement('tr');
    tr.id = 'row_' + id;
    tr.innerHTML = `
      <td style="text-align:center">${id}</td>
      <td>
        <select id="npc_cs_${id}" class="sel-cat" onchange="npcParentChange(${id}, this.value)">
          <option value="">① 选产品大类</option>${_npcParentOpts}
        </select>
        <select id="npc_sub_${id}" class="sel-filter" onchange="npcSubChange(${id}, this)" disabled>
          <option value="">② 选次级类</option>
        </select>
        <input id="npc_kw_${id}" type="text" class="sel-filter" placeholder="③ 关键字过滤（可选）"
               oninput="npcFilter(${id}, this.value)" disabled>
        <select id="npc_ps_${id}" onchange="npcProdSel(${id}, this)" disabled>
          <option value="">④ 选具体型号</option>
        </select>
        <input type="hidden" id="npc_pid_${id}"    name="items[${id}][product_id]"  value="">
        <input type="hidden" id="npc_catid_${id}"  name="items[${id}][category_id]" value="">
        <input type="hidden" id="npc_catlbl_${id}" name="items[${id}][category]"    value="">
        <input type="hidden" id="npc_cname_${id}"  name="items[${id}][custom_name]" value="">
      </td>
      <td><input type="text" id="sp_${id}" name="items[${id}][product_name]" placeholder="品牌/型号" style="width:100%"
             oninput="document.getElementById('npc_cname_${id}').value=this.value;"></td>
      <td><input type="text" id="rm_${id}" name="items[${id}][spec]" placeholder="产品名称及配置参数..." style="width:100%"></td>
      <td><input type="text" name="items[${id}][unit]" value="台"></td>
      <td><input type="number" name="items[${id}][quantity]" value="1" min="1" oninput="calcRow(${id})"></td>
      <td><input type="number" id="pr_${id}" name="items[${id}][price]" step="any" oninput="calcRow(${id})"></td>
      <td class="cost-col"><input type="number" id="ct_${id}" name="items[${id}][cost]" step="any" min="0" oninput="calcRow(${id})" style="background:#fff8e1;width:100%;"></td>
      <td style="text-align:right"><span id="sub_${id}">0.00</span></td>
      <td><button type="button" class="btn-delete-row" onclick="delRow(${id})">删除</button></td>
    `;
    document.getElementById('itemsBody').appendChild(tr);
    reSeq();
}

function delRow(id) {
    if (!confirm('确定删除？')) return;
    document.getElementById('row_'+id)?.remove();
    reSeq(); calcTotal();
}

function reSeq() {
    document.querySelectorAll('#itemsBody tr').forEach((tr,i) => {
        if (tr.cells[0]) tr.cells[0].textContent = i + 1;
    });
}

function calcRow(id) {
    const q = parseFloat(document.querySelector(`input[name="items[${id}][quantity]"]`)?.value) || 0;
    const p = parseFloat(document.getElementById('pr_'+id)?.value) || 0;
    const sub = document.getElementById('sub_'+id);
    if (sub) sub.textContent = (q*p).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let sum = 0, totalCost = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const id = tr.id.replace('row_','');
        sum += parseFloat(document.getElementById('sub_'+id)?.textContent)||0;
        const q = parseFloat(document.querySelector(`input[name="items[${id}][quantity]"]`)?.value)||0;
        const c = parseFloat(document.getElementById('ct_'+id)?.value)||0;
        totalCost += q * c;
    });
    const disc = parseFloat(document.querySelector('input[name="discount"]')?.value) || 0;
    document.getElementById('subtotal').textContent   = '¥' + sum.toFixed(2);
    document.getElementById('totalAmount').textContent = '¥' + (sum-disc).toFixed(2);
    if (document.getElementById('totalCost'))   document.getElementById('totalCost').textContent   = '¥' + totalCost.toFixed(2);
    if (document.getElementById('totalProfit')) document.getElementById('totalProfit').textContent = '¥' + (sum-disc-totalCost).toFixed(2);
}

function toggleCostMode() {
    const on = document.body.classList.toggle('cost-mode');
    const btn = document.getElementById('costToggleBtn');
    btn.textContent = on ? '💰 利润核算 ON' : '💰 利润核算 OFF';
    btn.classList.toggle('on', on);
    localStorage.setItem('costMode', on ? '1' : '0');
}


function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customerId').value = opt.value || '';
    if (opt.value) {
        document.getElementById('customerNameManual').value = '';
        const cp = document.querySelector('input[name="contact_person"]');
        const ph = document.querySelector('input[name="contact_phone"]');
        if (cp) cp.value = opt.dataset.contact || '';
        if (ph) ph.value = opt.dataset.phone   || '';
    }
}
function saveQuote(status) {
    const form = document.getElementById('quoteForm');
    if (!form.checkValidity()) { alert('请填写所有必填项！'); return; }
    const fd = new FormData(form);
    fd.append('status', status);
    fd.append('customer_id', document.getElementById('customerId')?.value || '');
    const sum  = parseFloat(document.getElementById('subtotal').textContent.replace('¥','')) || 0;
    const disc = parseFloat(document.querySelector('input[name="discount"]')?.value) || 0;
    fd.append('final_amount', (sum-disc).toFixed(2));
    fd.append('remark', document.getElementById('remark')?.value || '');
    const qd = document.querySelector('input[name="quote_date"]');
    if (qd?.value) fd.set('quote_date', qd.value.replace(/-/g,''));
    const btns = document.querySelectorAll('.btn-primary');
    btns.forEach(b => { b.disabled=true; b.textContent='保存中...'; });
    fetch('quote_save.php', {method:'POST', body:fd})
    .then(r=>r.json())
    .then(d => {
        if (d.success) { alert(d.message); location.href='quotes.php'; }
        else { alert('保存失败：'+d.message); btns.forEach(b=>{b.disabled=false; b.textContent=b.textContent.replace('保存中...','');}); }
    })
    .catch(e => { alert('出错：'+e.message); btns.forEach(b=>{b.disabled=false; b.textContent=b.textContent.replace('保存中...','');}); });
}

window.onload = function() {
    addRow();
    if (localStorage.getItem('costMode') === '1') {
        document.body.classList.add('cost-mode');
        const btn = document.getElementById('costToggleBtn');
        if (btn) { btn.textContent = '💰 利润核算 ON'; btn.classList.add('on'); }
    }
};
</script>
</body>
</html>
