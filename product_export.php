<?php
/**
 * 文件名: product_export.php
 * 版本: 1.0
 * 功能: 产品数据导出（CSV，支持按分类/类型/搜索过滤）
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
require_once 'settings_helper.php';
$cfg = getUserSettings($conn, $_SESSION['user_id']);
if (empty($cfg['is_commercial'])) {
    if (isset($_GET['action'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'此功能仅授权版本可用']);
        exit;
    }
    die('<div style="font-family:sans-serif;text-align:center;padding:80px;color:#c0392b"><h2>&#x1F512; 此功能仅授权版本可用</h2><p>请联系管理员升级授权。</p><a href="products.php" style="color:#1f6dad">赔回产品库</a></div>');
}
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── AJAX：动态加载子分类 ──
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'get_categories') {
        $pid = intval($_GET['type'] ?? 0);
        $stmt = $conn->prepare("SELECT id, name FROM product_categories WHERE parent_id=? ORDER BY sort_order ASC");
        $stmt->bind_param('i', $pid);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    }
    exit;
}

// ── 分类树 ──
$parent_cats = $conn->query("SELECT id,name FROM product_categories WHERE parent_id=0 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$child_cats  = $conn->query("SELECT id,name,parent_id FROM product_categories WHERE parent_id>0 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$all_cats    = $conn->query("SELECT id,name,parent_id FROM product_categories")->fetch_all(MYSQLI_ASSOC);
$cat_name_map= []; foreach ($all_cats as $c) $cat_name_map[$c['id']] = $c['name'];

// ── 统计 ──
$_exp_uid = (int)$_SESSION['user_id'];
$_exp_super = (($_SESSION['role'] ?? '') === 'super_admin');
$stats = $conn->query("SELECT COUNT(*) as total, SUM(stock_quantity) as qty, SUM(stock_quantity*cost_price) as value FROM products WHERE is_active=1 AND (user_id=0 OR user_id=$_exp_uid)")->fetch_assoc();

// ── 执行导出（CSV下载） ──
if (isset($_POST['do_export'])) {
    $cat_id  = intval($_POST['cat_id'] ?? 0);
    $ptype   = trim($_POST['ptype'] ?? '');
    $search  = trim($_POST['search'] ?? '');
    $fields  = $_POST['fields'] ?? [];

    $where = ['p.is_active=1'];
    $params = []; $types = '';
    if ($cat_id) {
        // 判断是否顶级
        $is_top = false;
        foreach ($parent_cats as $pc) if ($pc['id']==$cat_id) { $is_top=true; break; }
        if ($is_top) {
            $child_ids = array_column(array_filter($child_cats, fn($c)=>$c['parent_id']==$cat_id), 'id');
            if ($child_ids) {
                $phs = implode(',', array_fill(0, count($child_ids), '?'));
                $where[] = "p.category_id IN ($phs)";
                foreach ($child_ids as $cid) { $params[]=$cid; $types.='i'; }
            }
        } else {
            $where[] = "p.category_id=?"; $params[]=$cat_id; $types.='i';
        }
    }
    if ($ptype) { $where[] = "p.product_type=?"; $params[]=$ptype; $types.='s'; }
    if ($search) { $where[] = "(p.name LIKE ? OR p.sku LIKE ?)"; $s="%$search%"; $params[]=$s;$params[]=$s; $types.='ss'; }

    $where[] = "(p.user_id=0 OR p.user_id=$_exp_uid)";
    $sql = "SELECT p.*,pc.name as cat_name,pp.name as parent_cat FROM products p
            LEFT JOIN product_categories pc ON p.category_id=pc.id
            LEFT JOIN product_categories pp ON pc.parent_id=pp.id
            WHERE ".implode(' AND ',$where)."
            ORDER BY p.category_id,p.name";
    if ($params) {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $rows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $filename = '产品导出_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $all_fields = [
        'id'=>'产品ID','name'=>'产品名称','sku'=>'SKU','spec'=>'规格说明',
        'unit'=>'单位','parent_cat'=>'大类','cat_name'=>'子类','product_type'=>'产品类型',
        'cost_price'=>'成本价','default_price'=>'售价','stock_quantity'=>'库存数量',
        'min_stock'=>'最低库存','is_active'=>'是否启用','created_at'=>'创建时间',
    ];
    $export_fields = empty($fields) ? array_keys($all_fields) : $fields;

    // 表头
    echo implode(',', array_map(fn($f)=>'"'.($all_fields[$f]??$f).'"', $export_fields)) . "\n";
    // 数据
    $type_label = ['hardware'=>'硬件配件','device'=>'整机设备','software'=>'软件授权','service'=>'技术服务'];
    foreach ($rows as $row) {
        $line = [];
        foreach ($export_fields as $f) {
            $v = $row[$f] ?? '';
            if ($f === 'product_type') $v = $type_label[$v] ?? $v;
            if ($f === 'is_active') $v = $v ? '是' : '否';
            $line[] = '"' . str_replace('"','""',$v) . '"';
        }
        echo implode(',', $line) . "\n";
    }
    $conn->close(); exit;
}

$conn->close();
?>
<?php $page_title = '产品导出 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.
.btn-nav{padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:13px;text-decoration:none;}
.btn-nav:hover{background:#edf2f7;}
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.stat-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.stat-card .sv{font-size:24px;font-weight:700;color:#1f6dad;}.stat-card .sl{font-size:12px;color:#718096;margin-top:4px;}
.card{background:white;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;margin-bottom:24px;}
.card-title{font-size:16px;font-weight:700;color:#1a202c;}
.card-
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;}
.form-group label{display:block;font-size:12px;font-weight:700;color:#4a5568;margin-bottom:8px;}
.form-group select,.form-group input[type=text]{width:100%;padding:11px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;outline:none;font-family:inherit;}
.form-group select:focus,.form-group input:focus{border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
/* 字段选择 */
.field-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px;}
.field-check{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .2s;font-size:13px;color:#4a5568;}
.field-check:hover{border-color:#1f6dad;background:#f0f0ff;}
.field-check input[type=checkbox]{accent-color:#1f6dad;}
.field-check.checked{border-color:#1f6dad;background:#eef2ff;color:#4338ca;font-weight:500;}
.section-title{font-size:14px;font-weight:700;color:#1a202c;margin-bottom:12px;display:flex;align-items:center;gap:8px;}
.btn-all{font-size:12px;color:#1f6dad;cursor:pointer;text-decoration:underline;margin-left:8px;}
.btn-submit{width:100%;padding:15px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:10px;font-size:16px;font-weight:700;cursor:pointer;transition:all .2s;letter-spacing:.5px;}
.btn-submit:hover{transform:translateY(-2px);box-shadow:0 8px 20px rgba(31,109,173,.4);}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
<div style="max-width:960px;margin:0 auto;">
    <div class="page-header">
        <div class="page-title">📤 产品数据导出</div>
        <a href="products.php"    class="btn-nav">← 返回产品管理</a>
        <a href="product_import.php" class="btn-nav">📥 导入产品</a>
    </div>

    <!-- 统计 -->
    <div class="stat-row">
        <div class="stat-card"><div class="sv"><?=number_format($stats['total']??0)?></div><div class="sl">📦 产品总数</div></div>
        <div class="stat-card"><div class="sv"><?=number_format($stats['qty']??0)?></div><div class="sl">🗃️ 库存总数量</div></div>
        <div class="stat-card"><div class="sv">¥<?=number_format($stats['value']??0,2)?></div><div class="sl">💰 库存总价值</div></div>
    </div>

    <form method="POST">
        <input type="hidden" name="do_export" value="1">
        <div class="card">
            <div class="card-header"><span style="font-size:20px;">🔍</span><div class="card-title">筛选范围</div></div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label><span class="step-num">1</span> 产品大类</label>
                        <select id="m_type" onchange="fetchCats()">
                            <option value="">请选择大类...</option>
                            <?php foreach ($parent_cats as $top): ?>
                            <option value="<?=$top['id']?>"><?=e($top['name'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><span class="step-num">2</span> 产品子类</label>
                        <select id="s_cat" name="cat_id" disabled>
                            <option value="">请先选择大类...</option>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column:1/-1;">
                        <label><span class="step-num">3</span> 关键字过滤（可选）</label>
                        <input type="text" name="search" id="p_search" placeholder="输入产品名称 / SKU / 规格关键字过滤...">
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span style="font-size:20px;">✅</span><div class="card-title">选择导出字段 <span class="btn-all" onclick="toggleAll(true)">全选</span><span class="btn-all" onclick="toggleAll(false)">取消</span></div></div>
            <div class="card-body">
                <div class="field-grid" id="fieldGrid">
                    <?php
                    $fields_def = [
                        'name'=>'产品名称','sku'=>'SKU','spec'=>'规格说明','unit'=>'单位',
                        'parent_cat'=>'大类','cat_name'=>'子类','product_type'=>'产品类型',
                        'cost_price'=>'成本价','default_price'=>'售价',
                        'stock_quantity'=>'库存数量','min_stock'=>'最低库存',
                        'is_active'=>'是否启用','created_at'=>'创建时间',
                    ];
                    $default_checked = ['name','sku','spec','unit','cat_name','cost_price','default_price','stock_quantity'];
                    foreach ($fields_def as $f => $label):
                        $chk = in_array($f, $default_checked) ? 'checked' : '';
                        $cls = $chk ? 'field-check checked' : 'field-check';
                    ?>
                    <label class="<?=$cls?>" id="lbl_<?=$f?>">
                        <input type="checkbox" name="fields[]" value="<?=$f?>" <?=$chk?> onchange="syncClass('<?=$f?>')">
                        <?=$label?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-submit">⬇️ 导出 CSV 文件</button>
    </form>
</div>
</div>
<script>
function syncClass(f) {
    const cb = document.querySelector(`input[value="${f}"]`);
    document.getElementById('lbl_'+f).className = 'field-check' + (cb.checked?' checked':'');
}
function toggleAll(chk) {
    document.querySelectorAll('#fieldGrid input[type=checkbox]').forEach(cb => {
        cb.checked = chk;
        syncClass(cb.value);
    });
}
function fetchCats() {
    const tid = document.getElementById('m_type').value;
    const sCat = document.getElementById('s_cat');
    sCat.innerHTML = '<option>加载中...</option>';
    sCat.disabled = true;
    if (!tid) {
        sCat.innerHTML = '<option value="">请先选择大类...</option>';
        return;
    }
    fetch(`product_export.php?action=get_categories&type=${tid}`)
        .then(r => r.json())
        .then(data => {
            sCat.innerHTML = '<option value="">全部子类（不限）</option>';
            data.forEach(c => sCat.add(new Option(c.name, c.id)));
            sCat.disabled = false;
        });
}
</script>
</body>
</html>
