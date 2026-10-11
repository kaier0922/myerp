<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$id       = intval($_GET['id'] ?? 0);
$error    = '';

// 读取合同
$stmt = $conn->prepare("SELECT * FROM rental_contracts WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if (!$r) { header('Location: rentals.php'); exit; }

// 读取设备明细
$stmt2 = $conn->prepare("SELECT * FROM rental_items WHERE contract_id=? ORDER BY id");
$stmt2->bind_param("i", $id);
$stmt2->execute();
$items = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// 客户列表
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name, phone FROM customers WHERE " . tenantWhere() . " ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id    = intval($_POST['customer_id']   ?? 0);
    $customer_name  = trim($_POST['customer_name']   ?? '');
    $contact_name   = trim($_POST['contact_name']    ?? '');
    $contact_phone  = trim($_POST['contact_phone']   ?? '');
    $start_date     = $_POST['start_date']           ?? '';
    $end_date       = $_POST['end_date']             ?? '';
    $rent_cycle     = $_POST['rent_cycle']           ?? 'month';
    $rent_price     = floatval($_POST['rent_price']  ?? 0);
    $deposit        = floatval($_POST['deposit']     ?? 0);
    $deposit_status = $_POST['deposit_status']       ?? $r['deposit_status'];
    $notes          = trim($_POST['notes']           ?? '');

    $item_ids     = $_POST['item_id']      ?? [];
    $item_names   = $_POST['item_name']    ?? [];
    $item_serials = $_POST['item_serial']  ?? [];
    $item_specs   = $_POST['item_spec']    ?? [];
    $item_costs   = $_POST['item_cost']    ?? [];
    $item_markets = $_POST['item_market']  ?? [];
    $item_qtys    = $_POST['item_qty']     ?? [];
    $item_rents   = $_POST['item_rent']    ?? [];

    if (!$customer_name) { $error = '请填写客户名称'; }
    elseif (!$start_date || !$end_date) { $error = '请填写起止日期'; }
    elseif ($end_date <= $start_date)   { $error = '结束日期必须晚于开始日期'; }
    else {
        // 重新计算总租金
        $total_rent = 0;
        if ($r['type'] === 'rental' && $rent_price > 0) {
            $days = max(1, round((strtotime($end_date) - strtotime($start_date)) / 86400));
            if ($rent_cycle === 'month') {
                $months = max(1, round($days / 30));
                $total_rent = $rent_price * $months;
            } else {
                $total_rent = $rent_price * $days;
            }
        }

        $conn->begin_transaction();
        try {
            // 更新合同主表
            $upd = $conn->prepare("UPDATE rental_contracts SET
                customer_id=?, customer_name=?, contact_name=?, contact_phone=?,
                start_date=?, end_date=?, rent_cycle=?, rent_price=?,
                deposit=?, deposit_status=?, total_rent=?, notes=?
                WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
            $upd->bind_param("issssssddssi",
                $customer_id, $customer_name, $contact_name, $contact_phone,
                $start_date, $end_date, $rent_cycle, $rent_price,
                $deposit, $deposit_status, $total_rent, $notes, $id);
            // 修正参数类型（13个参数需要13个类型字符）
            $upd = $conn->prepare("UPDATE rental_contracts SET
                customer_id=?, customer_name=?, contact_name=?, contact_phone=?,
                start_date=?, end_date=?, rent_cycle=?, rent_price=?,
                deposit=?, deposit_status=?, total_rent=?, notes=?
                WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
            $upd->bind_param("issssssddssdsi",
                $customer_id, $customer_name, $contact_name, $contact_phone,
                $start_date, $end_date, $rent_cycle, $rent_price,
                $deposit, $deposit_status, $total_rent, $notes, $id);
            $upd->execute();

            // 删除旧设备明细，重新插入
            $conn->query("DELETE FROM rental_items WHERE contract_id=$id");

            $istmt = $conn->prepare("INSERT INTO rental_items
                (contract_id, product_name, serial_no, spec, cost_price, market_price,
                 quantity, rent_price)
                VALUES (?,?,?,?,?,?,?,?)"); // 修复：加入 label_no
            foreach ($item_names as $i => $name) {
                $name = trim($name);
                if (!$name) continue;
                $serial = trim($item_serials[$i] ?? '');
                $spec   = trim($item_specs[$i]   ?? '');
                $cost   = floatval($item_costs[$i]   ?? 0);
                $market = floatval($item_markets[$i] ?? 0);
                $qty    = intval($item_qtys[$i]   ?? 1);
                $irent  = floatval($item_rents[$i]  ?? 0);
                $label  = trim($item_labels[$i] ?? '');  // 修复
                $istmt->bind_param("isssddid",
                    $id, $name, $serial, $spec, $cost, $market, $qty, $irent);
                $istmt->execute();
            }

            $conn->commit();
            writeLog($conn, 'rentals', 'update', '修改租借合同');
            header('Location: rental_view.php?id=' . $id . '&updated=1');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $error = '保存失败：' . $e->getMessage();
        }
    }
}

function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
$conn->close();
?>
<?php $page_title = '编辑合同 - ' . e($r['contract_no']); include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.back-btn{padding:8px 16px;background:white;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;}
.form-card-head{padding:16px 24px;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#1a202c;display:flex;align-items:center;gap:8px;}
.form-card-
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.form-grid.cols3{grid-template-columns:1fr 1fr 1fr;}
.form-label .req{color:#e53e3e;margin-left:2px;}
.form-label .hint{font-weight:400;color:#a0aec0;font-size:12px;margin-left:6px;}
.form-control{padding:9px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
select.form-control{background:white;}
.type-badge{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:8px;font-size:14px;font-weight:600;}
.type-badge.rental{background:#eef2ff;color:#4338ca;border:2px solid #1f6dad;}
.type-badge.loan{background:#ecfdf5;color:#065f46;border:2px solid #135286;}

/* 设备表格 */
.items-
.items-table
.items-table
.items-table td input{width:100%;padding:7px 9px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;font-family:inherit;}
.items-table td input:focus{outline:none;border-color:#1f6dad;}
.btn-del-row{padding:5px 10px;background:#fff5f5;color:#c53030;border:1px solid #feb2b2;border-radius:6px;font-size:12px;cursor:pointer;white-space:nowrap;}
.btn-add-row{padding:8px 16px;background:#f7fafc;border:2px dashed #cbd5e0;border-radius:8px;font-size:13px;color:#4a5568;cursor:pointer;transition:all .2s;width:100%;margin-top:8px;}
.btn-add-row:hover{border-color:#1f6dad;color:#1f6dad;background:#f0f0ff;}

.btn-submit{padding:10px 28px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
.btn-cancel{padding:10px 24px;background:white;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#4a5568;text-decoration:none;}
.alert-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;}
.rent-preview{background:#eef2ff;border-radius:8px;padding:12px 16px;font-size:14px;color:#4338ca;margin-top:8px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
<div style="max-width:960px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">✏️ 编辑合同：<?=e($r['contract_no'])?></h1>
        <a href="rental_view.php?id=<?=$id?>" class="back-btn">← 返回合同详情</a>
        <span class="type-badge <?=$r['type']?>">
            <?=$r['type']==='rental'?'💰 收费租赁':'🤝 免费借用'?>
        </span>
    </div>

    <?php if ($error): ?>
    <div class="alert-error">❌ <?=e($error)?></div>
    <?php endif; ?>

    <div class="alert-info">
        ℹ️ 合同类型创建后不可更改。如需修改设备明细，编辑后将重新保存所有设备记录。
    </div>

    <form method="POST">
        <!-- 客户信息 -->
        <div class="form-card">
            <div class="form-card-head">👤 客户信息</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">选择客户<span class="hint">（选择后自动填入）</span></label>
                        <select class="form-control" onchange="fillCustomer(this)">
                            <option value="">-- 手动填写 --</option>
                            <?php foreach ($customers as $c): ?>
                            <option value="<?=$c['id']?>"
                                data-name="<?=e($c['company_name']??$c['contact_name'])?>"
                                data-contact="<?=e($c['contact_name']??'')?>"
                                data-phone="<?=e($c['phone']??'')?>"
                                <?=$c['id']==$r['customer_id']?'selected':''?>>
                                <?=e($c['company_name']??$c['contact_name'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">客户名称<span class="req">*</span></label>
                        <input type="text" name="customer_name" id="customer_name" class="form-control"
                               value="<?=e($r['customer_name'])?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">联系人</label>
                        <input type="text" name="contact_name" id="contact_name" class="form-control"
                               value="<?=e($r['contact_name'])?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">联系电话</label>
                        <input type="text" name="contact_phone" id="contact_phone" class="form-control"
                               value="<?=e($r['contact_phone'])?>">
                    </div>
                </div>
                <input type="hidden" name="customer_id" id="customer_id" value="<?=$r['customer_id']?>">
            </div>
        </div>

        <!-- 租借信息 -->
        <div class="form-card">
            <div class="form-card-head">📅 租借信息</div>
            <div class="form-card-body">
                <div class="form-grid <?=$r['type']==='rental'?'cols3':''?>" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label">开始日期<span class="req">*</span></label>
                        <input type="date" name="start_date" id="start_date" class="form-control"
                               value="<?=e($r['start_date'])?>" onchange="calcRent()" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">到期日期<span class="req">*</span></label>
                        <input type="date" name="end_date" id="end_date" class="form-control"
                               value="<?=e($r['end_date'])?>" onchange="calcRent()" required>
                    </div>
                    <?php if ($r['type']==='rental'): ?>
                    <div class="form-group">
                        <label class="form-label">租金周期</label>
                        <select name="rent_cycle" id="rent_cycle" class="form-control" onchange="calcRent()">
                            <option value="day">按天</option>
                            <option value="week">按周</option>
                            <option value="month">按月</option>
                            <option value="year">按年</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($r['type']==='rental'): ?>
                <div class="form-grid" style="margin-bottom:10px;">
                    <div class="form-group">
                        <label class="form-label">租金单价（元/周期）</label>
                        <input type="number" name="rent_price" id="rent_price" class="form-control"
                               step="any" min="0" value="<?=$r['rent_price']?>" oninput="calcRent()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">押金（元）</label>
                        <input type="number" name="deposit" id="deposit" class="form-control"
                               step="any" min="0" value="<?=$r['deposit']?>" oninput="toggleDepositStatus()">
                    </div>
                </div>
                <div class="form-group" id="depositStatusGroup"
                     style="<?=$r['deposit']>0?'':'display:none;'?>">
                    <label class="form-label">押金状态</label>
                    <select name="deposit_status" class="form-control">
                        <option value="unpaid"   <?=$r['deposit_status']==='unpaid'  ?'selected':''?>>未收</option>
                        <option value="paid"     <?=$r['deposit_status']==='paid'    ?'selected':''?>>已收</option>
                        <option value="refunded" <?=$r['deposit_status']==='refunded'?'selected':''?>>已退</option>
                        <option value="deducted" <?=$r['deposit_status']==='deducted'?'selected':''?>>已扣</option>
                    </select>
                </div>
                <div class="rent-preview" id="rentPreview">📊 计算中...</div>
                <?php else: ?>
                <!-- 借用押金 -->
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">押金（元）<span class="hint">（可选）</span></label>
                        <input type="number" name="deposit" id="deposit" class="form-control"
                               step="any" min="0" value="<?=$r['deposit']?>" oninput="toggleDepositStatus()">
                    </div>
                    <div class="form-group" id="depositStatusGroup"
                         style="<?=$r['deposit']>0?'':'display:none;'?>">
                        <label class="form-label">押金状态</label>
                        <select name="deposit_status" class="form-control">
                            <option value="unpaid"   <?=$r['deposit_status']==='unpaid'  ?'selected':''?>>未收</option>
                            <option value="paid"     <?=$r['deposit_status']==='paid'    ?'selected':''?>>已收</option>
                            <option value="refunded" <?=$r['deposit_status']==='refunded'?'selected':''?>>已退</option>
                        </select>
                    </div>
                </div>
                <!-- 借用无租金，隐藏字段 -->
                <input type="hidden" name="rent_price" value="0">
                <input type="hidden" name="rent_cycle" value="month">
                <?php endif; ?>
            </div>
        </div>

        <!-- 设备明细 -->
        <div class="form-card">
            <div class="form-card-head">💻 设备明细</div>
            <div class="form-card-body" style="padding:16px;">
                
                <table class="items-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="width:28%;">设备名称<span style="color:#e53e3e;">*</span></th>
                            <th style="width:42%;">规格参数（处理器/内存/硬盘/显卡等）<span style="color:#e53e3e;">*</span></th>
                            <th style="width:12%;">市场估价(¥)</th>
                            <th style="width:8%;">数量</th>
                            <th style="width:10%;text-align:right;">小计(¥)</th>
                            <th style="width:60px;text-align:center;">删除</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                    <?php foreach ($items as $it): ?>
                    <tr>
                            <td><input type="text" name="item_name[]" value="<?=e($it['product_name'])?>" required style="width:100%;"></td>
                            <td><input type="text" name="item_spec[]" value="<?=e($it['spec'])?>" placeholder="如：i7-1260P / 16GB / 512GB SSD / 核显" style="width:100%;"></td>
                            <td><input type="number" name="item_market[]" value="<?=floatval($it['market_price'])?>" step="any" min="0" style="width:100%;" oninput="calcRow(this)"></td>
                            <td><input type="number" name="item_qty[]" value="<?=intval($it['quantity'])?>" min="1" style="width:100%;" oninput="calcRow(this)"></td>
                            <td class="subtotal-cell" style="text-align:right;font-weight:600;color:#3b82f6;padding-right:8px;"><?=number_format(floatval($it['market_price'])*intval($it['quantity']),2)?></td>
                            <td style="text-align:center;"><button type="button" onclick="this.closest('tr').remove();calcTotal()">删除</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                
                <button type="button" class="btn-add-row" onclick="addRow()">➕ 添加设备</button>
            </div>
        </div>

        <!-- 备注 -->
        <div class="form-card">
            <div class="form-card-head">📝 备注</div>
            <div class="form-card-body" style="padding:16px 24px;">
                <textarea name="notes" class="form-control" rows="5" style="width:100%;resize:vertical;min-height:120px;"><?=e($r['notes'])?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit">💾 保存修改</button>
            <a href="rental_view.php?id=<?=$id?>" class="btn-cancel">取消</a>
        </div>
    </form>
</div>
</main>

<script>
const isRental = <?=$r['type']==='rental'?'true':'false'?>;

function fillCustomer(sel) {
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('customer_id').value    = opt.value || 0;
    document.getElementById('customer_name').value  = opt.dataset.name    || '';
    document.getElementById('contact_name').value   = opt.dataset.contact || '';
    document.getElementById('contact_phone').value  = opt.dataset.phone   || '';
}

function calcRent() {
    if (!isRental) return;
    const start   = document.getElementById('start_date').value;
    const end     = document.getElementById('end_date').value;
    const price   = parseFloat(document.getElementById('rent_price').value) || 0;
    const cycle   = document.getElementById('rent_cycle').value;
    const preview = document.getElementById('rentPreview');
    if (!start || !end || !preview) return;
    const days   = Math.max(1, Math.round((new Date(end) - new Date(start)) / 86400000));
    const months = Math.max(1, Math.round(days / 30));
    let total = 0, desc = '';
    if (cycle === 'month') {
        total = price * months;
        desc  = `${months} 个月 × ¥${price.toFixed(2)}/月 = `;
    } else if (cycle === 'week') {
        const weeks = Math.max(1, Math.round(days / 7));
        total = price * weeks;
        desc  = `${weeks} 周 × ¥${price.toFixed(2)}/周 = `;
    } else {
        total = price * days;
        desc  = `${days} 天 × ¥${price.toFixed(2)}/天 = `;
    }
    preview.innerHTML = `📊 租期 ${days} 天　${desc}<strong style="color:#1f6dad;font-size:15px;">¥${total.toFixed(2)}</strong>`;
}

function toggleDepositStatus() {
    const val = parseFloat(document.getElementById('deposit').value) || 0;
    document.getElementById('depositStatusGroup').style.display = val > 0 ? '' : 'none';
}

function addRow() {
    const tbody = document.getElementById('itemsBody');
    const first = tbody.rows[0];
    const tr = first.cloneNode(true);
    tr.querySelectorAll('input').forEach(inp => {
        if (inp.name.includes('qty')) inp.value = '1';
        else if (inp.type === 'number') inp.value = '0';
        else inp.value = '';
    });
    const sub = tr.querySelector('.subtotal-cell');
    if (sub) sub.textContent = '0.00';
    tbody.appendChild(tr);
    calcTotal();
}


function calcRow(input) {
    const tr    = input.closest('tr');
    const qty   = parseFloat(tr.querySelector('input[name="item_qty[]"]')?.value) || 0;
    const price = parseFloat(tr.querySelector('input[name="item_rent[]"]')?.value) || 0;
    const sub   = tr.querySelector('.subtotal-cell');
    if (sub) sub.textContent = (qty * price).toFixed(2);
    calcTotal();
}

function calcTotal() {
    let total = 0;
    document.querySelectorAll('#itemsBody .subtotal-cell').forEach(td => {
        total += parseFloat(td.textContent) || 0;
    });
    const el = document.getElementById('totalAmount');
    if (el) el.textContent = '¥' + total.toFixed(2);
}

function delRow(btn) {
    const tbody = document.getElementById('itemsBody');
    if (tbody.rows.length > 1) btn.closest('tr').remove();
    else sapToast('至少保留一台设备', 'info');
}

// 初始化
calcRent();
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
