<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_login();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: products.php'); exit; }

$conn = getDBConnection();
function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }

$stmt = $conn->prepare("SELECT p.*, pc.name as category_name FROM products p LEFT JOIN product_categories pc ON p.category_id=pc.id WHERE p.id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$p) { header('Location: products.php'); exit; }

// 序列号
$serials = $conn->query("SELECT sn_code, status, in_date as created_at FROM product_serials WHERE product_id=$id ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

// 价格历史
$price_hist = $conn->query("SELECT old_price, new_price, changed_at FROM product_price_history WHERE product_id=$id ORDER BY changed_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);

$nickname = $_SESSION['nickname']??'';
$role     = $_SESSION['role']??'';
$conn->close();
?>
<?php $page_title = e($p['name']) . ' - 产品详情'; include __DIR__ . '/head.php'; ?>
<style>
.
.inner{max-width:960px;margin:0 auto;}
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:24px;overflow:hidden;}
.card-header{padding:16px 24px;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#1a202c;}
.card-body{padding:24px;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.info-item label{font-size:12px;color:#718096;font-weight:500;display:block;margin-bottom:4px;}
.info-item .val{font-size:14px;color:#2d3748;font-weight:500;}
.badge-active{background:#d1fae5;color:#065f46;padding:3px 10px;border-radius:10px;font-size:11px;}
.badge-inactive{background:#fee2e2;color:#991b1b;padding:3px 10px;border-radius:10px;font-size:11px;}
tr:last-child td{border-bottom:none;}
.empty{color:#a0aec0;font-size:13px;padding:16px;text-align:center;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="inner">
    <div class="page-header">
        <div class="page-title">📦 <?=e($p['name'])?></div>
        <div style="display:flex;gap:10px;">
            <a href="products.php" class="btn btn-secondary">← 返回列表</a>
            <a href="product_edit.php?id=<?=$p['id']?>" class="btn btn-primary">✏️ 编辑</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">📦 基本信息</div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item"><label>产品名称</label><div class="val"><?=e($p['name'])?></div></div>
                <div class="info-item"><label>SKU</label><div class="val"><?=e($p['sku'])??'-'?></div></div>
                <div class="info-item"><label>分类</label><div class="val"><?=e($p['category_name'])??'-'?></div></div>
                <div class="info-item"><label>单位</label><div class="val"><?=e($p['unit'])??'-'?></div></div>
                <div class="info-item"><label>售价</label><div class="val" style="color:#1f6dad;font-weight:700;">¥<?=number_format($p['default_price']??0,2)?></div></div>
                <div class="info-item"><label>成本价</label><div class="val">¥<?=number_format($p['cost_price']??0,2)?></div></div>
                <div class="info-item"><label>库存数量</label><div class="val"><?=intval($p['stock_quantity']??0)?> <?=e($p['unit'])?></div></div>
                <div class="info-item"><label>状态</label><div class="val">
                    <?php if($p['is_active']): ?>
                    <span class="badge-active">上架</span>
                    <?php else: ?>
                    <span class="badge-inactive">下架</span>
                    <?php endif; ?>
                </div></div>
                <?php if($p['spec']): ?>
                <div class="info-item" style="grid-column:1/-1;"><label>规格说明</label><div class="val"><?=e($p['spec'])?></div></div>
                <?php endif; ?>
                <?php if($p['description']): ?>
                <div class="info-item" style="grid-column:1/-1;"><label>产品描述</label><div class="val"><?=e($p['description'])?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if($serials): ?>
    <div class="card">
        <div class="card-header">🔢 序列号记录（最近20条）</div>
        <table>
            <thead><tr><th>序列号</th><th>状态</th><th>入库时间</th></tr></thead>
            <tbody>
            <?php foreach($serials as $s): ?>
            <tr>
                <td style="font-family:monospace;"><?=e($s['sn_code'])?></td>
                <td><?=e($s['status'])?></td>
                <td><?=e($s['created_at'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if($price_hist): ?>
    <div class="card">
        <div class="card-header">📈 价格变更历史</div>
        <table>
            <thead><tr><th>原价</th><th>新价</th><th>变更时间</th></tr></thead>
            <tbody>
            <?php foreach($price_hist as $h): ?>
            <tr>
                <td>¥<?=number_format($h['old_price'],2)?></td>
                <td>¥<?=number_format($h['new_price'],2)?></td>
                <td><?=e($h['changed_at'])?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

</div>
</main>
</body>
</html>
