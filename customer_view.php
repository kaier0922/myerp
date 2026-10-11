<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_login();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: customers.php'); exit; }

$conn = getDBConnection();

// 客户基本信息
$stmt = $conn->prepare("SELECT * FROM customers WHERE id=? AND is_active=1 AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("i", $id);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$c) { header('Location: customers.php'); exit; }

// 关联的报价单
$quotes = $conn->query("SELECT id, quote_no, quote_date, final_amount, status FROM quotes WHERE customer_id=$id ORDER BY quote_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// 关联的合同
$contracts = $conn->query("SELECT id, contract_no, contract_date, total_amount, status FROM sales_contracts WHERE customer_id=$id ORDER BY contract_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// 关联的送货单
$deliveries = $conn->query("SELECT id, delivery_no, delivery_date, total_amount, status FROM deliveries WHERE customer_id=$id ORDER BY delivery_date DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

// 应收账款汇总
$ar_summary = $conn->query("
    SELECT
        COUNT(*) as total_count,
        SUM(total_amount) as total_amount,
        SUM(paid_amount) as total_paid,
        SUM(outstanding_amount) as total_outstanding
    FROM accounts_receivable
    WHERE customer_id=$id
")->fetch_assoc();

// 应收账款明细（未收完的）
$ar_list = $conn->query("
    SELECT id, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status
    FROM accounts_receivable
    WHERE customer_id=$id AND status != '已收款'
    ORDER BY bill_date DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$nickname = $_SESSION['nickname'] ?? '';
$role     = $_SESSION['role'] ?? '';
$conn->close();

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<?php $page_title = e($c['company_name']) . ' - 客户详情'; include __DIR__ . '/head.php'; ?>
<style>
.inner{max-width:1000px;margin:0 auto;}
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:24px;overflow:hidden;}
.card-header{padding:16px 24px;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#1a202c;display:flex;align-items:center;gap:8px;}
.card-body{padding:24px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.info-item label{font-size:12px;color:#718096;font-weight:500;display:block;margin-bottom:4px;}
.info-item .val{font-size:14px;color:#2d3748;}
.table{width:100%;border-collapse:collapse;font-size:13px;}
.table th{background:#f7fafc;padding:10px 12px;text-align:left;font-size:12px;font-weight:600;color:#718096;border-bottom:1px solid #e2e8f0;}
.table td{padding:10px 12px;border-bottom:1px solid #f0f0f0;color:#2d3748;}
.table tr:last-child td{border-bottom:none;}
.table a{color:#1f6dad;text-decoration:none;}
.badge-draft{background:#e2e8f0;color:#4a5568;}
.badge-confirmed{background:#dbeafe;color:#1f6dad;}
.badge-deal{background:#d1fae5;color:#065f46;}
.badge-expired{background:#fee2e2;color:#991b1b;}
.empty{color:#a0aec0;font-size:13px;padding:16px 0;text-align:center;}
.summary-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.summary-card{background:white;border-radius:12px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.summary-card .label{font-size:12px;color:#718096;margin-bottom:6px;}
.summary-card .value{font-size:22px;font-weight:700;}
.value-red{color:#e53e3e;}
.value-blue{color:#1f6dad;}
.value-green{color:#059669;}
.badge-unpaid{background:#fee2e2;color:#991b1b;}
.badge-partial{background:#fef3c7;color:#92400e;}
.badge-paid{background:#d1fae5;color:#065f46;}
.badge-overdue{background:#fed7d7;color:#c53030;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="inner">
    <div class="page-header">
        <div class="page-title">🏢 <?=e($c['short_name'] ?: $c['company_name'])?></div>
        <div style="display:flex;gap:10px;">
            <a href="customers.php" class="btn btn-secondary">← 返回列表</a>
            <a href="customer_edit.php?id=<?=$c['id']?>" class="btn btn-primary">✏️ 编辑</a>
        </div>
    </div>

    <!-- 基本信息 -->
    <div class="card">
        <div class="card-header">👤 基本信息</div>
        <div class="card-body">
            <div class="info-grid">
                <?php if(!empty($c['short_name'])): ?>
                <div class="info-item"><label>客户简称</label><div class="val"><?=e($c['short_name'])?></div></div>
                <?php endif; ?>
                <div class="info-item"><label>公司全称</label><div class="val"><?=e($c['company_name'])?></div></div>
                <div class="info-item"><label>联系人</label><div class="val"><?=e($c['contact_name']) ?: '-'?></div></div>
                <div class="info-item"><label>联系电话</label><div class="val"><?=e($c['phone']) ?: '-'?></div></div>
                <div class="info-item"><label>邮箱</label><div class="val"><?=e($c['email']) ?: '-'?></div></div>
                <div class="info-item"><label>税号</label><div class="val"><?=e($c['tax_number']) ?: '-'?></div></div>
                <div class="info-item"><label>地址</label><div class="val"><?=e($c['address']) ?: '-'?></div></div>
                <div class="info-item"><label>创建时间</label><div class="val"><?=e($c['created_at'])?></div></div>
                <?php if($c['notes']): ?>
                <div class="info-item" style="grid-column:1/-1;"><label>备注</label><div class="val"><?=e($c['notes'])?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 报价单 -->
    <div class="card">
        <div class="card-header">📋 报价记录（最近10条）</div>
        <div class="card-body" style="padding:0;">
            <?php if($quotes): ?>
            <table class="table">
                <thead><tr><th>报价单号</th><th>日期</th><th>金额</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach($quotes as $q):
                    $sc = ['草稿'=>'draft','已确认'=>'confirmed','已成交'=>'deal','已过期'=>'expired'][$q['status']] ?? 'draft';
                ?>
                <tr>
                    <td><a href="quote_view.php?id=<?=$q['id']?>"><?=e($q['quote_no'])?></a></td>
                    <td><?=e($q['quote_date'])?></td>
                    <td>¥<?=number_format($q['final_amount'],2)?></td>
                    <td><span class="badge badge-<?=$sc?>"><?=e($q['status'])?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty">暂无报价记录</div><?php endif; ?>
        </div>
    </div>

    <!-- 合同 -->
    <div class="card">
        <div class="card-header">📝 合同记录（最近10条）</div>
        <div class="card-body" style="padding:0;">
            <?php if($contracts): ?>
            <table class="table">
                <thead><tr><th>合同编号</th><th>日期</th><th>金额</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach($contracts as $ct): ?>
                <tr>
                    <td><a href="contract_view.php?id=<?=$ct['id']?>"><?=e($ct['contract_no'])?></a></td>
                    <td><?=e($ct['contract_date'])?></td>
                    <td>¥<?=number_format($ct['total_amount'],2)?></td>
                    <td><span class="badge badge-draft"><?=e($ct['status'])?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty">暂无合同记录</div><?php endif; ?>
        </div>
    </div>

    <!-- 送货单 -->
    <div class="card">
        <div class="card-header">🚚 送货记录（最近10条）</div>
        <div class="card-body" style="padding:0;">
            <?php if($deliveries): ?>
            <table class="table">
                <thead><tr><th>送货单号</th><th>日期</th><th>金额</th><th>状态</th></tr></thead>
                <tbody>
                <?php
                $dlabels = ['pending'=>'待送货','delivering'=>'配送中','completed'=>'已完成','failed'=>'失败','cancelled'=>'已取消'];
                foreach($deliveries as $d): ?>
                <tr>
                    <td><a href="delivery_view.php?id=<?=$d['id']?>"><?=e($d['delivery_no'])?></a></td>
                    <td><?=e($d['delivery_date'])?></td>
                    <td>¥<?=number_format($d['total_amount'],2)?></td>
                    <td><span class="badge badge-draft"><?=$dlabels[$d['status']]??$d['status']?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty">暂无送货记录</div><?php endif; ?>
        </div>
    </div>

    <!-- 应收账款汇总 -->
    <div class="summary-cards">
        <div class="summary-card">
            <div class="label">📋 应收总额</div>
            <div class="value value-blue">¥<?=number_format($ar_summary['total_amount']??0,2)?></div>
        </div>
        <div class="summary-card">
            <div class="label">✅ 已收款</div>
            <div class="value value-green">¥<?=number_format($ar_summary['total_paid']??0,2)?></div>
        </div>
        <div class="summary-card">
            <div class="label">⚠️ 未收款</div>
            <div class="value value-red">¥<?=number_format($ar_summary['total_outstanding']??0,2)?></div>
        </div>
    </div>

    <!-- 未收款明细 -->
    <div class="card">
        <div class="card-header">💰 未收款明细</div>
        <div class="card-body" style="padding:0;">
            <?php if($ar_list): ?>
            <table class="table">
                <thead><tr><th>账单号</th><th>账单日期</th><th>应收金额</th><th>已收</th><th>未收</th><th>到期日</th><th>状态</th></tr></thead>
                <tbody>
                <?php foreach($ar_list as $ar):
                    $ar_status_class = ['未收款'=>'unpaid','部分收款'=>'partial','已收款'=>'paid','已逾期'=>'overdue'][$ar['status']] ?? 'unpaid';
                ?>
                <tr>
                    <td><?=e($ar['bill_no'])?></td>
                    <td><?=e($ar['bill_date'])?></td>
                    <td>¥<?=number_format($ar['total_amount'],2)?></td>
                    <td>¥<?=number_format($ar['paid_amount'],2)?></td>
                    <td style="font-weight:600;color:#e53e3e;">¥<?=number_format($ar['outstanding_amount'],2)?></td>
                    <td><?=$ar['due_date'] ? e($ar['due_date']) : '-'?></td>
                    <td><span class="badge badge-<?=$ar_status_class?>"><?=e($ar['status'])?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?><div class="empty">🎉 该客户无未收款记录</div><?php endif; ?>
        </div>
    </div>

</div>
</main>
</body>
</html>
