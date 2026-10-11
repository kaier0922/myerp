<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$conn    = getDBConnection();
$uid     = (int)$_SESSION['user_id'];
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';
$id      = intval($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: receipts.php'); exit; }

$es = $conn->prepare("SELECT * FROM receipts WHERE id=? AND user_id=?");
$es->bind_param('ii', $id, $uid); $es->execute();
$existing = $es->get_result()->fetch_assoc(); $es->close();
if (!$existing) die('收据不存在或无权限');

$ei = $conn->prepare("SELECT * FROM receipt_items WHERE receipt_id=? ORDER BY id");
$ei->bind_param('i', $id); $ei->execute();
$existing_items = $ei->get_result()->fetch_all(MYSQLI_ASSOC); $ei->close();

$is_ar       = empty($existing_items);
$fixed_amount = floatval($existing['total_amount']);

// ── POST 保存 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = trim($_POST['note'] ?? '');
    if ($is_ar) {
        $item_name = trim($_POST['item_name'] ?? '');
        $item_spec = trim($_POST['item_spec'] ?? '');
        try {
            $conn->begin_transaction();
            $s = $conn->prepare("UPDATE receipts SET note=? WHERE id=? AND user_id=?");
            $s->bind_param('sii', $note, $id, $uid); if (!$s->execute()) throw new Exception($s->error); $s->close();
            if ($item_name !== '') {
                $conn->query("DELETE FROM receipt_items WHERE receipt_id=$id");
                $q1=1; $u1='项';
                $s2 = $conn->prepare("INSERT INTO receipt_items (receipt_id,item_name,spec,qty,unit,unit_price,amount,user_id) VALUES (?,?,?,?,?,?,?,?)");
                $s2->bind_param('issisddi', $id, $item_name, $item_spec, $q1, $u1, $fixed_amount, $fixed_amount, $uid);
                $s2->execute(); $s2->close();
            }
            $conn->commit();
            writeLog($conn, 'receipts', 'update', '修改收据');
            header('Location: receipt_view.php?id='.$id); exit;
        } catch (Exception $e) { $conn->rollback(); die('❌ '.$e->getMessage()); }
    }
    $customer_id    = isset($_POST['customer_id']) && $_POST['customer_id'] !== '' ? intval($_POST['customer_id']) : NULL;
    $walkin_name    = trim($_POST['walkin_name'] ?? '');
    $walkin_phone   = trim($_POST['walkin_phone'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '现金');
    $tax_rate       = floatval($_POST['tax_rate'] ?? 0);
    $paid_amount_in = trim($_POST['paid_amount'] ?? '');
    $seal_id        = isset($_POST['seal_id']) && $_POST['seal_id'] !== '' ? intval($_POST['seal_id']) : NULL;
    $names  = $_POST['item_name']  ?? []; $specs  = $_POST['spec'] ?? [];
    $qtys   = $_POST['qty']        ?? []; $units  = $_POST['unit'] ?? [];
    $prices = $_POST['unit_price'] ?? [];
    $subtotal = 0; $valid_items = [];
    foreach ($names as $k => $name) {
        if (trim($name) === '') continue;
        $q = floatval($qtys[$k] ?? 1); $p = floatval($prices[$k] ?? 0); $a = round($q*$p, 2);
        $subtotal += $a;
        $valid_items[] = ['name'=>trim($name),'spec'=>trim($specs[$k]??''),'qty'=>$q,'unit'=>trim($units[$k]??''),'price'=>$p,'amount'=>$a];
    }
    if (empty($valid_items)) {
        $subtotal = floatval($existing['subtotal']); $tax_amount = floatval($existing['tax_amount']); $total_amount = floatval($existing['total_amount']);
    } else {
        $tax_amount = round($subtotal * $tax_rate / 100, 2); $total_amount = $subtotal + $tax_amount;
    }
    $paid_amount = $paid_amount_in !== '' ? floatval($paid_amount_in) : $total_amount;
    try {
        $conn->begin_transaction();
        $s = $conn->prepare("UPDATE receipts SET customer_id=?,walkin_name=?,walkin_phone=?,subtotal=?,tax_amount=?,total_amount=?,paid_amount=?,payment_method=?,note=?,seal_id=? WHERE id=? AND user_id=?");
        $s->bind_param('issddddssiii', $customer_id,$walkin_name,$walkin_phone,$subtotal,$tax_amount,$total_amount,$paid_amount,$payment_method,$note,$seal_id,$id,$uid);
        if (!$s->execute()) throw new Exception('更新失败: '.$s->error); $s->close();
        if (!empty($valid_items)) {
            $conn->query("DELETE FROM receipt_items WHERE receipt_id=$id");
            foreach ($valid_items as $it) {
                $s2 = $conn->prepare("INSERT INTO receipt_items (receipt_id,item_name,spec,qty,unit,unit_price,amount,user_id) VALUES (?,?,?,?,?,?,?,?)");
                $s2->bind_param('issdsddi', $id,$it['name'],$it['spec'],$it['qty'],$it['unit'],$it['price'],$it['amount'],$uid);
                $s2->execute(); $s2->close();
            }
        }
        $conn->commit(); header('Location: receipt_view.php?id='.$id); exit;
    } catch (Exception $e) { $conn->rollback(); die('❌ '.$e->getMessage()); }
}

$customers = $conn->query("SELECT id, COALESCE(short_name,company_name,contact_name) AS display_name FROM customers WHERE ".tenantWhere()." ORDER BY display_name");
$seals     = $conn->query("SELECT id, seal_name FROM seals WHERE ".tenantWhere()." ORDER BY id DESC");
$conn->close();
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<?php $page_title = '编辑收据 - ' . e($existing['receipt_no']); include __DIR__ . '/head.php'; ?>
<style>
.page-inner { max-width:900px; margin:0 auto; }
.card { background:white; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); margin-bottom:20px; overflow:hidden; }
.card-header { padding:14px 20px; font-weight:600; font-size:15px; color:#1a202c; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; }
.card-body { padding:20px; }
.form-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
.form-input,.form-select { padding:9px 12px; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; transition:all .2s; font-family:inherit; width:100%; box-sizing:border-box; }
.form-input:focus,.form-select:focus { outline:none; border-color:#1f6dad; box-shadow:0 0 0 3px rgba(31,109,173,.1); }
.items-table { width:100%; border-collapse:collapse; }
.items-table th { background:#f7fafc; padding:10px 8px; text-align:left; font-size:13px; font-weight:600; color:#4a5568; border-bottom:2px solid #e2e8f0; }
.items-table td { padding:6px 8px; border-bottom:1px solid #f0f0f0; }
.items-table td input { width:100%; padding:7px 9px; border:1px solid #e2e8f0; border-radius:6px; font-size:13px; font-family:inherit; box-sizing:border-box; }
.items-table td input:focus { outline:none; border-color:#1f6dad; }
.items-table td input[readonly] { background:#f7fafc; color:#4a5568; cursor:default; }
.amount-cell { font-weight:600; color:#047857; text-align:right; padding-right:12px !important; }
.total-bar { background:#f0f4ff; border-radius:8px; padding:12px 20px; margin:12px 16px 16px; display:flex; justify-content:flex-end; align-items:center; gap:16px; }
.total-bar strong { color:#1f6dad; font-size:18px; }
.readonly-val { padding:9px 12px; background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; font-size:14px; color:#718096; }
.readonly-val.green { color:#047857; font-weight:600; background:#f0fff4; border-color:#9ae6b4; }
.btn-danger { background:#fee2e2; color:#c53030; border:1px solid #fca5a5; padding:6px 12px; font-size:13px; border-radius:6px; cursor:pointer; }
</style>
</head><body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
<div class="page-inner">
    <div class="page-header">
        <div>
            <h1 class="page-title">✏️ 编辑收据</h1>
            <p style="color:#718096;font-size:14px;margin-top:4px;">收据编号：<?= e($existing['receipt_no']) ?></p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="receipt_view.php?id=<?= $id ?>" class="btn btn-secondary">← 返回查看</a>
            <button form="editForm" type="submit" class="btn btn-primary">💾 保存修改</button>
        </div>
    </div>

    <form method="post" id="editForm">

    <?php if ($is_ar): ?>
        <!-- AR 收据：仅可改项目名/规格/说明 -->
        <div class="card">
            <div class="card-header">✏️ 补充收款说明 <span style="font-size:12px;color:#a0aec0;font-weight:normal;">金额由应收单生成，已锁定</span></div>
            <div class="card-body">
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 16px;margin-bottom:16px;font-size:13px;color:#92400e;">
                    ⚠️ 此收据由应收单生成，金额已固定，仅可补充项目名称、规格和收款说明。
                </div>
                <div class="form-grid" style="margin-bottom:16px;">
                    <div><label class="form-label">联系人</label><div class="readonly-val"><?= e($existing['walkin_name'] ?: '（未填写）') ?></div></div>
                    <div><label class="form-label">收款方式</label><div class="readonly-val"><?= e($existing['payment_method'] ?: '现金') ?></div></div>
                    <div><label class="form-label">收款金额（锁定）</label><div class="readonly-val green">¥<?= number_format($fixed_amount, 2) ?></div></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div><label class="form-label">项目名称 <span style="color:#1f6dad;font-weight:normal;">（可修改）</span></label>
                        <input name="item_name" class="form-input" placeholder="例如：电脑维修、IT设备采购" value="<?= e($existing['note'] ?? '') ?>"></div>
                    <div><label class="form-label">规格 <span style="color:#1f6dad;font-weight:normal;">（可修改）</span></label>
                        <input name="item_spec" class="form-input" placeholder="规格说明"></div>
                </div>
                <div><label class="form-label">收款说明 <span style="color:#1f6dad;font-weight:normal;">（可修改）</span></label>
                    <input name="note" class="form-input" placeholder="例如：送货单 SH202604280001 货款" value="<?= e($existing['note']) ?>"></div>
            </div>
        </div>
        <div style="background:#f0fff4;border:1px solid #9ae6b4;border-radius:10px;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <span style="color:#276749;font-size:14px;">💰 收据金额（锁定）</span>
            <strong style="color:#047857;font-size:22px;">¥<?= number_format($fixed_amount, 2) ?></strong>
        </div>

    <?php else: ?>
        <!-- 手动收据：全字段可编辑 -->
        <div class="card">
            <div class="card-header">👤 客户信息</div>
            <div class="card-body">
                <div class="form-grid">
                    <div><label class="form-label">客户（可选）</label>
                        <select name="customer_id" class="form-select">
                            <option value="">—— 散客 ——</option>
                            <?php if ($customers) while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $existing['customer_id'] ? 'selected' : '' ?>><?= e($c['display_name']) ?></option>
                            <?php endwhile; ?>
                        </select></div>
                    <div><label class="form-label">联系人</label><input name="walkin_name" class="form-input" placeholder="联系人姓名" value="<?= e($existing['walkin_name']) ?>"></div>
                    <div><label class="form-label">联系电话</label><input name="walkin_phone" class="form-input" placeholder="联系方式" value="<?= e($existing['walkin_phone']) ?>"></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">💰 收款信息</div>
            <div class="card-body">
                <div class="form-grid">
                    <div><label class="form-label">收款方式</label>
                        <input type="text" name="payment_method" class="form-input" value="<?= e($existing['payment_method'] ?? '现金') ?>"
                               list="pm-list" placeholder="选择或输入" autocomplete="off">
                        <datalist id="pm-list">
                            <option value="现金"><option value="微信支付"><option value="支付宝">
                            <option value="银行转账"><option value="刷卡（POS）"><option value="对公转账"><option value="月结">
                        </datalist></div>
                    <div><label class="form-label">税率（%）</label>
                        <input name="tax_rate" class="form-input" type="number" step="0.01" min="0" max="100"
                               value="<?= $existing['tax_amount']>0&&$existing['subtotal']>0 ? round($existing['tax_amount']/$existing['subtotal']*100,2) : 0 ?>"></div>
                    <div><label class="form-label">实收金额（留空=合计）</label>
                        <input name="paid_amount" class="form-input" type="number" step="any" placeholder="默认=合计"
                               value="<?= $existing['paid_amount'] != $existing['total_amount'] ? e($existing['paid_amount']) : '' ?>"></div>
                    <div><label class="form-label">盖章（可选）</label>
                        <select name="seal_id" class="form-select">
                            <option value="" <?= !$existing['seal_id'] ? 'selected' : '' ?>>—— 不盖章 ——</option>
                            <?php if ($seals) while ($s = $seals->fetch_assoc()): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['id'] == $existing['seal_id'] ? 'selected' : '' ?>><?= e($s['seal_name']) ?></option>
                            <?php endwhile; ?>
                        </select></div>
                    <div style="grid-column:span 2;"><label class="form-label">收款说明</label>
                        <input name="note" class="form-input" placeholder="例如：送货单 SH202604280001 货款" value="<?= e($existing['note']) ?>"></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <span>📋 收据明细</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">➕ 添加行</button>
            </div>
            <div style="padding:0;">
                <datalist id="unitList">
                    <option value="个"><option value="台"><option value="套"><option value="件">
                    <option value="块"><option value="条"><option value="根"><option value="张">
                    <option value="包"><option value="箱"><option value="米"><option value="千克">
                    <option value="吨"><option value="升"><option value="卷"><option value="颗">
                    <option value="支"><option value="盒"><option value="批"><option value="次">
                    <option value="小时"><option value="天"><option value="月"><option value="年">
                    <option value="项">
                </datalist>
                <table class="items-table" id="itemsTable">
                    <thead><tr>
                        <th style="width:35%;padding-left:16px;">项目名称</th>
                        <th>规格</th><th style="width:9%;">数量</th><th style="width:8%;">单位</th>
                        <th style="width:11%;">单价</th><th style="width:11%;text-align:right;padding-right:12px;">金额</th>
                        <th style="width:7%;text-align:center;">操作</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($existing_items as $it): ?>
                    <tr>
                        <td style="padding-left:10px;"><input name="item_name[]" value="<?= e($it['item_name']) ?>"></td>
                        <td><input name="spec[]" value="<?= e($it['spec']) ?>"></td>
                        <td><input name="qty[]" value="<?= $it['qty'] ?>" type="number" step="0.01" oninput="calcRow(this)"></td>
                        <td><input name="unit[]" value="<?= e($it['unit']) ?>" list="unitList" placeholder="个/项"></td>
                        <td><input name="unit_price[]" value="<?= $it['unit_price'] ?>" type="number" step="any" oninput="calcRow(this)"></td>
                        <td class="amount-cell"><?= number_format($it['amount'], 2) ?></td>
                        <td style="text-align:center;"><button type="button" class="btn-danger" onclick="delRow(this)">删除</button></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="total-bar"><span>合计：</span><strong id="totalDisplay">¥<?= number_format($existing['subtotal'], 2) ?></strong></div>
            </div>
        </div>
    <?php endif; ?>

        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:8px;">
            <a href="receipt_view.php?id=<?= $id ?>" class="btn btn-secondary">取消</a>
            <button type="submit" class="btn btn-primary">💾 保存修改</button>
        </div>
    </form>
</div>
</main>

<script>
function addRow() {
    const tbody = document.querySelector('#itemsTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `<td style="padding-left:10px;"><input name="item_name[]"></td>
        <td><input name="spec[]"></td>
        <td><input name="qty[]" value="1" type="number" step="0.01" oninput="calcRow(this)"></td>
        <td><input name="unit[]" list="unitList" placeholder="个/项"></td>
        <td><input name="unit_price[]" value="0" type="number" step="any" oninput="calcRow(this)"></td>
        <td class="amount-cell">0.00</td>
        <td style="text-align:center;"><button type="button" class="btn-danger" onclick="delRow(this)">删除</button></td>`;
    tbody.appendChild(tr);
}
function delRow(btn) {
    const tbody = document.querySelector('#itemsTable tbody');
    if (tbody.rows.length > 1) { btn.closest('tr').remove(); calcTotal(); }
    else alert('至少保留一行');
}
function calcRow(inp) {
    const tr = inp.closest('tr');
    const q = parseFloat(tr.querySelector('input[name="qty[]"]').value) || 0;
    const p = parseFloat(tr.querySelector('input[name="unit_price[]"]').value) || 0;
    tr.querySelector('.amount-cell').innerText = (q * p).toFixed(2);
    calcTotal();
}
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.amount-cell').forEach(td => total += parseFloat(td.innerText) || 0);
    const d = document.getElementById('totalDisplay');
    if (d) d.innerText = '¥' + total.toFixed(2);
}
</script>
</body></html>
