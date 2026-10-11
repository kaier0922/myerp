<?php
/**
 * 文件名: inventory_report.php
 * 版本: 2.0
 * 功能: 库存综合分析报表（统一布局风格）
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── 1. 汇总统计 ──
$summary = ['total_types'=>0,'total_qty'=>0,'total_value'=>0,'zero_stock'=>0];
$res = $conn->query("SELECT COUNT(*) as total_types, SUM(stock_quantity) as total_qty, SUM(stock_quantity*cost_price) as total_value, SUM(stock_quantity=0) as zero_stock FROM products WHERE is_active=1 AND user_id = " . (int)getCurrentUserId() . "");
if ($res) $summary = $res->fetch_assoc();

// ── 2. 各大类库存统计 ──
$cat_stats = $conn->query("
    SELECT pc_parent.name as parent_name, COUNT(p.id) as type_count,
           SUM(p.stock_quantity) as qty, SUM(p.stock_quantity*p.cost_price) as value
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id=pc.id
    LEFT JOIN product_categories pc_parent ON pc.parent_id=pc_parent.id
    WHERE p.is_active=1 AND (p.user_id=0 OR p.user_id=" . (int)getCurrentUserId() . ")
    GROUP BY pc_parent.id, pc_parent.name
    ORDER BY value DESC
");
$cat_rows = $cat_stats ? $cat_stats->fetch_all(MYSQLI_ASSOC) : [];

// ── 3. 库存预警（低于最小库存）──
$alerts = $conn->query("
    SELECT p.name, p.sku, p.stock_quantity, p.min_stock,
           pc.name as cat_name
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id=pc.id
    WHERE p.stock_quantity <= p.min_stock AND p.is_active=1 AND (p.user_id=0 OR p.user_id=" . (int)getCurrentUserId() . ")
    AND (p.stock_quantity > 0 OR p.id IN (SELECT DISTINCT product_id FROM delivery_items WHERE product_id > 0 AND user_id=" . (int)getCurrentUserId() . ") OR p.id IN (SELECT DISTINCT ref_id FROM payable_ledger WHERE type='purchase' AND user_id=" . (int)getCurrentUserId() . "))
    ORDER BY p.stock_quantity ASC
");

// ── 4. 零库存产品 ──
$zero_stock = $conn->query("
    SELECT p.name, p.sku, pc.name as cat_name, p.cost_price
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id=pc.id
    WHERE p.stock_quantity=0 AND p.is_active=1 AND (p.user_id=0 OR p.user_id=" . (int)getCurrentUserId() . ")
    AND (p.id IN (SELECT DISTINCT product_id FROM delivery_items WHERE product_id > 0 AND user_id=" . (int)getCurrentUserId() . ") OR p.id IN (SELECT DISTINCT ref_id FROM payable_ledger WHERE type='purchase' AND user_id=" . (int)getCurrentUserId() . "))
    ORDER BY p.name ASC
    LIMIT 30
");

// ── 5. 呆滞料分析（在库超90天）──
$stagnant = false;
$check = $conn->query("SHOW TABLES LIKE 'product_serials'");
if ($check && $check->num_rows > 0) {
    $stagnant = $conn->query("
        SELECT ps.sn_code, p.name, ps.in_date, ps.purchase_price,
               DATEDIFF(NOW(), ps.in_date) as days_in_stock,
               s.company_name as supplier_name
        FROM product_serials ps
        JOIN products p ON ps.product_id=p.id
        LEFT JOIN suppliers s ON ps.supplier_id=s.id
        WHERE ps.status='in_stock'
        AND ps.in_date <= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ORDER BY days_in_stock DESC
        LIMIT 50
    ");
}

// ── 6. SN 库存按状态统计 ──
$sn_stats = ['in_stock'=>0,'sold'=>0,'repair'=>0,'returned'=>0];
$sn_res = $conn->query("SELECT status, COUNT(*) as n FROM product_serials GROUP BY status");
if ($sn_res) foreach ($sn_res->fetch_all(MYSQLI_ASSOC) as $r) $sn_stats[$r['status']] = $r['n'];

$conn->close();

// 计算总价值最大值用于进度条
$max_val = max(array_column($cat_rows, 'value') ?: [1]);
?>
<?php $page_title = '库存分析报表 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>

/* 导航栏 */

/* 侧边栏 */

/* 主内容 */
.
.btn-nav { padding:8px 16px; background:#f7fafc; border:1px solid #e2e8f0; border-radius:8px; color:#4a5568; font-size:13px; text-decoration:none; transition:all .2s; }
.btn-nav:hover { background:#edf2f7; }
.page-time { margin-left:auto; font-size:12px; color:#a0aec0; }

/* 统计卡片 */
.stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
.stat-card { background:white; border-radius:12px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,.08); border-left:4px solid var(--c,#1f6dad); }
.stat-card .sv { font-size:26px; font-weight:700; color:var(--c,#1a202c); margin-bottom:4px; }
.stat-card .sl { font-size:12px; color:#718096; }
.c-green  { --c:#1f6dad; }
.c-blue   { --c:#3b82f6; }
.c-amber  { --c:#f59e0b; }
.c-red    { --c:#ef4444; }

/* SN统计行 */
.sn-row { display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px; }
.sn-card { background:white; border-radius:10px; padding:14px 18px; box-shadow:0 1px 3px rgba(0,0,0,.08); display:flex; align-items:center; gap:12px; }
.sn-icon { font-size:24px; }
.sn-info .n { font-size:20px; font-weight:700; }
.sn-info .l { font-size:11px; color:#718096; }

/* 卡片 */
.card { background:white; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); overflow:hidden; margin-bottom:24px; }
.card-title { font-size:16px; font-weight:700; color:#1a202c; display:flex; align-items:center; gap:8px; }
.card-badge { font-size:12px; font-weight:600; padding:3px 10px; border-radius:20px; }
.badge-red    { background:#fee2e2; color:#b91c1c; }
.badge-amber  { background:#fef3c7; color:#92400e; }
.badge-green  { background:#d1fae5; color:#065f46; }
.badge-blue   { background:#dbeafe; color:#135286; }
.badge-gray   { background:#f1f5f9; color:#475569; }

/* 两列布局 */
.two-col { display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:24px; }

/* 表格 */
thead
tbody
tbody tr:last-child
tbody
.empty { text-align:center; padding:32px; color:#a0aec0; font-size:13px; }

/* 进度条 */
.progress-bar-wrap { background:#f1f5f9; border-radius:4px; height:6px; margin-top:4px; overflow:hidden; }
.progress-bar { height:100%; border-radius:4px; background:linear-gradient(90deg,#1f6dad,#135286); }

/* 库存数量色 */
.qty-red   { color:#ef4444; font-weight:700; }
.qty-amber { color:#f59e0b; font-weight:700; }
.qty-green { color:#1f6dad; font-weight:600; }
.qty-gray  { color:#a0aec0; }

/* 天数徽章 */
.days-badge { display:inline-block; padding:3px 8px; border-radius:6px; font-size:11px; font-weight:600; }
.days-180 { background:#fee2e2; color:#b91c1c; }
.days-90  { background:#fef3c7; color:#92400e; }

code { font-family:monospace; font-size:12px; background:#f7fafc; padding:2px 6px; border-radius:4px; }
</style>
</head>
<body>

<!-- 导航栏 -->
<?php include __DIR__ . '/navbar.php'; ?>

<!-- 侧边栏 -->
<?php include __DIR__ . '/sidebar.php'; ?>

<!-- 主内容 -->
<div class="main">
    <div class="page-header">
        <div class="page-title">📊 库存分析报表</div>
        <a href="stock_list.php" style="margin-left:auto;padding:9px 18px;background:linear-gradient(135deg,#1f6dad,#135286);color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:500">📋 入库记录</a>
        <a href="products.php"   class="btn-nav">← 返回产品管理</a>
        <a href="sn_tracker.php" class="btn-nav">🔍 序列号管理</a>
        <span class="page-time">报表生成时间：<?=date('Y-m-d H:i:s')?></span>
    </div>

    <!-- 汇总统计卡片 -->
    <div class="stat-row">
        <div class="stat-card c-blue">
            <div class="sv"><?=number_format($summary['total_types']??0)?> <small style="font-size:14px;font-weight:400;">种</small></div>
            <div class="sl">📦 在售产品品种</div>
        </div>
        <div class="stat-card c-green">
            <div class="sv"><?=number_format($summary['total_qty']??0)?> <small style="font-size:14px;font-weight:400;">件</small></div>
            <div class="sl">🗃️ 实物库存总数</div>
        </div>
        <div class="stat-card c-green">
            <div class="sv">¥<?=number_format($summary['total_value']??0,2)?></div>
            <div class="sl">💰 库存资产总价值（成本）</div>
        </div>
        <div class="stat-card c-red">
            <div class="sv"><?=intval($summary['zero_stock']??0)?> <small style="font-size:14px;font-weight:400;">种</small></div>
            <div class="sl">⚠️ 零库存产品数</div>
        </div>
    </div>

    <!-- SN 序列号统计 -->
    <div class="sn-row">
        <div class="sn-card">
            <div class="sn-icon">📦</div>
            <div class="sn-info">
                <div class="n" style="color:#1f6dad;"><?=number_format($sn_stats['in_stock'])?></div>
                <div class="l">序列号在库</div>
            </div>
        </div>
        <div class="sn-card">
            <div class="sn-icon">✅</div>
            <div class="sn-info">
                <div class="n" style="color:#3b82f6;"><?=number_format($sn_stats['sold'])?></div>
                <div class="l">已售出</div>
            </div>
        </div>
        <div class="sn-card">
            <div class="sn-icon">🛠️</div>
            <div class="sn-info">
                <div class="n" style="color:#f59e0b;"><?=number_format($sn_stats['repair'])?></div>
                <div class="l">返修中</div>
            </div>
        </div>
        <div class="sn-card">
            <div class="sn-icon">🔙</div>
            <div class="sn-info">
                <div class="n" style="color:#6b7280;"><?=number_format($sn_stats['returned'])?></div>
                <div class="l">已退厂</div>
            </div>
        </div>
    </div>

    <!-- 两列：大类分布 + 库存预警 -->
    <div>

        <!-- 各大类库存分布 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">📂 各大类库存分布</div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>大类</th>
                        <th>品种数</th>
                        <th>库存数量</th>
                        <th>资产价值</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($cat_rows)): ?>
                <?php foreach ($cat_rows as $row):
                    $pct = $max_val > 0 ? round($row['value']/$max_val*100) : 0;
                ?>
                <tr>
                    <td style="font-weight:600;"><?=e($row['parent_name']??'未分类')?></td>
                    <td><?=$row['type_count']?> 种</td>
                    <td><?=number_format($row['qty']??0)?></td>
                    <td>
                        <div style="font-weight:600;">¥<?=number_format($row['value']??0,2)?></div>
                        <div class="progress-bar-wrap"><div class="progress-bar" style="width:<?=$pct?>%"></div></div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr><td colspan="4" class="empty">暂无数据</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 库存预警 -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚠️ 库存预警</div>
                <span class="card-badge badge-red"><?=($alerts?$alerts->num_rows:0)?> 种需补货</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>产品名称</th>
                        <th>分类</th>
                        <th>当前库存</th>
                        <th>最低阈值</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($alerts && $alerts->num_rows > 0): ?>
                <?php while ($row = $alerts->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div style="font-weight:600;font-size:13px;"><?=e($row['name'])?></div>
                        <?php if($row['sku']): ?><div style="font-size:11px;color:#a0aec0;"><?=e($row['sku'])?></div><?php endif; ?>
                    </td>
                    <td style="font-size:12px;color:#718096;"><?=e($row['cat_name']??'-')?></td>
                    <td><span class="badge-red card-badge"><?=$row['stock_quantity']?></span></td>
                    <td style="color:#718096;"><?=$row['min_stock']?></td>
                </tr>
                <?php endwhile; ?>
                <?php else: ?>
                <tr><td colspan="4" class="empty">✅ 目前没有库存预警产品</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 零库存产品 -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🚫 零库存产品</div>
            <span class="card-badge badge-amber"><?=intval($summary['zero_stock']??0)?> 种</span>
        </div>
        <table>
            <thead>
                <tr><th>产品名称</th><th>SKU</th><th>分类</th><th>成本价</th></tr>
            </thead>
            <tbody>
            <?php if ($zero_stock && $zero_stock->num_rows > 0): ?>
            <?php while ($row = $zero_stock->fetch_assoc()): ?>
            <tr>
                <td style="font-weight:600;"><?=e($row['name'])?></td>
                <td style="font-size:12px;color:#a0aec0;"><?=e($row['sku']??'-')?></td>
                <td style="font-size:12px;color:#718096;"><?=e($row['cat_name']??'-')?></td>
                <td style="font-family:monospace;">¥<?=number_format($row['cost_price'],2)?></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr><td colspan="4" class="empty">✅ 暂无零库存产品</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 呆滞料预警 -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">🕒 呆滞料预警（在库 &gt; 90 天）</div>
            <span class="card-badge badge-amber"><?=($stagnant?$stagnant->num_rows:0)?> 条</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>序列号</th>
                    <th>产品名称</th>
                    <th>供应商</th>
                    <th>入库日期</th>
                    <th>采购价</th>
                    <th>在库时长</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($stagnant && $stagnant->num_rows > 0): ?>
            <?php while ($row = $stagnant->fetch_assoc()):
                $days = intval($row['days_in_stock']);
                $days_class = $days >= 180 ? 'days-180' : 'days-90';
            ?>
            <tr>
                <td><code><?=e($row['sn_code'])?></code></td>
                <td style="font-weight:600;"><?=e($row['name'])?></td>
                <td style="font-size:12px;color:#718096;"><?=e($row['supplier_name']??'-')?></td>
                <td style="font-size:12px;color:#718096;"><?=substr($row['in_date']??'',0,10)?></td>
                <td style="font-family:monospace;">¥<?=number_format($row['purchase_price'],2)?></td>
                <td><span class="days-badge <?=$days_class?>"><?=$days?> 天</span></td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr><td colspan="6" class="empty">✅ 暂无呆滞料记录</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</div><!-- end main -->
</body>
</html>
