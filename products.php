<?php
/**
 * 文件名: products.php
 * 版本: 5.0 - 分类全动态读取，以数据库category树为唯一标准
 *
 * 设计原则：
 * - 不再硬编码任何分类ID或名称
 * - 顶级大类(parent_id=0) → 每个大类的子类动态读取
 * - product_type 字段只用于统计，不用于分类过滤
 * - 过滤完全基于 category_id
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$conn     = getDBConnection();
$uid      = intval($_SESSION['user_id']);
$is_super = in_array($_SESSION['role'] ?? '', ['super_admin', 'admin']);

// ── 参数
$cat_filter = isset($_GET['cat']) ? intval($_GET['cat']) : 0;  // 选中的 category_id（顶级或子级）
$search     = trim($_GET['search'] ?? '');
$page       = max(1, intval($_GET['page'] ?? 1));
$page_size  = intval($_GET['page_size'] ?? 100);
$offset     = ($page - 1) * $page_size;

// ── 读取完整分类树（一次查询）
// top: 顶级大类, subs[parent_id]: 子类列表
$all_cats  = [];
$top_cats  = [];
$subs      = [];
$cat_names = [];  // id => name 快查表

$cat_res = $conn->query("SELECT id, parent_id, name, sort_order FROM product_categories ORDER BY parent_id, sort_order, id");
while ($c = $cat_res->fetch_assoc()) {
    $all_cats[$c['id']] = $c;
    $cat_names[$c['id']] = $c['name'];
    if ($c['parent_id'] == 0) {
        $top_cats[] = $c;
    } else {
        $subs[$c['parent_id']][] = $c;
    }
}

// 判断选中的是顶级还是子级，决定过滤范围
// 如果选中的是顶级(parent_id=0)，过滤该顶级下所有子类的产品
// 如果选中的是子级，直接过滤该子类
function getCatFilterIds($cat_id, $all_cats, $subs) {
    if ($cat_id == 0) return [];  // 全部
    if ($all_cats[$cat_id]['parent_id'] == 0) {
        // 顶级：返回所有子类ID + 自身
        $ids = [$cat_id];
        if (isset($subs[$cat_id])) {
            foreach ($subs[$cat_id] as $sub) $ids[] = $sub['id'];
        }
        return $ids;
    }
    return [$cat_id];  // 子级：直接用
}
$filter_ids = getCatFilterIds($cat_filter, $all_cats, $subs);

// 当前选中的顶级大类（用于高亮）
$active_top = 0;
if ($cat_filter > 0) {
    $c = $all_cats[$cat_filter];
    $active_top = ($c['parent_id'] == 0) ? $cat_filter : $c['parent_id'];
}

// ── 统计（全库）
$stats = $conn->query("
    SELECT COUNT(*) AS total,
        SUM(c.parent_id=1) AS hardware,
        SUM(c.parent_id=2) AS device,
        SUM(c.parent_id IN (6,7)) AS software,
        SUM(stock_quantity <= min_stock AND c.parent_id NOT IN (6,7)) AS low_stock
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.id
    WHERE 1=1" . "
")->fetch_assoc();

// ── 查询条件
$where  = ["1=1"];
$params = [];
$types  = '';

if (!empty($filter_ids)) {
    $placeholders = implode(',', array_fill(0, count($filter_ids), '?'));
    $where[]  = "p.category_id IN ($placeholders)";
    foreach ($filter_ids as $fid) { $params[] = $fid; $types .= 'i'; }
}
if ($search !== '') {
    $where[]  = "(p.name LIKE ? OR p.sku LIKE ? OR p.spec LIKE ?)";
    $st = "%$search%";
    $params[] = $st; $params[] = $st; $params[] = $st;
    $types   .= 'sss';
}
$where_sql = implode(' AND ', $where);

// 总数
$count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM products p WHERE $where_sql");
if ($params) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['cnt'];
$total_pages   = max(1, ceil($total_records / $page_size));
$count_stmt->close();

// 列表
$stmt = $conn->prepare("
    SELECT p.*, pc.name AS category_name, pp.name AS parent_cat_name
    FROM products p
    LEFT JOIN product_categories pc  ON p.category_id = pc.id
    LEFT JOIN product_categories pp  ON pc.parent_id   = pp.id
    WHERE $where_sql
    ORDER BY p.category_id, p.name
    LIMIT ? OFFSET ?
");
$fp = array_merge($params, [$page_size, $offset]);
$stmt->bind_param($types.'ii', ...$fp);
$stmt->execute();
$products = $stmt->get_result();

function buildUrl($extra = []) {
    $base = array_filter([
        'cat'       => $_GET['cat']       ?? '',
        'search'    => $_GET['search']    ?? '',
        'page_size' => $_GET['page_size'] ?? '',
    ]);
    $merged = array_filter(array_merge($base, $extra), fn($v) => $v !== '' && $v !== 0);
    return 'products.php?' . http_build_query($merged);
}

$conn->close();
?>
<?php $page_title = '产品管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.header-actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.btn-tool{padding:8px 16px;background:white;color:#4a5568;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-tool:hover{background:#f7fafc;border-color:#1f6dad;color:#1f6dad;}
.btn-primary-action{padding:10px 20px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;}
.btn-primary-action:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;margin-bottom:20px;}
.stat-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.stat-card-label{font-size:13px;color:#718096;margin-bottom:8px;}
.stat-card-value{font-size:26px;font-weight:700;color:#1a202c;}
.stat-card-value.warn{color:#ed8936;}

.cat-nav{background:white;border-radius:12px;padding:14px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:16px;display:flex;align-items:flex-start;gap:0;flex-wrap:wrap;}
.cat-top-group{display:flex;align-items:center;gap:0;margin-right:4px;margin-bottom:4px;}
.cat-top-btn{padding:6px 14px;border-radius:20px 0 0 20px;font-size:14px;font-weight:500;border:1px solid #e2e8f0;border-right:none;background:white;color:#4a5568;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:5px;white-space:nowrap;transition:all .2s;}
.cat-top-btn:hover{background:#f0f0ff;color:#1f6dad;border-color:#1f6dad;}
.cat-top-btn.active{background:linear-gradient(135deg,#1f6dad,#135286);color:white;border-color:transparent;}
.cat-top-btn.no-sub{border-radius:20px;border-right:1px solid #e2e8f0;}
.cat-top-btn.no-sub.active{border-color:transparent;}
.cat-sub-wrap{display:flex;flex-wrap:wrap;gap:0;}
.cat-sub-btn{padding:6px 12px;font-size:13px;border:1px solid #e2e8f0;border-left:none;background:white;color:#4a5568;cursor:pointer;text-decoration:none;white-space:nowrap;transition:all .2s;}
.cat-sub-btn:hover{background:#f0f0ff;color:#1f6dad;}
.cat-sub-btn.active{background:#eef2ff;color:#1f6dad;font-weight:600;}
.cat-sub-btn:last-child{border-radius:0 20px 20px 0;}
.cat-divider{width:1px;background:#e2e8f0;margin:0 8px;align-self:stretch;}
/* 搜索行 */
.filter-row{display:flex;align-items:center;gap:10px;background:white;border-radius:12px;padding:12px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:16px;}
.filter-row input{width:220px;padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;}
.filter-row input:focus{outline:none;border-color:#1f6dad;}
.btn-search{padding:8px 18px;background:#1f6dad;color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;}
.pagination-info{background:white;border-radius:12px;padding:10px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;font-size:14px;color:#4a5568;}
.page-size-select{padding:5px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;cursor:pointer;}
thead{background:#f7fafc;}
tbody
tbody tr:last-child
.product-name{font-weight:600;color:#1a202c;margin-bottom:3px;}
.product-spec{font-size:12px;color:#718096;line-height:1.4;}
.cat-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:500;background:#eef2ff;color:#4338ca;}
.price-cell{text-align:right;}
.price-
.stock-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:12px;font-weight:500;}
.stock-good{background:#d1fae5;color:#065f46;}
.stock-low{background:#fef3c7;color:#92400e;}
.stock-out{background:#fee2e2;color:#991b1b;}
code{background:#f1f5f9;padding:2px 6px;border-radius:4px;color:#475569;font-family:monospace;font-size:12px;}
.btn-sm-delete{padding:5px 10px;background:#fff5f5;color:#c53030;border:1px solid #feb2b2;border-radius:6px;font-size:13px;cursor:pointer;}
.btn-sm-delete:hover{background:#fed7d7;}
.btn-group{display:flex;gap:6px;justify-content:center;}
.batch-actions{display:none;padding:12px 20px;background:#eef2ff;border-top:1px solid #c7d2fe;align-items:center;gap:16px;}
.batch-actions.active{display:flex;}
.batch-count{font-size:14px;color:#4338ca;font-weight:500;}
.btn-batch-delete{padding:6px 14px;background:#ef4444;color:white;border:none;border-radius:6px;font-size:13px;cursor:pointer;}
.btn-cancel-select{padding:6px 14px;background:#f7fafc;color:#4a5568;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;cursor:pointer;}
.pagination{display:flex;justify-content:center;align-items:center;gap:6px;padding:20px;}
.pagination a,.pagination span{padding:7px 12px;border:1px solid #e2e8f0;border-radius:6px;text-decoration:none;color:#4a5568;font-size:14px;}
.pagination a:hover{background:#f7fafc;border-color:#1f6dad;color:#1f6dad;}
.pagination span.cur{background:linear-gradient(135deg,#1f6dad,#135286);color:white;border-color:transparent;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">📦 产品管理</h1>
        <div class="header-actions">
            <a href="sn_import.php"        class="btn-tool">📥 采购入库</a>
            <a href="sn_checkout.php"      class="btn-tool">📤 销售出库</a>
            <a href="rentals.php"          class="btn-tool">📋 租借管理</a>
            <a href="rma.php"              class="btn-tool">🔧 返修管理</a>
            <a href="sn_tracker.php"       class="btn-tool">🔍 序列号溯源</a>
            <a href="inventory_report.php" class="btn-tool">📊 库存管理</a>
            <a href="product_import.php"   class="btn-tool">📥 导入产品</a>
            <a href="product_export.php"   class="btn-tool">📤 导出产品</a>
        </div>
    </div>

    <!-- 统计卡片 -->
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-card-label">产品总数</div><div class="stat-card-value"><?php echo $stats['total']; ?></div></div>
        <div class="stat-card"><div class="stat-card-label">硬件配件</div><div class="stat-card-value"><?php echo $stats['hardware']??0; ?></div></div>
        <div class="stat-card"><div class="stat-card-label">整机设备</div><div class="stat-card-value"><?php echo $stats['device']??0; ?></div></div>
        <div class="stat-card"><div class="stat-card-label">软件/服务</div><div class="stat-card-value"><?php echo ($stats['software']??0)+($stats['service']??0); ?></div></div>
        <div class="stat-card"><div class="stat-card-label">库存预警</div><div class="stat-card-value warn"><?php echo $stats['low_stock']??0; ?></div></div>
    </div>

    <!-- 分类导航：全动态，新增分类自动出现 -->
    <div class="cat-nav">
        <!-- 全部 -->
        <div class="cat-top-group">
            <a href="<?php echo buildUrl(['cat'=>'','page'=>1]); ?>"
               class="cat-top-btn no-sub <?php echo $cat_filter===0?'active':''; ?>">全部</a>
        </div>
        <div class="cat-divider"></div>

        <?php foreach ($top_cats as $ti => $top):
            $top_id   = (int)$top['id'];
            $top_subs = $subs[$top_id] ?? [];
            $top_active = ($active_top === $top_id);
        ?>
        <div class="cat-top-group">
            <!-- 顶级大类按钮（点击显示全部该类产品）-->
            <a href="<?php echo buildUrl(['cat'=>$top_id,'page'=>1]); ?>"
               class="cat-top-btn <?php echo $top_active&&$cat_filter===$top_id?'active':''; ?> <?php echo empty($top_subs)?'no-sub':''; ?>">
                <?php echo htmlspecialchars($top['name']); ?>
            </a>
            <?php if (!empty($top_subs)): ?>
            <!-- 子类按钮 -->
            <div class="cat-sub-wrap">
                <?php foreach ($top_subs as $sub):
                    $sub_id = (int)$sub['id'];
                ?>
                <a href="<?php echo buildUrl(['cat'=>$sub_id,'page'=>1]); ?>"
                   class="cat-sub-btn <?php echo $cat_filter===$sub_id?'active':''; ?>">
                    <?php echo htmlspecialchars($sub['name']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($ti < count($top_cats)-1): ?><div class="cat-divider"></div><?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- 搜索 -->
    <form class="filter-row" method="GET" action="products.php">
        <?php if ($cat_filter): ?><input type="hidden" name="cat" value="<?php echo $cat_filter; ?>"><?php endif; ?>
        <input type="hidden" name="page_size" value="<?php echo $page_size; ?>">
        <input type="text" name="search" placeholder="🔍 搜索产品名称、SKU、规格..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit" class="btn-search">搜索</button>
        <?php if ($search || $cat_filter): ?>
        <a href="products.php" style="padding:8px 14px;color:#4a5568;text-decoration:none;font-size:14px;">✕ 清除</a>
        <?php endif; ?>
        <a href="product_add.php"      class="btn-primary-action">+ 添加新产品</a>
    </form>

    <!-- 分页信息 -->
    <div class="pagination-info">
        <span>
            <?php if ($cat_filter && isset($all_cats[$cat_filter])): ?>
            <strong><?php echo htmlspecialchars($all_cats[$cat_filter]['name']); ?></strong> ·
            <?php endif; ?>
            共 <?php echo $total_records; ?> 条，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页
        </span>
        <form method="GET" action="products.php" style="display:flex;align-items:center;gap:8px;">
            <?php if ($cat_filter): ?><input type="hidden" name="cat"    value="<?php echo $cat_filter; ?>"><?php endif; ?>
            <?php if ($search):     ?><input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>"><?php endif; ?>
            <input type="hidden" name="page" value="1">
            <label style="font-size:13px;">每页</label>
            <select name="page_size" class="page-size-select" onchange="this.form.submit()">
                <?php foreach ([50,100,200,500] as $ps): ?>
                <option value="<?php echo $ps; ?>" <?php echo $page_size==$ps?'selected':''; ?>><?php echo $ps; ?> 条</option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- 产品列表 -->
    <div class="content-card">
        <?php if ($products->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th width="36"><input type="checkbox" id="selectAll" onclick="selectAll(this)" style="cursor:pointer;"></th>
                    <th width="35%">产品信息</th>
                    <th>分类</th>
                    <th>SKU</th>
                    <th>库存</th>
                    <th style="text-align:right;">单价</th>
                    <th style="text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($p = $products->fetch_assoc()): ?>
            <tr>
                <td><input type="checkbox" class="product-checkbox" value="<?php echo $p['id']; ?>" onchange="updateBatchActions()" style="cursor:pointer;"></td>
                <td>
                    <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="product-spec"><?php echo htmlspecialchars($p['spec']?:''); ?></div>
                </td>
                <td>
                    <?php
                    // 显示 "顶级 · 子类" 或仅子类名
                    $cat_display = htmlspecialchars($p['category_name'] ?? '-');
                    if (!empty($p['parent_cat_name'])) {
                        $cat_display = htmlspecialchars($p['parent_cat_name']).' · '.$cat_display;
                    }
                    ?>
                    <span class="cat-badge"><?php echo $cat_display; ?></span>
                </td>
                <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                <td>
                    <?php
                    $s=$p['stock_quantity']; $m=$p['min_stock'];
                    $cls=$s<=0?'stock-out':($s<=$m?'stock-low':'stock-good');
                    $txt=$s<=0?'缺货':$s.' '.$p['unit'];
                    ?>
                    <span class="stock-badge <?php echo $cls; ?>" 
                        onclick="adjustStock(<?php echo $p['id']; ?>,'<?php echo htmlspecialchars($p['name'],ENT_QUOTES); ?>',<?php echo $s; ?>,'<?php echo htmlspecialchars($p['unit']); ?>')"
                        style="cursor:pointer;title='点击调整库存'"
                        title="点击调整库存"><?php echo htmlspecialchars($txt); ?></span>
                </td>
                <td class="price-cell"><div class="price-main">¥<?php echo number_format($p['default_price'],2); ?></div></td>
                <td align="center">
                    <div class="btn-group">
                        <a href="product_view.php?id=<?php echo $p['id']; ?>" class="btn-sm">👁️ 查看</a>
                        <?php if ($is_super || $p['user_id'] == $uid): ?>
                        <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn-sm">✏️ 编辑</a>
                        <button class="btn-sm-delete" onclick="deleteProduct(<?php echo $p['id']; ?>,'<?php echo htmlspecialchars($p['name'],ENT_QUOTES); ?>')">🗑️ 删除</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <div class="batch-actions" id="batchActions">
            <span class="batch-count">已选择 <strong id="selectedCount">0</strong> 项</span>
            <button onclick="exportSelectedProducts()" style="padding:6px 14px;background:#1f6dad;color:white;border:none;border-radius:6px;cursor:pointer;font-size:13px;">📤 导出选中</button>
            <button class="btn-batch-delete" onclick="batchDelete()">🗑️ 批量删除</button>
            <button class="btn-cancel-select" onclick="clearSelection()">取消选择</button>
        </div>
        <?php else: ?>
        <div class="empty-state"><div class="empty-state-icon">📦</div><div>暂无符合条件的产品</div></div>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page>1): ?><a href="<?php echo buildUrl(['page'=>$page-1]); ?>">← 上一页</a><?php endif; ?>
        <?php
        $sp=max(1,$page-3);$ep=min($total_pages,$page+3);
        if($sp>1){echo '<a href="'.buildUrl(['page'=>1]).'">1</a>';if($sp>2)echo '<span>…</span>';}
        for($i=$sp;$i<=$ep;$i++){if($i==$page)echo "<span class=\"cur\">$i</span>";else echo '<a href="'.buildUrl(['page'=>$i]).'">'.$i.'</a>';}
        if($ep<$total_pages){if($ep<$total_pages-1)echo '<span>…</span>';echo '<a href="'.buildUrl(['page'=>$total_pages]).'">'.$total_pages.'</a>';}
        ?>
        <?php if ($page<$total_pages): ?><a href="<?php echo buildUrl(['page'=>$page+1]); ?>">下一页 →</a><?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<!-- 导入弹窗 -->
<div id="productImportOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:400;" onclick="if(event.target===this)closeProductImportModal()"></div>
<div id="productImportModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);width:520px;max-width:95vw;background:white;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.2);z-index:401;overflow:hidden;">
    <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:20px 24px;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:16px;font-weight:600;color:white;">📥 导入产品</span>
        <button onclick="closeProductImportModal()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:28px;height:28px;border-radius:6px;font-size:16px;cursor:pointer;">×</button>
    </div>
    <div style="padding:24px;">
        <div style="background:#f0f4ff;border-radius:8px;padding:14px;margin-bottom:20px;font-size:14px;color:#3730a3;line-height:1.6;">
            <strong>导入说明：</strong><br>• 仅支持 CSV 格式（UTF-8 编码）<br>• 必填：产品名称、单价<br>• SKU 重复将被跳过
        </div>
        <div style="margin-bottom:20px;">
            <label style="display:block;font-size:14px;font-weight:600;color:#4a5568;margin-bottom:6px;">选择 CSV 文件</label>
            <input type="file" id="productImportFile" accept=".csv" style="width:100%;padding:8px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;">
        </div>
        <div id="productImportResult" style="display:none;padding:12px;border-radius:8px;margin-bottom:16px;font-size:14px;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
            <button onclick="closeProductImportModal()" class="btn-cancel-select">取消</button>
            <button onclick="doProductImport()" id="productImportBtn" style="padding:9px 20px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;">开始导入</button>
        </div>
    </div>
</div>

<script>
function selectAll(cb){document.querySelectorAll('.product-checkbox').forEach(c=>c.checked=cb.checked);updateBatchActions();}
function updateBatchActions(){const n=document.querySelectorAll('.product-checkbox:checked').length;document.getElementById('selectedCount').textContent=n;document.getElementById('batchActions').classList.toggle('active',n>0);const sa=document.getElementById('selectAll');if(sa)sa.checked=n>0&&n===document.querySelectorAll('.product-checkbox').length;}
function clearSelection(){document.querySelectorAll('.product-checkbox').forEach(c=>c.checked=false);document.getElementById('selectAll').checked=false;updateBatchActions();}
function deleteProduct(id,name){if(!confirm('确定删除「'+name+'」？'))return;const btn=event.target;btn.disabled=true;btn.textContent='⏳';fetch('product_delete.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+id}).then(r=>r.json()).then(d=>{if(d.success)location.reload();else{sapToast('删除失败：'+d.message, 'error');btn.disabled=false;btn.textContent='🗑️ 删除';}}).catch(e=>{sapToast('出错：'+e.message, 'error');btn.disabled=false;btn.textContent='🗑️ 删除';});}
function batchDelete(){const checked=document.querySelectorAll('.product-checkbox:checked');if(!checked.length)return;if(!confirm('确定删除选中的'+checked.length+'个产品？'))return;const ids=Array.from(checked).map(c=>c.value);let done=0,fail=0;const next=idx=>{if(idx>=ids.length){sapToast('完成：成功'+done+(fail?'，失败'+fail:'', 'success'));location.reload();return;}fetch('product_delete.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'id='+ids[idx]}).then(r=>r.json()).then(d=>{d.success?done++:fail++;next(idx+1);}).catch(()=>{fail++;next(idx+1);});};next(0);}
function exportSelectedProducts(){const checked=document.querySelectorAll('.product-checkbox:checked');if(!checked.length){sapToast('请先勾选产品', 'warning');return;}const rows=[['产品名称','规格','SKU','分类','单价','库存']];checked.forEach(cb=>{const tr=cb.closest('tr');const cells=tr.querySelectorAll('td');rows.push([cells[1]?.querySelector('.product-name')?.textContent.trim()??'',cells[1]?.querySelector('.product-spec')?.textContent.trim()??'',cells[3]?.textContent.trim()??'',cells[2]?.textContent.trim()??'',cells[5]?.textContent.trim().replace('¥','')??'',cells[4]?.textContent.trim()??'']);});const csv='\uFEFF'+rows.map(r=>r.map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');const a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'}));a.download='产品列表_'+new Date().toISOString().slice(0,10)+'.csv';a.click();}
function openProductImportModal(){document.getElementById('productImportOverlay').style.display='block';document.getElementById('productImportModal').style.display='block';document.getElementById('productImportResult').style.display='none';document.getElementById('productImportFile').value='';}
function closeProductImportModal(){document.getElementById('productImportOverlay').style.display='none';document.getElementById('productImportModal').style.display='none';}
async function doProductImport(){const file=document.getElementById('productImportFile').files[0];if(!file){sapToast('请选择CSV文件', 'warning');return;}const btn=document.getElementById('productImportBtn');btn.disabled=true;btn.textContent='⏳ 导入中...';const fd=new FormData();fd.append('csv_file',file);try{const r=await fetch('product_import.php',{method:'POST',body:fd});const d=await r.json();const box=document.getElementById('productImportResult');box.style.display='block';if(d.success){box.style.background='#d1fae5';box.style.color='#065f46';box.innerHTML=`✅ 成功 <strong>${d.imported}</strong> 条，跳过 <strong>${d.skipped}</strong> 条`;setTimeout(()=>{closeProductImportModal();location.reload();},1500);}else{box.style.background='#fee2e2';box.style.color='#991b1b';box.innerHTML='❌ '+(d.message||'导入失败');}}catch(e){sapToast('出错：'+e.message, 'error');}btn.disabled=false;btn.textContent='开始导入';}
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

<!-- 库存调整弹窗 -->
<div id="stockModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:12px;padding:32px;width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.3)">
        <h3 style="margin:0 0 8px;color:#1a202c">调整库存</h3>
        <p id="stockModalName" style="color:#718096;font-size:14px;margin:0 0 24px"></p>
        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:500;color:#4a5568;display:block;margin-bottom:6px">调整方式</label>
            <select id="stockAdjType" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px" onchange="updateStockPreview()">
                <option value="set">设为指定数量</option>
                <option value="add">增加数量（入库）</option>
                <option value="sub">减少数量（出库/损耗）</option>
            </select>
        </div>
        <div style="margin-bottom:16px">
            <label style="font-size:13px;font-weight:500;color:#4a5568;display:block;margin-bottom:6px">数量 <span id="stockUnit"></span></label>
            <input type="number" id="stockQty" min="0" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;box-sizing:border-box" oninput="updateStockPreview()">
            <p id="stockPreview" style="margin:6px 0 0;font-size:13px;color:#1f6dad"></p>
        </div>
        <div style="margin-bottom:24px">
            <label style="font-size:13px;font-weight:500;color:#4a5568;display:block;margin-bottom:6px">原因备注</label>
            <input type="text" id="stockRemark" placeholder="如：盘点调整、损耗、退货..." style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:14px;box-sizing:border-box">
        </div>
        <div style="display:flex;gap:12px;justify-content:flex-end">
            <button onclick="closeStockModal()" style="padding:9px 20px;border:1px solid #e2e8f0;border-radius:6px;background:#f7fafc;color:#4a5568;cursor:pointer;font-size:14px">取消</button>
            <button onclick="submitStockAdj()" style="padding:9px 20px;border:none;border-radius:6px;background:linear-gradient(135deg,#1f6dad,#135286);color:#fff;cursor:pointer;font-size:14px;font-weight:500">确认调整</button>
        </div>
    </div>
</div>
<script>
let _adjPid=0, _adjCur=0;
function adjustStock(pid, name, cur, unit) {
    _adjPid=pid; _adjCur=cur;
    document.getElementById('stockModalName').textContent = name + '  当前库存：' + cur + ' ' + unit;
    document.getElementById('stockUnit').textContent = unit;
    document.getElementById('stockQty').value = '';
    document.getElementById('stockRemark').value = '';
    document.getElementById('stockAdjType').value = 'set';
    document.getElementById('stockPreview').textContent = '';
    document.getElementById('stockModal').style.display = 'flex';
}
function closeStockModal() { document.getElementById('stockModal').style.display = 'none'; }
function updateStockPreview() {
    const type = document.getElementById('stockAdjType').value;
    const qty  = parseInt(document.getElementById('stockQty').value) || 0;
    let result = _adjCur;
    if (type==='set') result = qty;
    else if (type==='add') result = _adjCur + qty;
    else if (type==='sub') result = _adjCur - qty;
    document.getElementById('stockPreview').textContent = result >= 0 ? '调整后库存：' + result : '⚠️ 库存不能为负数';
}
function submitStockAdj() {
    const type   = document.getElementById('stockAdjType').value;
    const qty    = parseInt(document.getElementById('stockQty').value);
    const remark = document.getElementById('stockRemark').value;
    if (isNaN(qty) || qty < 0) { alert('请输入有效数量'); return; }
    let newQty = _adjCur;
    if (type==='set') newQty = qty;
    else if (type==='add') newQty = _adjCur + qty;
    else if (type==='sub') newQty = _adjCur - qty;
    if (newQty < 0) { alert('库存不能为负数'); return; }
    fetch('product_stock_adjust.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded'},
        body: `id=${_adjPid}&qty=${newQty}&remark=${encodeURIComponent(remark)}&type=${type}&change=${qty}`
    }).then(r=>r.json()).then(d=>{
        if (d.success) { closeStockModal(); location.reload(); }
        else alert('调整失败：' + d.message);
    });
}
</script>
</body>
</html>
