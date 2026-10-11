<?php
session_start();
error_reporting(0); // 防止 PHP notice 污染 JSON 响应
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$id       = intval($_GET['id'] ?? 0);
$msg      = '';
$msg_type = '';

// 读取合同
$stmt = $conn->prepare("SELECT * FROM rental_contracts WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("i", $id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
if (!$r) { header('Location: rentals.php'); exit; }

// 读取设备明细
$items = $conn->prepare("SELECT * FROM rental_items WHERE contract_id=? ORDER BY id");
$items->bind_param("i", $id);
$items->execute();
$items = $items->get_result()->fetch_all(MYSQLI_ASSOC);

// 读取收付款记录
$payments = $conn->prepare("SELECT rp.*, u.nickname as op_name FROM rental_payments rp
    LEFT JOIN users u ON rp.created_by=u.id
    WHERE rp.contract_id=? ORDER BY rp.payment_date DESC, rp.id DESC");
$payments->bind_param("i", $id);
$payments->execute();
$payments = $payments->get_result()->fetch_all(MYSQLI_ASSOC);

// 读取归还记录
$returns = $conn->prepare("SELECT rr.*, u.nickname as op_name FROM rental_returns rr
    LEFT JOIN users u ON rr.created_by=u.id
    WHERE rr.contract_id=? ORDER BY rr.return_date DESC");
$returns->bind_param("i", $id);
$returns->execute();
$returns = $returns->get_result()->fetch_all(MYSQLI_ASSOC);

// ── AJAX 操作 ──
$action = $_POST['action'] ?? '';

if ($action === 'add_payment') {
    header('Content-Type: application/json');
    $payment_type   = $_POST['payment_type']   ?? 'rent';
    $amount         = floatval($_POST['amount'] ?? 0);
    $payment_date   = $_POST['payment_date']   ?? date('Y-m-d');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $period_start   = $_POST['period_start'] ?: null;
    $period_end     = $_POST['period_end']   ?: null;
    $notes          = trim($_POST['notes']   ?? '');

    $stmt2 = $conn->prepare("INSERT INTO rental_payments
        (contract_id, payment_type, amount, payment_date, payment_method, period_start, period_end, notes, created_by)
        VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt2->bind_param("isdsssssi",
        $id, $payment_type, $amount, $payment_date, $payment_method,
        $period_start, $period_end, $notes, $_SESSION['user_id']);
    $stmt2->execute();

    // 更新合同已收租金/押金
    if ($payment_type === 'rent') {
        $conn->query("UPDATE rental_contracts SET paid_rent = paid_rent + $amount WHERE id=$id AND user_id=" . intval($_SESSION['user_id']) . "");
    } elseif ($payment_type === 'deposit') {
        $safe_amount = floatval($amount);
        $conn->query("UPDATE rental_contracts SET deposit_paid_amount = deposit_paid_amount + $safe_amount,
            deposit_status = IF(deposit_paid_amount + $safe_amount >= deposit, 'paid', deposit_status) WHERE id=$id");
    } elseif ($payment_type === 'deposit_refund') {
        $conn->query("UPDATE rental_contracts SET deposit_status='refunded' WHERE id=$id");
    }

    echo json_encode(['success'=>true]);
    $conn->close(); exit;
}

if ($action === 'process_return') {
    header('Content-Type: application/json');
    $return_date      = $_POST['return_date']      ?? date('Y-m-d');
    $device_status    = $_POST['device_status']    ?? 'normal';
    $damage_desc      = trim($_POST['damage_desc'] ?? '');
    $compensation_type= $_POST['compensation_type']?? 'none';
    $compensation_amt = floatval($_POST['compensation_amount'] ?? 0);
    $deposit_deduct   = floatval($_POST['deposit_deduct']      ?? 0);
    $receipt_amount   = floatval($_POST['receipt_amount']      ?? 0);
    $notes            = trim($_POST['notes'] ?? '');

    // 计算实际租金（多退少补）
    $actual_days = max(1, round((strtotime($return_date) - strtotime($r['start_date'])) / 86400));
    $actual_rent = 0;
    if ($r['type'] === 'rental' && $r['rent_price'] > 0) {
        if ($r['rent_cycle'] === 'month') {
            $actual_months = max(1, round($actual_days / 30));
            $actual_rent   = $r['rent_price'] * $actual_months;
        } else {
            $actual_rent = $r['rent_price'] * $actual_days;
        }
    }
    $rent_balance = $actual_rent - floatval($r['paid_rent']);

    $stmt3 = $conn->prepare("INSERT INTO rental_returns
        (contract_id, return_date, actual_days, actual_rent, rent_balance,
         device_status, damage_desc, compensation_type, compensation_amount,
         deposit_deduct, deposit_refund, receipt_amount, notes, created_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $deposit_refund = max(0, floatval($r['deposit_paid_amount']) - $deposit_deduct);
    $stmt3->bind_param("isiddsssddddsi",
        $id, $return_date, $actual_days, $actual_rent, $rent_balance,
        $device_status, $damage_desc, $compensation_type, $compensation_amt,
        $deposit_deduct, $deposit_refund, $receipt_amount, $notes, $_SESSION['user_id']);
    $stmt3->execute();

    // 更新合同状态
    $conn->query("UPDATE rental_contracts SET status='returned' WHERE id=$id AND user_id=" . intval($_SESSION['user_id']) . "");

    echo json_encode([
        'success'      => true,
        'actual_days'  => $actual_days,
        'actual_rent'  => $actual_rent,
        'rent_balance' => $rent_balance,
        'deposit_refund'=> $deposit_refund,
    ]);
    $conn->close(); exit;
}

if ($action === 'cancel_contract') {
    header('Content-Type: application/json');
    $conn->query("UPDATE rental_contracts SET status='cancelled' WHERE id=$id");
    echo json_encode(['success'=>true]);
    $conn->close(); exit;
}

if ($action === 'generate_contract') {
    header('Content-Type: application/json');
    error_reporting(0);

    $its = $conn->query("SELECT * FROM rental_items WHERE contract_id=$id ORDER BY id");
    $its = $its->fetch_all(MYSQLI_ASSOC);

    if (empty($its)) {
        echo json_encode(['success'=>false,'msg'=>'没有设备明细，无法生成合同']);
        $conn->close(); exit;
    }

    // 合同类型（必须先定义，$total 计算依赖它）
    $con_type   = $r['type']; // rental / loan
    $con_no     = $r['contract_no']; // 直接沿用租赁合同编号
    $type_label = $con_type === 'rental' ? '租赁' : '借用';
    // 计算合计（$con_type 已定义）
    $total = $con_type === 'rental' ? floatval($r['total_rent']) : 0;
    // 周期标签
    $cycle_label_map = ['day'=>'天','week'=>'周','month'=>'月','year'=>'年'];
    $cycle_label = $cycle_label_map[$r['rent_cycle']] ?? '天';
    // 付款条款
    $rent_desc = '';
    if ($con_type === 'rental') {
        $rent_desc = "租金单价：¥" . number_format($r['rent_price'],2) . "/{$cycle_label}\n";
    }
    $pay_terms = $rent_desc
               . "租期：" . $r['start_date'] . " 至 " . $r['end_date']
               . ($r['deposit']>0 ? "\n押金：¥".number_format($r['deposit'],2) : "");
    $del_terms = "设备由甲方送货上门，验收签字后生效。";
    $war_terms = "租借期间设备出现非人为故障，由甲方负责维修。人为损坏或丢失按市场估价赔偿。";
    $notes_val = "由{$type_label}合同 " . $r['contract_no'] . " 自动生成";
    $status_val = 'active';
    // 提前计算设备总数量
    $total_qty_pre = array_sum(array_column($its, 'quantity')) ?: 1;
    $conn->begin_transaction();
    try {
        $sla_data = $con_type === 'rental' ? json_encode([
            'rent_price'  => floatval($r['rent_price']),
            'rent_cycle'  => $r['rent_cycle'],
            'cycle_label' => $cycle_label,
            'total_qty'   => $total_qty_pre,
            'total_rent'  => $total,
            'start_date'  => $r['start_date'],
            'end_date'    => $r['end_date'],
            'deposit'     => floatval($r['deposit']),
        ], JSON_UNESCAPED_UNICODE) : null;
        $ins = $conn->prepare("INSERT INTO sales_contracts
            (contract_no, contract_type, customer_id, customer_name, contract_date,
             total_amount, payment_terms, delivery_terms, warranty_terms,
             work_start, work_end, sla_terms,
             status, notes, created_by, user_id)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $ins->bind_param("ssissdssssssssii",
            $con_no, $con_type, $r['customer_id'], $r['customer_name'],
            date('Y-m-d'), $total,
            $pay_terms, $del_terms, $war_terms,
            $r['start_date'], $r['end_date'], $sla_data,
            $status_val, $notes_val, $_SESSION['user_id'], $_SESSION['user_id']);
        if (!$ins->execute()) throw new Exception('主合同写入失败：' . $ins->error);
        $con_id = $conn->insert_id;
        // 插入设备明细
        $iins = $conn->prepare("INSERT INTO sales_contract_items
            (contract_id, item_no, product_name, specification, unit, quantity, price, amount)
            VALUES (?,?,?,?,?,?,?,?)");
        $total_qty = $total_qty_pre;
        foreach ($its as $idx => $it) {
            $no    = $idx + 1;
            $spec  = $it['spec'] ?? '';
            $unit  = '台';
            $qty   = floatval($it['quantity']);
            // 第一条设备清单用市场估值（赔偿参考），第二条才是真实租金
            $price = floatval($it['market_price'] ?? 0);
            $amt   = round($price * $qty, 2);
            $iins->bind_param("iisssddd",
                $con_id, $no, $it['product_name'], $spec, $unit, $qty, $price, $amt);
            $iins->execute();
        }

        $conn->commit();
        echo json_encode(['success'=>true, 'contract_id'=>$con_id, 'contract_no'=>$con_no]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success'=>false,'msg'=>$e->getMessage()]);
    }
    $conn->close(); exit;
}


function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
function daysDiff($date) { return intval((strtotime($date) - time()) / 86400); }

$days_left  = daysDiff($r['end_date']);
$is_active  = $r['status'] === 'active';
$is_overdue = $is_active && $days_left < 0;
$is_expiring= $is_active && $days_left >= 0 && $days_left <= 3;

// 设备总价值
$total_market = array_sum(array_column($items, 'market_price'));
$total_cost   = array_sum(array_column($items, 'cost_price'));

$conn->close();

$payment_type_labels = [
    'rent'           => '租金',
    'deposit'        => '押金',
    'deposit_refund' => '退押金',
    'compensation'   => '赔偿',
];
$status_labels = [
    'active'    => ['label'=>'进行中',  'color'=>'#135286', 'bg'=>'#d1fae5'],
    'expired'   => ['label'=>'已逾期',  'color'=>'#dc2626', 'bg'=>'#fee2e2'],
    'returned'  => ['label'=>'已归还',  'color'=>'#6b7280', 'bg'=>'#f3f4f6'],
    'cancelled' => ['label'=>'已取消',  'color'=>'#9ca3af', 'bg'=>'#f9fafb'],
];
$sl = $status_labels[$r['status']] ?? $status_labels['active'];
?>
<?php $page_title = '合同详情 - ' . e($r['contract_no']); include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,

/* 预警横幅 */
.alert-banner{padding:14px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;}
.alert-warn{background:#fffbeb;border:1px solid #f59e0b;color:#92400e;}

/* 布局 */
.layout{display:grid;grid-template-columns:1fr 380px;gap:24px;align-items:start;}

/* 卡片 */
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;margin-bottom:20px;}
.card-head{padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;}
.card-title{font-size:15px;font-weight:600;color:#1a202c;}
.card-

/* 合同概览 */
.contract-header{background:linear-gradient(135deg,#1f6dad,#135286);padding:24px;color:white;border-radius:12px;margin-bottom:20px;}
.contract-header.loan{background:linear-gradient(135deg,#135286,#047857);}
.ch-no{font-size:13px;opacity:.8;margin-bottom:6px;font-family:'Courier New',monospace;}
.ch-customer{font-size:22px;font-weight:700;margin-bottom:4px;}
.ch-meta{font-size:13px;opacity:.85;}
.ch-badges{display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;}
.ch-badge{padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;background:rgba(255,255,255,.2);}

/* 金额卡片 */
.amount-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:16px;}
.amount-item{background:#f7fafc;border-radius:8px;padding:14px;text-align:center;}
.amount-item .av{font-size:20px;font-weight:700;font-family:'Courier New',monospace;}
.amount-item .al{font-size:12px;color:#718096;margin-top:4px;}
.amount-item .av.blue{color:#3b82f6;}
.amount-item .av.green{color:#135286;}
.amount-item .av.red{color:#dc2626;}
.amount-item .av.orange{color:#d97706;}

/* 信息行 */
.info-row{display:flex;padding:8px 0;border-bottom:1px solid #f0f4f8;font-size:14px;}
.info-row:last-child{border-bottom:none;}
.info-label{width:100px;color:#718096;flex-shrink:0;}
.info-value{flex:1;color:#1a202c;font-weight:500;}

/* 设备表格 */
tbody tr:last-child
.mono{font-family:'Courier New',monospace;font-weight:600;}

/* 操作按钮 */
.btn-green{padding:9px 18px;background:linear-gradient(135deg,#135286,#047857);color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;}
.btn-orange{padding:9px 18px;background:#d97706;color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;}
.btn-red{padding:9px 18px;background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;border-radius:8px;font-size:13px;cursor:pointer;}
.btn-purple{padding:9px 18px;background:linear-gradient(135deg,#135286,#6d28d9);color:white;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;}
.btn-gray{padding:9px 18px;background:#f7fafc;color:#4a5568;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;cursor:pointer;text-decoration:none;}
.btn-group{display:flex;gap:8px;flex-wrap:wrap;}

/* 收款记录 */
.payment-item{background:#f7fafc;border-radius:8px;padding:12px;margin-bottom:8px;border-left:3px solid #1f6dad;}
.payment-item.deposit{border-left-color:#f59e0b;}
.payment-item.compensation{border-left-color:#ef4444;}
.pi-top{display:flex;justify-content:space-between;margin-bottom:4px;}
.pi-amount{font-size:16px;font-weight:700;font-family:'Courier New',monospace;color:#135286;}
.pi-type{font-size:12px;padding:2px 8px;background:#eef2ff;color:#4338ca;border-radius:10px;}
.pi-meta{font-size:12px;color:#718096;}

/* 归还记录 */
.return-item{background:#f7fafc;border-radius:8px;padding:14px;margin-bottom:8px;}
.return-status{display:inline-block;padding:3px 10px;border-radius:10px;font-size:12px;font-weight:600;margin-bottom:8px;}
.rs-normal{background:#d1fae5;color:#065f46;}
.rs-damaged{background:#fef3c7;color:#92400e;}
.rs-lost{background:#fee2e2;color:#991b1b;}

/* 弹窗 */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:400;align-items:center;justify-content:center;}
.modal-overlay.open{display:flex;}
.modal{background:white;border-radius:14px;width:520px;max-width:95vw;max-height:85vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.2);}
.modal-head{padding:20px 24px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;display:flex;justify-content:space-between;align-items:center;border-radius:14px 14px 0 0;}
.modal-head.green{background:linear-gradient(135deg,#135286,#047857);}
.modal-head.orange{background:linear-gradient(135deg,#d97706,#b45309);}
.modal-title{font-size:16px;font-weight:600;}
.modal-close{background:rgba(255,255,255,.2);border:none;color:white;width:30px;height:30px;border-radius:6px;font-size:18px;cursor:pointer;}
.modal-
.form-label .hint{font-weight:400;color:#a0aec0;font-size:12px;margin-left:6px;}
.form-control{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;}
.form-control:focus{outline:none;border-color:#1f6dad;}
.modal-footer{padding:16px 24px;border-top:1px solid #e2e8f0;display:flex;gap:8px;justify-content:flex-end;}
.calc-preview{background:#eef2ff;border-radius:8px;padding:12px;font-size:13px;color:#4338ca;margin-top:8px;line-height:1.8;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
<div style="max-width:960px;margin:0 auto;">
    <?php if (isset($_GET['created'])): ?>
    <div style="background:#d1fae5;border:1px solid #1f6dad;color:#065f46;padding:12px 20px;border-radius:8px;margin-bottom:16px;">✅ 合同创建成功！</div>
    <?php endif; ?>

    <?php if ($is_overdue): ?>
    <div class="alert-banner alert-danger">⚠️ 此合同已逾期 <?=abs($days_left)?> 天，请尽快联系客户归还设备！</div>
    <?php elseif ($is_expiring): ?>
    <div class="alert-banner alert-warn">🔔 此合同还有 <?=$days_left?> 天到期，请提前联系客户续租或安排归还！</div>
    <?php endif; ?>

    <div class="page-header">
        <div style="display:flex;align-items:center;gap:16px;">
            <h1 class="page-title"><?=$r['type']==='rental'?'📋 租赁合同':'🤝 借用合同'?></h1>
            <a href="rentals.php" class="btn-gray">← 返回列表</a>
        </div>
        <?php if ($is_active): ?>
        <div class="btn-group">
            <?php if ($r['type']==='rental'): ?>
            <button class="btn-primary" onclick="openPaymentModal()">💰 登记收款</button>
            <?php endif; ?>
            <?php if ($r['deposit'] > 0 && $r['deposit_status']==='unpaid'): ?>
            <button class="btn-orange" onclick="openDepositModal()">🏦 收押金</button>
            <?php endif; ?>
            <button class="btn-green" onclick="openReturnModal()">✅ 办理归还</button>
            <a href="rental_edit.php?id=<?=$id?>" class="btn-gray">✏️ 编辑</a>
            <button class="btn-red" onclick="cancelContract()">取消合同</button>
            <button class="btn-purple" onclick="generateContract()">📄 生成客户合同</button>
        </div>
        <?php endif; ?>
    </div>

    <div class="layout">
        <!-- 左侧主内容 -->
        <div>
            <!-- 合同头部 -->
            <div class="contract-header <?=$r['type']==='loan'?'loan':''?>">
                <div class="ch-no"><?=e($r['contract_no'])?></div>
                <div class="ch-customer"><?=e($r['customer_name'])?></div>
                <div class="ch-meta">
                    <?php if ($r['contact_name']): ?>👤 <?=e($r['contact_name'])?>&emsp;<?php endif; ?>
                    <?php if ($r['contact_phone']): ?>📱 <?=e($r['contact_phone'])?>&emsp;<?php endif; ?>
                    📅 <?=e($r['start_date'])?> ~ <?=e($r['end_date'])?>
                </div>
                <div class="ch-badges">
                    <span class="ch-badge"><?=$r['type']==='rental'?'💰 收费租赁':'🤝 免费借用'?></span>
                    <span class="ch-badge" style="background:<?=$sl['bg']?>;color:<?=$sl['color']?>;"><?=$sl['label']?></span>
                    <?php if ($is_active): ?>
                        <?php if ($is_overdue): ?>
                        <span class="ch-badge" style="background:#fee2e2;color:#991b1b;">逾期 <?=abs($days_left)?> 天</span>
                        <?php elseif ($is_expiring): ?>
                        <span class="ch-badge" style="background:#fef3c7;color:#92400e;">还剩 <?=$days_left?> 天</span>
                        <?php else: ?>
                        <span class="ch-badge">还剩 <?=$days_left?> 天</span>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 金额概览 -->
            <?php if ($r['type']==='rental'): ?>
            <div class="card">
                <div class="card-head"><span class="card-title">💰 财务概览</span></div>
                <div class="card-body">
                    <div class="amount-grid">
                        <div class="amount-item">
                            <div class="av blue">¥<?=number_format($r['total_rent'],2)?></div>
                            <div class="al">应收总租金</div>
                        </div>
                        <div class="amount-item">
                            <div class="av green">¥<?=number_format($r['paid_rent'],2)?></div>
                            <div class="al">已收租金</div>
                        </div>
                        <div class="amount-item">
                            <div class="av <?=$r['total_rent']-$r['paid_rent']>0?'red':'green'?>">
                                ¥<?=number_format($r['total_rent']-$r['paid_rent'],2)?>
                            </div>
                            <div class="al">未收租金</div>
                        </div>
                    </div>
                    <?php if ($r['deposit'] > 0): ?>
                    <div style="background:#fffbeb;border-radius:8px;padding:12px;font-size:13px;">
                        🏦 押金：¥<?=number_format($r['deposit'],2)?>
                        <span style="margin-left:12px;padding:2px 8px;border-radius:10px;font-size:12px;
                            background:<?=$r['deposit_status']==='paid'?'#d1fae5':'#fee2e2'?>;
                            color:<?=$r['deposit_status']==='paid'?'#065f46':'#991b1b'?>;">
                            <?=['unpaid'=>'未收','paid'=>'已收','refunded'=>'已退','deducted'=>'已扣'][$r['deposit_status']]?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 设备明细 -->
            <div class="card">
                <div class="card-head">
                    <span class="card-title">💻 设备明细（<?=count($items)?> 台）</span>
                    <div style="font-size:13px;color:#718096;">
                        市场总估值：<strong style="color:#3b82f6;">¥<?=number_format($total_market,2)?></strong>
                        &emsp;成本总价：<strong>¥<?=number_format($total_cost,2)?></strong>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="min-width:222px;white-space:nowrap;">设备名称</th>
                            <th style="min-width:182px;white-space:nowrap;">序列号</th>
                            <th style="width:100%;">规格参数</th>
                            <th style="width:125px;white-space:nowrap;">成本价</th>
                            <th style="width:125px;white-space:nowrap;">市场估价</th>
                            <th style="width:70px;white-space:nowrap;">数量</th>
                            <th style="width:125px;white-space:nowrap;">单价</th>
                            <th style="width:125px;white-space:nowrap;">小计</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $grand_total = 0;
                    foreach ($items as $it):
                        $subtotal = $it['quantity'] * $it['rent_price'];
                        $grand_total += $subtotal;
                    ?>
                    <tr>
                        <td style="white-space:nowrap;"><strong><?=e($it['product_name'])?></strong></td>
                        <td style="white-space:nowrap;"><span class="mono" style="font-size:12px;"><?=e($it['serial_no'])?:'-'?></span></td>
                        <td style="color:#4a5568;font-size:13px;"><?=e($it['spec'])?:'-'?></td>
                        <td class="mono" style="white-space:nowrap;">¥<?=number_format($it['cost_price'],2)?></td>
                        <td class="mono" style="color:#3b82f6;white-space:nowrap;">¥<?=number_format($it['market_price'],2)?></td>
                        <td><?=$it['quantity']?></td>
                        <td class="mono" style="white-space:nowrap;">¥<?=number_format($it['rent_price'],2)?></td>
                        <td class="mono" style="color:#1f6dad;font-weight:700;white-space:nowrap;">¥<?=number_format($subtotal,2)?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="7" style="text-align:right;padding:10px 16px;font-size:14px;color:#4a5568;font-weight:600;">合计金额：</td>
                            <td class="mono" style="font-size:16px;font-weight:700;color:#1f6dad;padding:10px 16px;white-space:nowrap;">¥<?=number_format($grand_total,2)?></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>
            <!-- 归还记录 -->
            <?php if (!empty($returns)): ?>
            <div class="card">
                <div class="card-head"><span class="card-title">📦 归还记录</span></div>
                <div class="card-body">
                    <?php foreach ($returns as $rt): ?>
                    <div class="return-item">
                        <span class="return-status <?=['normal'=>'rs-normal','damaged'=>'rs-damaged','lost'=>'rs-lost'][$rt['device_status']]?>">
                            <?=['normal'=>'✅ 完好归还','damaged'=>'⚠️ 有损坏','lost'=>'❌ 设备丢失'][$rt['device_status']]?>
                        </span>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:13px;">
                            <div>归还日期：<strong><?=e($rt['return_date'])?></strong></div>
                            <div>实际使用：<strong><?=$rt['actual_days']?> 天</strong></div>
                            <div>实际租金：<strong>¥<?=number_format($rt['actual_rent'],2)?></strong></div>
                            <div>租金差额：<strong style="color:<?=$rt['rent_balance']>0?'#dc2626':'#135286'?>">
                                <?=$rt['rent_balance']>0?'+':''?>¥<?=number_format($rt['rent_balance'],2)?>
                                （<?=$rt['rent_balance']>0?'需补收':'需退款'?>）
                            </strong></div>
                            <?php if ($rt['compensation_amount'] > 0): ?>
                            <div>赔偿金额：<strong style="color:#dc2626;">¥<?=number_format($rt['compensation_amount'],2)?></strong></div>
                            <div>赔偿方式：<strong><?=['none'=>'无','deposit_deduct'=>'扣押金','receipt'=>'出收据'][$rt['compensation_type']]?></strong></div>
                            <?php endif; ?>
                            <?php if ($rt['deposit_refund'] > 0): ?>
                            <div>退还押金：<strong style="color:#135286;">¥<?=number_format($rt['deposit_refund'],2)?></strong></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($rt['damage_desc']): ?>
                        <div style="margin-top:8px;font-size:12px;color:#718096;">损坏描述：<?=e($rt['damage_desc'])?></div>
                        <?php endif; ?>
                        <div style="margin-top:6px;font-size:12px;color:#a0aec0;">经办：<?=e($rt['op_name'])?> · <?=e($rt['return_date'])?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 右侧边栏 -->
        <div>
            <!-- 合同信息 -->
            <div class="card">
                <div class="card-head"><span class="card-title">📋 合同信息</span></div>
                <div class="card-body">
                    <div class="info-row"><span class="info-label">合同编号</span><span class="info-value" style="font-family:'Courier New';font-size:12px;"><?=e($r['contract_no'])?></span></div>
                    <div class="info-row"><span class="info-label">合同类型</span><span class="info-value"><?=$r['type']==='rental'?'收费租赁':'免费借用'?></span></div>
                    <div class="info-row"><span class="info-label">开始日期</span><span class="info-value"><?=e($r['start_date'])?></span></div>
                    <div class="info-row"><span class="info-label">到期日期</span><span class="info-value"><?=e($r['end_date'])?></span></div>
                    <?php if ($r['type']==='rental'): ?>
                    <div class="info-row"><span class="info-label">租金周期</span><span class="info-value"><?=['month'=>'按月','week'=>'按周','day'=>'按天'][$r['rent_cycle']]??$r['rent_cycle']?></span></div>
                    <div class="info-row"><span class="info-label">租金单价</span><span class="info-value" style="font-family:'Courier New';font-weight:700;color:#3b82f6;">¥<?=number_format($r['rent_price'],2)?>／<?=$r['rent_cycle']==='month'?'月':'天'?></span></div>
                    <?php endif; ?>
                    <?php if ($r['notes']): ?>
                    <div class="info-row"><span class="info-label">备注</span><span class="info-value" style="color:#718096;"><?=e($r['notes'])?></span></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 收付款记录 -->
            <div class="card">
                <div class="card-head">
                    <span class="card-title">💳 收款记录（<?=count($payments)?> 笔）</span>
                </div>
                <div class="card-body" style="padding:16px;">
                    <?php if (!empty($payments)): ?>
                        <?php foreach ($payments as $p): ?>
                        <div class="payment-item <?=$p['payment_type']==='deposit'||$p['payment_type']==='deposit_refund'?'deposit':($p['payment_type']==='compensation'?'compensation':'')?>">
                            <div class="pi-top">
                                <span class="pi-amount">¥<?=number_format($p['amount'],2)?></span>
                                <span class="pi-type"><?=$payment_type_labels[$p['payment_type']]??$p['payment_type']?></span>
                            </div>
                            <div class="pi-meta">
                                <?=e($p['payment_date'])?>
                                <?php if ($p['payment_method']): ?> · <?=e($p['payment_method'])?><?php endif; ?>
                                <?php if ($p['period_start']): ?> · <?=e($p['period_start'])?> ~ <?=e($p['period_end'])?><?php endif; ?>
                                <br>经办：<?=e($p['op_name'])?>
                                <?php if ($p['notes']): ?> · <?=e($p['notes'])?><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center;color:#a0aec0;padding:20px;font-size:13px;">暂无收款记录</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- 收租金弹窗 -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal">
        <div class="modal-head">
            <span class="modal-title">💰 登记收款</span>
            <button class="modal-close" onclick="closeModal('paymentModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">款项类型</label>
                    <select class="form-control" id="pay_type">
                        <option value="rent">租金</option>
                        <option value="compensation">赔偿</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">金额（元）</label>
                    <input type="number" class="form-control" id="pay_amount" step="any" min="0"
                           value="<?=floatval($r['total_rent']-$r['paid_rent'])?>">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">收款日期</label>
                    <input type="date" class="form-control" id="pay_date" value="<?=date('Y-m-d')?>">
                </div>
                <div class="form-group">
                    <label class="form-label">收款方式</label>
                    <select class="form-control" id="pay_method">
                        <option value="现金">现金</option>
                        <option value="微信支付">微信支付</option>
                        <option value="支付宝">支付宝</option>
                        <option value="银行转账">银行转账</option>
                        <option value="对公转账">对公转账</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">租期开始<span class="hint">（可选）</span></label>
                    <input type="date" class="form-control" id="pay_period_start">
                </div>
                <div class="form-group">
                    <label class="form-label">租期结束<span class="hint">（可选）</span></label>
                    <input type="date" class="form-control" id="pay_period_end">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">备注</label>
                <input type="text" class="form-control" id="pay_notes" placeholder="可选">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeModal('paymentModal')">取消</button>
            <button class="btn-primary" onclick="submitPayment()">💾 确认收款</button>
        </div>
    </div>
</div>

<!-- 收押金弹窗 -->
<div class="modal-overlay" id="depositModal">
    <div class="modal">
        <div class="modal-head modal-head orange">
            <span class="modal-title">🏦 登记押金</span>
            <button class="modal-close" onclick="closeModal('depositModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">押金金额（元）</label>
                    <input type="number" class="form-control" id="dep_amount"
                           value="<?=floatval($r['deposit'])?>" step="any" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">收款日期</label>
                    <input type="date" class="form-control" id="dep_date" value="<?=date('Y-m-d')?>">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">收款方式</label>
                <select class="form-control" id="dep_method">
                    <option value="现金">现金</option>
                    <option value="微信支付">微信支付</option>
                    <option value="支付宝">支付宝</option>
                    <option value="银行转账">银行转账</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeModal('depositModal')">取消</button>
            <button class="btn-orange" onclick="submitDeposit()">💾 确认收押金</button>
        </div>
    </div>
</div>

<!-- 办理归还弹窗 -->
<div class="modal-overlay" id="returnModal">
    <div class="modal">
        <div class="modal-head green">
            <span class="modal-title">✅ 办理归还结算</span>
            <button class="modal-close" onclick="closeModal('returnModal')">×</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">归还日期</label>
                    <input type="date" class="form-control" id="ret_date"
                           value="<?=date('Y-m-d')?>" oninput="calcReturn()">
                </div>
                <div class="form-group">
                    <label class="form-label">设备状态</label>
                    <select class="form-control" id="ret_device_status" onchange="toggleCompensation()">
                        <option value="normal">✅ 完好归还</option>
                        <option value="damaged">⚠️ 有损坏</option>
                        <option value="lost">❌ 设备丢失</option>
                    </select>
                </div>
            </div>

            <div class="calc-preview" id="retCalcPreview">
                📊 请选择归还日期以预览结算金额
            </div>

            <div id="compensationArea" style="display:none;margin-top:16px;">
                <div class="form-group">
                    <label class="form-label">损坏/丢失描述</label>
                    <textarea class="form-control" id="ret_damage_desc" rows="2" placeholder="描述损坏情况或丢失原因"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">赔偿金额（元）<span class="hint">（市场估价参考：¥<?=number_format($total_market,2)?>）</span></label>
                    <input type="number" class="form-control" id="ret_compensation"
                           step="any" min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">赔偿方式</label>
                    <select class="form-control" id="ret_compensation_type">
                        <option value="none">暂不处理</option>
                        <?php if ($r['deposit_paid_amount'] > 0): ?>
                        <option value="deposit_deduct">从押金扣除（已收押金：¥<?=number_format($r['deposit_paid_amount'],2)?>）</option>
                        <?php endif; ?>
                        <option value="receipt">单独出收据</option>
                    </select>
                </div>
                <div id="depositDeductArea" style="display:none;">
                    <div class="form-group">
                        <label class="form-label">押金扣除金额（元）</label>
                        <input type="number" class="form-control" id="ret_deposit_deduct"
                               step="any" min="0" max="<?=$r['deposit_paid_amount']?>"
                               value="<?=$r['deposit_paid_amount']?>">
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top:16px;">
                <label class="form-label">备注</label>
                <input type="text" class="form-control" id="ret_notes" placeholder="可选">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-gray" onclick="closeModal('returnModal')">取消</button>
            <button class="btn-green" onclick="submitReturn()">✅ 确认归还</button>
        </div>
    </div>
</div>

<script>
const contractData = {
    startDate:   <?=json_encode($r['start_date']??'') ?>,
    rentPrice:   <?=floatval($r['rent_price']??0) ?>,
    rentCycle:   <?=json_encode($r['rent_cycle']??'month') ?>,
    paidRent:    <?=floatval($r['paid_rent']??0) ?>,
    deposit:     <?=floatval($r['deposit']??0) ?>,
    depositPaid: <?=floatval($r['deposit_paid_amount']??0) ?>,
    type:        <?=json_encode($r['type']??'rental') ?>,
};

function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openPaymentModal() { openModal('paymentModal'); }
function openDepositModal() { openModal('depositModal'); }
function openReturnModal()  { openModal('returnModal'); calcReturn(); }

// 计算归还结算
function calcReturn() {
    const retDate = document.getElementById('ret_date').value;
    if (!retDate) return;
    const days = Math.max(1, Math.round((new Date(retDate) - new Date(contractData.startDate)) / 86400000));
    let actualRent = 0;
    if (contractData.type === 'rental' && contractData.rentPrice > 0) {
        if (contractData.rentCycle === 'month') {
            const months = Math.max(1, Math.round(days / 30));
            actualRent = contractData.rentPrice * months;
        } else {
            actualRent = contractData.rentPrice * days;
        }
    }
    const balance = actualRent - contractData.paidRent;
    let html = `📊 实际使用 <strong>${days}</strong> 天`;
    if (contractData.type === 'rental') {
        html += `　实际租金：<strong>¥${actualRent.toFixed(2)}</strong>`;
        html += `　已收：<strong>¥${contractData.paidRent.toFixed(2)}</strong>　`;
        if (balance > 0) {
            html += `<strong style="color:#dc2626;">需补收 ¥${balance.toFixed(2)}</strong>`;
        } else if (balance < 0) {
            html += `<strong style="color:#135286;">需退款 ¥${Math.abs(balance).toFixed(2)}</strong>`;
        } else {
            html += `<strong style="color:#135286;">金额刚好</strong>`;
        }
    }
    if (contractData.depositPaid > 0) {
        html += `　押金：<strong>¥${contractData.depositPaid.toFixed(2)}</strong>（归还时退还）`;
    }
    document.getElementById('retCalcPreview').innerHTML = html;
}

function toggleCompensation() {
    const status = document.getElementById('ret_device_status').value;
    document.getElementById('compensationArea').style.display = status !== 'normal' ? 'block' : 'none';
    // 丢失时默认填入市场价
    if (status === 'lost') {
        document.getElementById('ret_compensation').value = (<?=floatval($total_market)?>).toFixed(2);
    }
}

document.getElementById('ret_compensation_type').addEventListener('change', function() {
    document.getElementById('depositDeductArea').style.display = this.value === 'deposit_deduct' ? 'block' : 'none';
});

async function submitPayment() {
    const data = new URLSearchParams({
        action: 'add_payment',
        payment_type:   document.getElementById('pay_type').value,
        amount:         document.getElementById('pay_amount').value,
        payment_date:   document.getElementById('pay_date').value,
        payment_method: document.getElementById('pay_method').value,
        period_start:   document.getElementById('pay_period_start').value,
        period_end:     document.getElementById('pay_period_end').value,
        notes:          document.getElementById('pay_notes').value,
    });
    const r = await fetch('', {method:'POST', body:data});
    const d = await r.json();
    if (d.success) location.reload();
    else sapToast('❌ 操作失败', 'error');
}

async function submitDeposit() {
    const data = new URLSearchParams({
        action: 'add_payment',
        payment_type:   'deposit',
        amount:         document.getElementById('dep_amount').value,
        payment_date:   document.getElementById('dep_date').value,
        payment_method: document.getElementById('dep_method').value,
    });
    const r = await fetch('', {method:'POST', body:data});
    const d = await r.json();
    if (d.success) location.reload();
    else sapToast('❌ 操作失败', 'error');
}

async function submitReturn() {
    const data = new URLSearchParams({
        action:              'process_return',
        return_date:         document.getElementById('ret_date').value,
        device_status:       document.getElementById('ret_device_status').value,
        damage_desc:         document.getElementById('ret_damage_desc')?.value || '',
        compensation_type:   document.getElementById('ret_compensation_type')?.value || 'none',
        compensation_amount: document.getElementById('ret_compensation')?.value || 0,
        deposit_deduct:      document.getElementById('ret_deposit_deduct')?.value || 0,
        receipt_amount:      0,
        notes:               document.getElementById('ret_notes').value,
    });
    if (!confirm('确认办理归还？合同状态将变更为"已归还"，此操作不可撤销。')) return;
    const r = await fetch('', {method:'POST', body:data});
    const d = await r.json();
    if (d.success) {
        let msg = `✅ 归还成功！\n实际使用 ${d.actual_days} 天`;
        if (contractData.type === 'rental') {
            msg += `\n实际租金：¥${parseFloat(d.actual_rent).toFixed(2)}`;
            if (d.rent_balance > 0) msg += `\n需补收租金：¥${parseFloat(d.rent_balance).toFixed(2)}`;
            else if (d.rent_balance < 0) msg += `\n需退还租金：¥${Math.abs(d.rent_balance).toFixed(2)}`;
        }
        if (d.deposit_refund > 0) msg += `\n退还押金：¥${parseFloat(d.deposit_refund).toFixed(2)}`;
        sapToast(msg, 'info');
        location.reload();
    } else {
        sapToast('❌ 操作失败', 'error');
    }
}

async function cancelContract() {
    if (!confirm('确定取消此合同吗？\n\n取消后合同状态将变更为"已取消"。')) return;
    const r = await fetch('', {method:'POST', body:new URLSearchParams({action:'cancel_contract'})});
    const d = await r.json();
    if (d.success) location.reload();
    else sapToast('❌ 操作失败', 'error');
}

async function generateContract() {
    if (!confirm('根据此租借单生成一份正式客户合同？\n\n将自动填入客户信息、设备明细（设备名/规格/数量/单价/小计）及租期付款条款。\n\n生成后可在合同管理中编辑修改。')) return;
    try {
        const resp = await fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=generate_contract'
        });
        const d = await resp.json();
        if (d.success) {
            if (confirm('✅ 客户合同已生成！\n合同编号：' + d.contract_no + '\n\n点击确定跳转到合同详情页。')) {
                window.location.href = 'contract_view.php?id=' + d.contract_id;
            }
        } else {
            sapToast('❌ 生成失败：' + (d.msg || '未知错误'), 'error');
        }
    } catch(e) {
        sapToast('❌ 网络错误：' + e.message, 'error');
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
