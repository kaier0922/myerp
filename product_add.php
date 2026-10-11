<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn = getDBConnection();
$message = $error = '';

// 获取父分类和子分类
$parent_cats = [];
$res = $conn->query("SELECT id, name FROM product_categories WHERE parent_id=0 ORDER BY sort_order ASC");
while ($row = $res->fetch_assoc()) { $parent_cats[] = $row; }

$child_cats = [];
$res2 = $conn->query("SELECT id, name, parent_id FROM product_categories WHERE parent_id>0 ORDER BY sort_order ASC");
while ($row = $res2->fetch_assoc()) { $child_cats[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id = intval($_POST['category_id'] ?? 0);
    $platform_data = [];
    
    // 如果没有勾选“跳过校验”，则封装 JSON
    if (!isset($_POST['skip_compat'])) {
        if ($cat_id == 11) { // CPU
            $platform_data = ["socket" => strtoupper(trim($_POST['cpu_socket'])), "generations" => array_filter(array_map('trim', explode(',', $_POST['cpu_gens'])))];
        } elseif ($cat_id == 12) { // 主板
            $platform_data = ["cpu_socket" => strtoupper(trim($_POST['mb_socket'])), "memory_type" => strtoupper($_POST['mb_mem_type']), "support_generations" => array_filter(array_map('trim', explode(',', $_POST['mb_gens'])))];
        } elseif ($cat_id == 13) { // 内存
            $platform_data = ["memory_type" => strtoupper($_POST['mem_type'])];
        }
    } else {
        $platform_data = ["ignore_check" => true];
    }

    $p_json = !empty($platform_data) ? json_encode($platform_data, JSON_UNESCAPED_UNICODE) : null;

    // 超管新增产品归公共库(user_id=0)，普通用户归自己名下
    $cur_uid  = (int)$_SESSION['user_id'];
    $prod_uid = $cur_uid;
    $stmt = $conn->prepare("INSERT INTO products (user_id, category_id, product_type, device_subtype, sku, name, spec, unit, cost_price, default_price, stock_quantity, min_stock, platform_data, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('iissssssdddisi', $prod_uid, $cat_id, $_POST['product_type'], $_POST['device_subtype'], $_POST['sku'], $_POST['name'], $_POST['spec'], $_POST['unit'], $_POST['cost_price'], $_POST['default_price'], $_POST['stock_quantity'], $_POST['min_stock'], $p_json, $cur_uid);
    
    if ($stmt->execute()) { $message = '✅ 产品录入成功！'; writeLog($conn, 'products', 'create', '新建产品'); } else { $error = '❌ 录入失败: ' . $stmt->error; }
}
?>
<?php $page_title = '智能产品录入 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
        <link rel="stylesheet" href="common.css">
<style>
        
        /* 智能识别区样式 */
        .ai-parser { background: #f0f7ff; border: 2px dashed #1f6dad; border-radius: 12px; padding: 20px; margin-bottom: 30px; }
        .ai-parser textarea { width: 100%; height: 60px; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; font-size: 14px; margin-top: 10px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 20px; }
        .full { grid-column: span 3; }
        label { font-size: 13px; font-weight: 600; color: #4b5563; margin-bottom: 8px; display: block; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; font-size: 14px; outline: none; }
        input:focus { border-color: #1f6dad; ring: 2px #1f6dad; }
        
        .compat-section { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin-bottom: 25px; }
        .btn-submit { background: linear-gradient(135deg, #1f6dad 0%, #135286 100%); color: white; border: none; padding: 16px; border-radius: 8px; font-weight: bold; cursor: pointer; width: 100%; font-size: 16px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div style="font-weight:bold; color:#1f6dad; font-size:20px;">📦 产品智能录入</div>
    <a href="products.php" style="text-decoration:none; color:#6b7280;">← 返回产品管理</a>
</nav>

<div class="form-card">
    <div class="ai-parser">
        <label>🚀 智能识别（粘贴产品型号，如：Intel i5-12400F 16G 华硕 B660）</label>
        <textarea id="aiInput" placeholder="在此粘贴型号或参数，系统将自动识别并填表..." oninput="smartParse()"></textarea>
        <div id="parseResult" style="margin-top:10px; font-size:12px; color:#1f6dad;"></div>
    </div>

    <form method="POST">
        <div class="form-grid">
            <div class="form-group">
                <label>产品大类</label>
                <select name="parent_cat" id="parent_cat" onchange="onParentChange()">
                    <option value="">请选择大类...</option>
                    <?php foreach($parent_cats as $c) echo "<option value='{$c['id']}'>{$c['name']}</option>"; ?>
                </select>
            </div>
            <div class="form-group">
                <label>产品子类</label>
                <select name="category_id" id="cat_id" onchange="toggleCompat()">
                    <option value="">请先选大类...</option>
                    <?php foreach($child_cats as $c) echo "<option value='{$c['id']}' data-parent='{$c['parent_id']}'>{$c['name']}</option>"; ?>
                </select>
            </div>
        </div>

        <div id="compat_ui" class="compat-section" style="display:none">
            <div style="display:flex; justify-content:space-between">
                <label>⚠️ 兼容性参数 (自动识别中...)</label>
                <label style="font-weight:normal"><input type="checkbox" name="skip_compat"> 暂不清楚，跳过校验</label>
            </div>
            <div id="ui_11" style="display:none; margin-top:10px" class="form-grid">
                <input type="text" name="cpu_socket" id="cpu_socket" placeholder="接口 (如: LGA1700)">
                <input type="text" name="cpu_gens" id="cpu_gens" class="full" placeholder="世代 (如: Intel-12th)">
            </div>
            <div id="ui_12" style="display:none; margin-top:10px" class="form-grid">
                <input type="text" name="mb_socket" id="mb_socket" placeholder="插槽 (如: LGA1700)">
                <select name="mb_mem_type" id="mb_mem_type"><option value="DDR4">DDR4</option><option value="DDR5">DDR5</option><option value="DDR3">DDR3</option></select>
                <input type="text" name="mb_gens" id="mb_gens" class="full" placeholder="支持世代 (Intel-12th, Intel-13th)">
            </div>
            <div id="ui_13" style="display:none; margin-top:10px">
                <select name="mem_type" id="mem_type"><option value="DDR4">DDR4</option><option value="DDR5">DDR5</option><option value="DDR3">DDR3</option></select>
            </div>
        </div>

        <div class="form-grid">
            <input type="text" name="name" id="p_name" class="full" placeholder="产品正式名称" required>
            <input type="text" name="sku" placeholder="SKU 编码">
            <input type="text" name="unit" value="个">
            <input type="number" step="any" name="cost_price" placeholder="成本价">
            <input type="number" step="any" name="default_price" placeholder="销售价">
            <input type="number" name="stock_quantity" value="0">
            <textarea name="spec" id="p_spec" class="full" placeholder="详细规格描述..."></textarea>
        </div>

        <button type="submit" class="btn-submit">确认并保存产品</button>
    </form>
</div>

<script>
// 智能识别核心逻辑
function smartParse() {
    const text = document.getElementById('aiInput').value.toUpperCase();
    const resultDiv = document.getElementById('parseResult');
    let tags = [];

    // 1. 识别产品类型
    if (text.includes("I3-") || text.includes("I5-") || text.includes("I7-") || text.includes("RYZEN")) {
        document.getElementById('cat_id').value = "11"; // 自动选CPU
    } else if (text.includes("B660") || text.includes("B760") || text.includes("H610") || text.includes("B550")) {
        document.getElementById('cat_id').value = "12"; // 自动选主板
    }

    // 2. 识别接口 Socket
    const socketMap = {
        "LGA1700": [/12\d00/, /13\d00/, /14\d00/, /B660/, /B760/, /Z690/, /Z790/, /H610/],
        "AM4": [/RYZEN [357] [1-5]\d00/, /B450/, /B550/, /A320/, /A520/],
        "LGA1200": [/10\d00/, /11\d00/, /B460/, /B560/, /H410/, /H510/],
        "LGA1151": [/6\d00/, /7\d00/, /8\d00/, /9\d00/, /B150/, /B250/, /B360/, /H110/, /H310/],
        "LGA2011-3": [/E5-26\d\d V[34]/, /X99/],
        "LGA2011": [/E5-26\d\d V[12]/, /X79/]
    };

    for (let socket in socketMap) {
        if (socketMap[socket].some(reg => reg.test(text)) || text.includes(socket)) {
            if(document.getElementById('cpu_socket')) document.getElementById('cpu_socket').value = socket;
            if(document.getElementById('mb_socket')) document.getElementById('mb_socket').value = socket;
            tags.push("检测到接口: " + socket);
            break;
        }
    }

    // 3. 识别内存
    if (text.includes("DDR5")) {
        if(document.getElementById('mb_mem_type')) document.getElementById('mb_mem_type').value = "DDR5";
        if(document.getElementById('mem_type')) document.getElementById('mem_type').value = "DDR5";
        tags.push("内存类型: DDR5");
    } else if (text.includes("DDR4")) {
        tags.push("内存类型: DDR4");
    }

    // 4. 同步名称
    document.getElementById('p_name').value = document.getElementById('aiInput').value;
    
    toggleCompat();
    resultDiv.innerHTML = tags.map(t => `<span class="badge">${t}</span>`).join('');
}

// 父分类 → product_type 映射
const PARENT_TO_TYPE = {
    1: 'hardware',  // 硬件配件
    2: 'device',    // 整机设备
    3: 'device',    // 网络设备
    4: 'device',    // 外设周边
    5: 'device',    // 监控安防
    6: 'software',  // 软件授权
    7: 'service'    // 技术服务
};

function onParentChange() {
    const parentId = parseInt(document.getElementById('parent_cat').value);
    const catSel   = document.getElementById('cat_id');

    // 过滤子分类
    let firstVal = '';
    Array.from(catSel.options).forEach(opt => {
        if (!opt.value) { opt.style.display = ''; return; }
        const show = parseInt(opt.dataset.parent) === parentId;
        opt.style.display = show ? '' : 'none';
        if (show && !firstVal) firstVal = opt.value;
    });
    catSel.value = firstVal || '';

    // 同步 product_type 隐藏字段
    const ptype = PARENT_TO_TYPE[parentId] || 'hardware';
    let ptypeField = document.getElementById('p_type_hidden');
    if (!ptypeField) {
        ptypeField = document.createElement('input');
        ptypeField.type = 'hidden';
        ptypeField.name = 'product_type';
        ptypeField.id   = 'p_type_hidden';
        catSel.parentElement.appendChild(ptypeField);
    }
    ptypeField.value = ptype;

    toggleCompat();
}

function toggleType() { onParentChange(); }

function toggleCompat() {
    const c = document.getElementById('cat_id').value;
    const ui = document.getElementById('compat_ui');
    ui.style.display = (['11','12','13'].includes(c)) ? 'block' : 'none';
    document.getElementById('ui_11').style.display = (c==='11'?'grid':'none');
    document.getElementById('ui_12').style.display = (c==='12'?'grid':'none');
    document.getElementById('ui_13').style.display = (c==='13'?'block':'none');
}
</script>
</body>
</html>