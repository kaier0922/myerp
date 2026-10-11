<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();
require_once 'config.php';

$conn = getDBConnection();
$id   = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: rma.php'); exit; }

$res = $conn->query("
    SELECT r.*,
           s.company_name AS supplier_name,
           COALESCE(c.short_name, c.company_name) AS company_name AS customer_name,
           p.name AS product_name, p.spec AS product_spec
    FROM rma_orders r
    LEFT JOIN suppliers s ON r.supplier_id = s.id
    LEFT JOIN customers c ON r.customer_id = c.id
    LEFT JOIN products  p ON r.product_id  = p.id
    WHERE r.id = $id
");
$rma = $res ? $res->fetch_assoc() : null;
if (!$rma) { header('Location: rma.php'); exit; }
$conn->close();

function e($v){ return htmlspecialchars($v??'', ENT_QUOTES, 'UTF-8'); }

$status_map = [
    'pending'    => ['待发出',  '#a0aec0'],
    'sent'       => ['已发出',  '#3b82f6'],
    'processing' => ['处理中',  '#f59e0b'],
    'returned'   => ['已返回',  '#16a34a'],
    'replaced'   => ['已换货',  '#8b5cf6'],
    'refunded'   => ['已退款',  '#ef4444'],
    'closed'     => ['已关闭',  '#6b7280'],
];
$result_map = [
    'repaired'     => '修好返回',
    'replaced'     => '换全机',
    'refunded'     => '退款',
    'unrepairable' => '无法维修',
];
$sta     = $status_map[$rma['status']] ?? ['未知','#a0aec0'];
$sta_label = $sta[0]; $sta_color = $sta[1];
$nickname = $_SESSION['nickname']; $role = $_SESSION['role'];
?>
<?php $page_title = '返修单 ' . e($rma['rma_no']) . ' - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.content-inner{max-width:860px;margin:0 auto;}
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,0.08);margin-bottom:24px;overflow:hidden;}
.card-
.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
.field-row{display:flex;flex-direction:column;gap:4px;padding:14px 0;border-bottom:1px solid #f0f0f0;}
.field-row:last-child{border-bottom:none;}
.field-row.full{grid-column:1/-1;}
.field-label{font-size:12px;font-weight:600;color:#a0aec0;text-transform:uppercase;letter-spacing:.4px;}
.field-value{font-size:15px;color:#1a202c;font-weight:500;line-height:1.6;}
.field-value.empty{color:#a0aec0;font-style:italic;font-weight:400;}
.rma-no{font-family:monospace;font-size:22px;font-weight:700;color:#1f6dad;letter-spacing:1px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
<div class="content-inner">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
        <div>
            <h1 style="font-size:26px;font-weight:700;color:#1a202c;">📋 返修单详情</h1>
            <p style="color:#718096;font-size:14px;margin-top:4px;">查看返修单完整信息</p>
        </div>
        <div style="display:flex;gap:12px;">
            <a href="rma.php" class="btn btn-secondary">← 返回列表</a>
            <a href="rma_add.php?id=<?=$rma['id']?>" class="btn btn-primary">✏️ 编辑</a>
        </div>
    </div>

    <!-- 单号 & 状态 -->
    <div class="card">
        <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <div>
                <div style="font-size:12px;color:#a0aec0;margin-bottom:6px;">返修单号</div>
                <div class="rma-no"><?=e($rma['rma_no'])?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:12px;color:#a0aec0;margin-bottom:6px;">当前状态</div>
                <span class="badge" style="background:<?=$sta_color?>22;color:<?=$sta_color?>;"><?=$sta_label?></span>
            </div>
            <div style="text-align:right;">
                <div style="font-size:12px;color:#a0aec0;margin-bottom:4px;">创建时间</div>
                <div style="font-size:14px;color:#4a5568;"><?=e(substr($rma['created_at']??'',0,16))?></div>
                <?php if($rma['updated_at'] && $rma['updated_at'] !== $rma['created_at']): ?>
                <div style="font-size:12px;color:#a0aec0;margin-top:2px;">更新：<?=e(substr($rma['updated_at'],0,16))?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 供应商 / 客户 -->
    <div class="card">
        <div class="card-header">🏭 供应商 / 客户</div>
        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-label">供应商</div>
                    <div class="field-value <?=empty($rma['supplier_name'])?'empty':''?>"><?=e($rma['supplier_name']) ?: '未指定'?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">关联客户</div>
                    <div class="field-value <?=empty($rma['customer_name'])?'empty':''?>"><?=e($rma['customer_name']) ?: '未关联'?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 产品信息 -->
    <div class="card">
        <div class="card-header">📦 产品信息</div>
        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-label">产品名称</div>
                    <div class="field-value <?=empty($rma['product_name'])?'empty':''?>"><?=e($rma['product_name']) ?: '未选择'?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">规格型号</div>
                    <div class="field-value <?=empty($rma['product_spec'])?'empty':''?>"><?=e($rma['product_spec']) ?: '—'?></div>
                </div>
                <div class="field-row full" style="grid-column:1/-1;">
                    <div class="field-label">序列号</div>
                    <div class="field-value <?=empty($rma['serial_number'])?'empty':''?>">
                        <?php if($rma['serial_number']): ?>
                        <code style="font-family:monospace;background:#f7fafc;padding:4px 10px;border-radius:6px;font-size:14px;"><?=e($rma['serial_number'])?></code>
                        <?php else: ?>未填写<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 故障信息 -->
    <div class="card">
        <div class="card-header">🔍 故障信息</div>
        <div class="card-body">
            <div class="field-row" style="border:none;padding:0;">
                <div class="field-label">故障描述</div>
                <div class="field-value" style="margin-top:8px;background:#f7fafc;padding:14px 16px;border-radius:8px;line-height:1.8;white-space:pre-wrap;"><?=e($rma['fault_desc'])?></div>
            </div>
        </div>
    </div>

    <!-- 处理进度 -->
    <div class="card">
        <div class="card-header">📋 处理进度</div>
        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-label">发出日期</div>
                    <div class="field-value <?=empty($rma['send_date'])||$rma['send_date']==='0000-00-00'?'empty':''?>"><?=($rma['send_date']&&$rma['send_date']!=='0000-00-00') ? e($rma['send_date']) : '未填写'?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">预计返回日期</div>
                    <div class="field-value <?=empty($rma['expected_return'])||$rma['expected_return']==='0000-00-00'?'empty':''?>"><?=($rma['expected_return']&&$rma['expected_return']!=='0000-00-00') ? e($rma['expected_return']) : '未填写'?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">实际返回日期</div>
                    <div class="field-value <?=empty($rma['actual_return'])||$rma['actual_return']==='0000-00-00'?'empty':''?>"><?=($rma['actual_return']&&$rma['actual_return']!=='0000-00-00') ? e($rma['actual_return']) : '未填写'?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">处理结果</div>
                    <div class="field-value <?=empty($rma['result'])?'empty':''?>"><?=e($result_map[$rma['result']] ?? ($rma['result'] ?: '待定'))?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 费用 -->
    <div class="card">
        <div class="card-header">💰 费用</div>
        <div class="card-body">
            <div class="field-grid">
                <div class="field-row">
                    <div class="field-label">运费</div>
                    <div class="field-value">¥ <?=number_format(floatval($rma['freight_fee']),2)?></div>
                </div>
                <div class="field-row">
                    <div class="field-label">维修费</div>
                    <div class="field-value">¥ <?=number_format(floatval($rma['repair_fee']),2)?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 备注 -->
    <?php if(!empty($rma['notes'])): ?>
    <div class="card">
        <div class="card-header">📝 备注</div>
        <div class="card-body">
            <div class="field-value" style="background:#f7fafc;padding:14px 16px;border-radius:8px;line-height:1.8;white-space:pre-wrap;"><?=e($rma['notes'])?></div>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:flex;justify-content:flex-end;gap:12px;margin-bottom:32px;">
        <a href="rma.php" class="btn btn-secondary">← 返回列表</a>
        <a href="rma_add.php?id=<?=$rma['id']?>" class="btn btn-primary">✏️ 编辑此返修单</a>
    </div>

</div>
</main>
</body>
</html>
