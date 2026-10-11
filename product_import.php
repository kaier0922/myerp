<?php
/**
 * 文件名: product_import.php
 * 版本: 1.0
 * 功能: 产品批量导入（CSV/Excel）
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();


$conn = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// ── 读取分类树 ──
$parent_cats = $conn->query("SELECT id,name FROM product_categories WHERE parent_id=0 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$child_cats  = $conn->query("SELECT id,name,parent_id FROM product_categories WHERE parent_id>0 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$cat_map = []; // id=>name
foreach ($child_cats as $c) $cat_map[$c['id']] = $c['name'];

// ── AJAX 处理导入 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    header('Content-Type: application/json; charset=utf-8');
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success'=>false,'message'=>'文件上传失败']); exit;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv','txt'])) {
        echo json_encode(['success'=>false,'message'=>'仅支持 CSV 格式']); exit;
    }

    $content = file_get_contents($file['tmp_name']);
    // 去除 BOM
    $content = ltrim($content, "\xEF\xBB\xBF");
    $lines   = preg_split('/\r\n|\n|\r/', trim($content));
    if (count($lines) < 2) {
        echo json_encode(['success'=>false,'message'=>'文件内容为空或格式不正确']); exit;
    }

    // 解析表头
    $header = str_getcsv(array_shift($lines));
    $header = array_map('trim', $header);
    // 字段映射
    $col_map = [
        'name'          => ['产品名称','名称','name'],
        'sku'           => ['SKU','编码','sku'],
        'spec'          => ['规格','spec','规格说明'],
        'unit'          => ['单位','unit'],
        'category_id'   => ['分类ID','category_id'],
        'cost_price'    => ['成本价','进价','cost_price'],
        'default_price' => ['售价','销售价','单价','default_price'],
        'stock_quantity'=> ['库存','库存数量','stock_quantity'],
        'min_stock'     => ['最低库存','预警值','min_stock'],
        'product_type'  => ['产品类型','product_type'],
    ];
    $col_idx = [];
    foreach ($col_map as $field => $aliases) {
        foreach ($aliases as $alias) {
            $idx = array_search($alias, $header);
            if ($idx !== false) { $col_idx[$field] = $idx; break; }
        }
    }
    if (!isset($col_idx['name'])) {
        echo json_encode(['success'=>false,'message'=>'未找到「产品名称」列，请检查表头']); exit;
    }

    $imported = 0; $skipped = 0; $errors = [];
    // 超管导入归公共库(user_id=0)，普通用户归自己名下
    $cur_uid  = (int)$_SESSION['user_id'];
    $prod_uid = $cur_uid;
    $stmt = $conn->prepare("INSERT INTO products (user_id,name,sku,spec,unit,category_id,product_type,cost_price,default_price,stock_quantity,min_stock,is_active,created_by,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,1,?,NOW())
        ON DUPLICATE KEY UPDATE name=VALUES(name),spec=VALUES(spec),cost_price=VALUES(cost_price),default_price=VALUES(default_price)");

    $type_map = ['硬件配件'=>'hardware','整机设备'=>'device','软件授权'=>'software','技术服务'=>'service'];

    foreach ($lines as $ln => $line) {
        if (trim($line) === '') continue;
        $row = str_getcsv($line);
        $name = trim($row[$col_idx['name']] ?? '');
        if (!$name) { $skipped++; continue; }

        $sku           = trim($row[$col_idx['sku']          ?? -1] ?? '');
        $spec          = trim($row[$col_idx['spec']         ?? -1] ?? '');
        $unit          = trim($row[$col_idx['unit']         ?? -1] ?? '个');
        $cat_id        = intval($row[$col_idx['category_id']?? -1] ?? 0) ?: null;
        $cost          = floatval($row[$col_idx['cost_price']    ?? -1] ?? 0);
        $price         = floatval($row[$col_idx['default_price'] ?? -1] ?? 0);
        $stock         = intval($row[$col_idx['stock_quantity']  ?? -1] ?? 0);
        $min_stock     = intval($row[$col_idx['min_stock']       ?? -1] ?? 0);
        $ptype_raw     = trim($row[$col_idx['product_type']      ?? -1] ?? '');
        $ptype         = $type_map[$ptype_raw] ?? ($ptype_raw ?: 'hardware');

        $stmt->bind_param('issssisddiii',
            $prod_uid,
            $name,$sku,$spec,$unit,$cat_id,$ptype,
            $cost,$price,$stock,$min_stock,$cur_uid);
        if ($stmt->execute()) $imported++;
        else { $skipped++; $errors[] = "第".($ln+2)."行：{$stmt->error}"; }
    }

    echo json_encode([
        'success'  => true,
        'imported' => $imported,
        'skipped'  => $skipped,
        'errors'   => array_slice($errors, 0, 10),
    ]);
    $conn->close(); exit;
}

// ── 下载模板 ──
if (isset($_GET['tpl'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="产品导入模板.csv"');
    echo "\xEF\xBB\xBF"; // BOM
    echo "产品名称,SKU,规格,单位,分类ID,产品类型,成本价,售价,库存,最低库存\n";
    echo "示例：Intel i5-12400F,CPU-12400F,\"6核12线程 2.5GHz LGA1700\",个,11,硬件配件,800,1200,10,2\n";
    // 附加分类参考
    foreach ($child_cats as $c) {
        echo sprintf(",,,,,,%s(%d),,,,\n", addslashes($c['name']), $c['id']);
    }
    exit;
}

$conn->close();
?>
<?php $page_title = '产品导入 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.
.btn-nav{padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:13px;text-decoration:none;transition:all .2s;}
.btn-nav:hover{background:#edf2f7;}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:24px;}
.card{background:white;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;margin-bottom:24px;}
.card-title{font-size:16px;font-weight:700;color:#1a202c;}
.card-
.info-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px;margin-bottom:20px;font-size:13px;color:#135286;line-height:2;}
.info-box strong{display:block;font-size:14px;margin-bottom:4px;color:#1e3a8a;}
.info-box code{background:#dbeafe;padding:1px 6px;border-radius:4px;font-family:monospace;}
.drop-zone{border:2px dashed #c7d2fe;border-radius:12px;padding:48px 24px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbff;}
.drop-zone:hover,.drop-zone.drag-over{border-color:#1f6dad;background:#eef2ff;}
.drop-zone .icon{font-size:48px;margin-bottom:12px;}
.drop-zone p{font-size:14px;color:#4a5568;margin-bottom:8px;}
.drop-zone small{font-size:12px;color:#a0aec0;}
.drop-zone input[type=file]{display:none;}
.btn-primary:disabled{opacity:.6;transform:none;box-shadow:none;cursor:not-allowed;}
.btn-tpl{padding:10px 20px;background:white;border:1px solid #1f6dad;color:#1f6dad;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:6px;transition:all .2s;margin-bottom:16px;}
.btn-tpl:hover{background:#eef2ff;}
.result-box{padding:16px;border-radius:10px;margin-top:16px;font-size:14px;display:none;}
.result-ok{background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;}
.result-err{background:#fff1f2;color:#be123c;border:1px solid #fecdd3;}
.progress-wrap{background:#e2e8f0;border-radius:8px;height:8px;margin-top:12px;overflow:hidden;display:none;}
.progress-bar{height:100%;border-radius:8px;background:linear-gradient(90deg,#1f6dad,#135286);width:0%;transition:width .3s;}
.cat-
.cat-table
.cat-table
.cat-table tr:last-child
code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-family:monospace;font-size:12px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
<div style="max-width:1100px;margin:0 auto;">
    <div class="page-header">
        <div class="page-title">📥 产品批量导入</div>
        <a href="products.php"   class="btn-nav">← 返回产品管理</a>
        <a href="product_export.php" class="btn-nav">📤 导出产品</a>
    </div>
    <div class="two-col">
        <!-- 左：上传区 -->
        <div>
            <div class="card">
                <div class="card-header"><span style="font-size:20px;">📁</span><div class="card-title">上传 CSV 文件</div></div>
                <div class="card-body">
                    <a href="product_import.php?tpl=1" class="btn-tpl">⬇️ 下载导入模板</a>
                    <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                        <div class="icon">📄</div>
                        <p>点击选择文件，或将 CSV 文件拖拽到此处</p>
                        <small>支持 .csv 格式，UTF-8 编码，文件大小不超过 10MB</small>
                        <input type="file" id="fileInput" accept=".csv,.txt">
                    </div>
                    <div id="fileName" style="margin-top:10px;font-size:13px;color:#1f6dad;display:none;"></div>
                    <div class="progress-wrap" id="progressWrap"><div class="progress-bar" id="progressBar"></div></div>
                    <button class="btn-primary" id="importBtn" onclick="doImport()" disabled>📥 开始导入</button>
                    <div class="result-box" id="resultBox"></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><span style="font-size:20px;">📋</span><div class="card-title">导入规则说明</div></div>
                <div class="card-body">
                    <div class="info-box">
                        <strong>📌 必填字段</strong>
                        产品名称（<code>产品名称</code>列）
                    </div>
                    <div class="info-box">
                        <strong>📌 表头格式（第一行）</strong>
                        <code>产品名称</code>、<code>SKU</code>、<code>规格</code>、<code>单位</code>、<code>分类ID</code>、<code>产品类型</code>、<code>成本价</code>、<code>售价</code>、<code>库存</code>、<code>最低库存</code>
                    </div>
                    <div class="info-box">
                        <strong>📌 产品类型可填</strong>
                        硬件配件 / 整机设备 / 软件授权 / 技术服务
                    </div>
                    <div class="info-box">
                        <strong>📌 SKU 重复处理</strong>
                        SKU 相同时更新名称、规格、价格；无 SKU 则直接插入新记录
                    </div>
                </div>
            </div>
        </div>
        <!-- 右：分类ID参考 -->
        <div>
            <div class="card">
                <div class="card-header"><span style="font-size:20px;">🗂️</span><div class="card-title">分类 ID 参考表</div></div>
                <div class="card-body" style="padding:0;">
                    <table class="cat-table">
                        <thead><tr><th>大类</th><th>子类名称</th><th>分类ID</th></tr></thead>
                        <tbody>
                        <?php foreach ($parent_cats as $top):
                            $children = array_filter($child_cats, fn($c)=>$c['parent_id']==$top['id']);
                            if (empty($children)): ?>
                            <tr>
                                <td style="font-weight:600;"><?=e($top['name'])?></td>
                                <td style="color:#a0aec0;">-</td>
                                <td><code><?=$top['id']?></code></td>
                            </tr>
                            <?php else:
                            $first = true;
                            foreach ($children as $ch): ?>
                            <tr>
                                <?php if ($first): ?><td style="font-weight:600;vertical-align:top;" rowspan="<?=count($children)?>"><?=e($top['name'])?></td><?php $first=false; endif; ?>
                                <td><?=e($ch['name'])?></td>
                                <td><code><?=$ch['id']?></code></td>
                            </tr>
                            <?php endforeach; endif; endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
let selectedFile = null;

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault(); dropZone.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f) setFile(f);
});
fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

function setFile(f) {
    selectedFile = f;
    document.getElementById('fileName').style.display = 'block';
    document.getElementById('fileName').textContent = '已选择：' + f.name + '（' + (f.size/1024).toFixed(1) + ' KB）';
    document.getElementById('importBtn').disabled = false;
    document.getElementById('resultBox').style.display = 'none';
}

async function doImport() {
    if (!selectedFile) return;
    const btn = document.getElementById('importBtn');
    btn.disabled = true; btn.textContent = '⏳ 导入中...';
    const pw = document.getElementById('progressWrap');
    const pb = document.getElementById('progressBar');
    pw.style.display = 'block'; pb.style.width = '30%';

    const fd = new FormData();
    fd.append('csv_file', selectedFile);
    pb.style.width = '60%';

    try {
        const r = await fetch('product_import.php', {method:'POST', body:fd});
        pb.style.width = '100%';
        const d = await r.json();
        const box = document.getElementById('resultBox');
        box.style.display = 'block';
        if (d.success) {
            box.className = 'result-box result-ok';
            box.innerHTML = `✅ 导入完成：成功 <strong>${d.imported}</strong> 条，跳过 <strong>${d.skipped}</strong> 条`
                + (d.errors?.length ? `<br><small style="color:#92400e;">部分错误：${d.errors.join('；')}</small>` : '');
        } else {
            box.className = 'result-box result-err';
            box.innerHTML = '❌ ' + (d.message || '导入失败');
        }
    } catch(e) {
        const box = document.getElementById('resultBox');
        box.style.display='block'; box.className='result-box result-err';
        box.textContent = '❌ 网络错误：' + e.message;
    }
    btn.disabled = false; btn.textContent = '📥 再次导入';
    setTimeout(() => { pw.style.display='none'; pb.style.width='0%'; }, 1000);
}
</script>
</body>
</html>
