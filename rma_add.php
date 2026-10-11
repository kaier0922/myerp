<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();
require_once 'config.php';

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];
$conn = getDBConnection();

// 编辑模式：读取现有记录
$edit_id  = intval($_GET['id'] ?? 0);
$rma      = null;
if ($edit_id > 0) {
    $res = $conn->query("SELECT * FROM rma_orders WHERE id=$edit_id");
    if ($res) $rma = $res->fetch_assoc();
}

$suppliers = $conn->query("SELECT id, company_name FROM suppliers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

$parent_cats = $conn->query("SELECT id, name FROM product_categories WHERE parent_id=0 AND is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$child_cats  = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id>0 AND is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$rma_products = $conn->query("SELECT p.id, p.name, p.spec, p.category_id, pc.parent_id as parent_cat_id FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.is_active=1 AND p.product_type IN ('hardware','device') AND (p.user_id=0 OR p.user_id=" . intval($_SESSION['user_id']) . ") ORDER BY p.name")->fetch_all(MYSQLI_ASSOC);

$conn->close();

function e($v){return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}
$is_edit   = $rma !== null;
$page_title = $is_edit ? '编辑返修单 ' . e($rma['rma_no']) : '新建返修单';
?>
<?php $page_title = $page_title . ' - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

.content-inner { max-width: 860px; margin: 0 auto; }

.card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 24px; overflow: hidden; }
.card-

.form-grid  { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.form-group:last-child { margin-bottom: 0; }
.form-label .req { color: #ef4444; margin-left: 3px; }
.form-control { width: 100%; padding: 9px 14px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; transition: all 0.2s; }
.form-control:focus { outline: none; border-color: #1f6dad; box-shadow: 0 0 0 3px rgba(31,109,173,0.1); }
textarea.form-control { resize: vertical; min-height: 80px; }
</style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
<div class="content-inner">

    <!-- 顶部操作栏 -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <h1 style="font-size:28px;font-weight:700;color:#1a202c;">↩️ <?=$page_title?></h1>
            <p style="color:#718096;font-size:14px;margin-top:4px;"><?=$is_edit ? '修改返修单信息' : '填写返修信息，提交后生成返修单号'?></p>
        </div>
        <div style="display:flex;gap:12px;">
            <a href="rma.php" class="btn btn-secondary">← 返回列表</a>
            <button class="btn btn-primary" onclick="saveRma()">💾 保存返修单</button>
        </div>
    </div>

    <form id="rmaForm">
        <input type="hidden" name="id" value="<?=$is_edit ? $rma['id'] : ''?>">

        <!-- 供应商 & 客户 -->
        <div class="card">
            <div class="card-header">🏭 供应商 / 客户</div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">供应商</label>
                        <select name="supplier_id" id="supplierId" class="form-control">
                            <option value="">-- 选择供应商 --</option>
                            <?php foreach($suppliers as $s): ?>
                            <option value="<?=$s['id']?>" <?=($rma['supplier_id']??'')==$s['id']?'selected':''?>><?=e($s['company_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">关联客户（可选）</label>
                        <select name="customer_id" id="customerId" class="form-control">
                            <option value="">-- 不关联 --</option>
                            <?php foreach($customers as $c): ?>
                            <option value="<?=$c['id']?>" <?=($rma['customer_id']??'')==$c['id']?'selected':''?>><?=e($c['company_name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 产品信息 -->
        <div class="card">
            <div class="card-header">📦 产品信息</div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">产品大类</label>
                        <select id="selParentCat" class="form-control" onchange="onParentCatChange()">
                            <option value="">— 请选大类 —</option>
                            <?php foreach($parent_cats as $c): ?>
                            <option value="<?=$c['id']?>"><?=e($c['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">产品子类</label>
                        <select id="selChildCat" class="form-control" onchange="onChildCatChange()" disabled>
                            <option value="">— 请先选大类 —</option>
                            <?php foreach($child_cats as $c): ?>
                            <option value="<?=$c['id']?>" data-parent="<?=$c['parent_id']?>"><?=e($c['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">关键字过滤</label>
                        <input type="text" id="filterKw" class="form-control" placeholder="输入关键字筛选..." oninput="filterProducts()" disabled>
                    </div>
                    <div class="form-group">
                        <label class="form-label">选择产品</label>
                        <select id="selProd" class="form-control" onchange="onProdChange()">
                            <option value="">— 请先选子类 —</option>
                        </select>
                    </div>
                </div>
                <input type="hidden" name="product_id" id="productId" value="<?=e($rma['product_id']??'')?>">
                <div id="all_rma_products" style="display:none"><?php echo json_encode($rma_products, JSON_UNESCAPED_UNICODE); ?></div>
                <div class="form-group">
                    <label class="form-label">序列号</label>
                    <input type="text" name="serial_number" id="serialNumber" class="form-control" placeholder="序列号（可选）" value="<?=e($rma['serial_number']??'')?>">
                </div>
            </div>
        </div>

        <!-- 故障描述 -->
        <div class="card">
            <div class="card-header">🔍 故障信息</div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">故障描述 <span class="req">*</span></label>
                    <textarea name="fault_desc" id="faultDesc" class="form-control" rows="3" placeholder="描述故障现象..." required><?=e($rma['fault_desc']??'')?></textarea>
                </div>
            </div>
        </div>

        <!-- 处理进度 -->
        <div class="card">
            <div class="card-header">📋 处理进度</div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">发出日期</label>
                        <input type="date" name="send_date" id="sendDate" class="form-control" value="<?=e($rma['send_date']??'')?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">预计返回日期</label>
                        <input type="date" name="expected_return" id="expectedReturn" class="form-control" value="<?=e($rma['expected_return']??'')?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">状态</label>
                        <select name="status" id="rmaStatus" class="form-control">
                            <option value="pending"    <?=($rma['status']??'pending')==='pending'   ?'selected':''?>>待发出</option>
                            <option value="sent"       <?=($rma['status']??'')==='sent'             ?'selected':''?>>已发出</option>
                            <option value="processing" <?=($rma['status']??'')==='processing'       ?'selected':''?>>处理中</option>
                            <option value="returned"   <?=($rma['status']??'')==='returned'         ?'selected':''?>>已返回</option>
                            <option value="replaced"   <?=($rma['status']??'')==='replaced'         ?'selected':''?>>已换货</option>
                            <option value="refunded"   <?=($rma['status']??'')==='refunded'         ?'selected':''?>>已退款</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">处理结果</label>
                        <select name="result" id="rmaResult" class="form-control">
                            <option value=""            <?=empty($rma['result'])                    ?'selected':''?>>-- 待定 --</option>
                            <option value="repaired"    <?=($rma['result']??'')==='repaired'        ?'selected':''?>>修好返回</option>
                            <option value="replaced"    <?=($rma['result']??'')==='replaced'        ?'selected':''?>>换全机</option>
                            <option value="refunded"    <?=($rma['result']??'')==='refunded'        ?'selected':''?>>退款</option>
                            <option value="unrepairable"<?=($rma['result']??'')==='unrepairable'    ?'selected':''?>>无法维修</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 费用 -->
        <div class="card">
            <div class="card-header">💰 费用</div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">运费</label>
                        <input type="number" name="freight_fee" id="freightFee" class="form-control" value="<?=e($rma['freight_fee']??'0')?>" step="any" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">维修费</label>
                        <input type="number" name="repair_fee" id="repairFee" class="form-control" value="<?=e($rma['repair_fee']??'0')?>" step="any" min="0">
                    </div>
                </div>
            </div>
        </div>

        <!-- 备注 -->
        <div class="card">
            <div class="card-header">📝 备注</div>
            <div class="card-body">
                <div class="form-group">
                    <textarea name="notes" id="rmaNotes" class="form-control" rows="3" placeholder="备注..."><?=e($rma['notes']??'')?></textarea>
                </div>
            </div>
        </div>

        <!-- 底部按钮 -->
        <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:32px;">
            <a href="rma.php" class="btn btn-secondary">取消</a>
            <button type="button" class="btn btn-primary" onclick="saveRma()">💾 保存返修单</button>
        </div>

    </form>
</div>
</main>

<script>
const _allRmaProducts = JSON.parse(document.getElementById('all_rma_products').textContent || '[]');
const editProductId   = '<?=e($rma['product_id']??'')?>';

function onParentCatChange() {
    const parentId = parseInt(document.getElementById('selParentCat').value);
    const childSel = document.getElementById('selChildCat');
    Array.from(childSel.options).forEach(o => {
        if (!o.value) { o.style.display = ''; return; }
        o.style.display = parseInt(o.dataset.parent) === parentId ? '' : 'none';
    });
    childSel.value = '';
    childSel.disabled = !parentId;
    document.getElementById('filterKw').disabled = true;
    document.getElementById('filterKw').value = '';
    document.getElementById('selProd').innerHTML = '<option value="">— 请先选子类 —</option>';
    document.getElementById('productId').value = '';
}

function onChildCatChange() {
    const childId = parseInt(document.getElementById('selChildCat').value);
    document.getElementById('filterKw').disabled = !childId;
    document.getElementById('filterKw').value = '';
    filterProducts();
}

function filterProducts() {
    const childId = parseInt(document.getElementById('selChildCat').value);
    const kw = document.getElementById('filterKw').value.toLowerCase();
    const sel = document.getElementById('selProd');
    const list = _allRmaProducts.filter(p => {
        if (parseInt(p.category_id) !== childId) return false;
        if (!kw) return true;
        return (p.name||'').toLowerCase().includes(kw) || (p.spec||'').toLowerCase().includes(kw);
    });
    sel.innerHTML = list.length
        ? `<option value="">— 选择产品 (${list.length}) —</option>` + list.map(p =>
            `<option value="${p.id}">${esc(p.name)}${p.spec?' - '+esc(p.spec):''}</option>`).join('')
        : '<option value="">— 无匹配产品 —</option>';
    if (editProductId) sel.value = editProductId;
}

function onProdChange() {
    document.getElementById('productId').value = document.getElementById('selProd').value || '';
}

function esc(s){ return (s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

// 编辑模式自动定位
if (editProductId) {
    const curP = _allRmaProducts.find(p => String(p.id) === String(editProductId));
    if (curP) {
        document.getElementById('selParentCat').value = curP.parent_cat_id || '';
        onParentCatChange();
        document.getElementById('selChildCat').value = curP.category_id || '';
        onChildCatChange();
        document.getElementById('selProd').value = editProductId;
        document.getElementById('productId').value = editProductId;
    }
}

async function saveRma() {
    const form = document.getElementById('rmaForm');
    const data = Object.fromEntries(new FormData(form));
    if (!data.fault_desc) { sapToast('请填写故障描述', 'warning'); return; }
    const btn = document.querySelector('.btn-primary[onclick="saveRma()"]');
    btn.disabled = true;
    btn.textContent = '保存中...';
    try {
        const r   = await fetch('rma_save.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data)});
        const res = await r.json();
        if (res.success) {
            window.location.href = 'rma.php';
        } else {
            sapToast('保存失败：' + res.message, 'error');
            btn.disabled = false;
            btn.innerHTML = '💾 保存返修单';
        }
    } catch(err) {
        sapToast('保存出错：' + err.message, 'error');
        btn.disabled = false;
        btn.innerHTML = '💾 保存返修单';
    }
}

// 页面加载后初始化产品选择器
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
