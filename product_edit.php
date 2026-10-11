<?php
/**
 * ============================================================================
 * 文件名: product_edit.php
 * 版本: 4.5 (智能兼容增强版)
 * 修改日期: 2026-03-13
 * * 主要功能：
 * 1. 自动解析并回填数据库中的 platform_data (JSON)。
 * 2. 移除左侧菜单，采用全屏专注布局。
 * 3. 增加“智能识别”功能，支持型号快速解析。
 * 4. 保持原有的 Fetch 异步保存逻辑。
 * ============================================================================
 */

session_start();
require_once 'config.php';

// 权限验证
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

// 获取产品ID
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($product_id <= 0) {
    die('无效的产品ID');
}

// 1. 获取产品详细信息
$is_super = (($_SESSION['role'] ?? '') === 'super_admin');
$product_sql = "SELECT * FROM products WHERE id = ?";
$stmt = $conn->prepare($product_sql);
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    die('产品不存在');
}
if ($product['user_id'] == 0 && !$is_super) {
    die('权限不足，公共产品库只有超级管理员可以修改');
}
if ($product['user_id'] != 0 && $product['user_id'] != intval($_SESSION['user_id'])) {
    die('权限不足，无法编辑其他用户的产品');
}

// 2. 解析 JSON 兼容性参数
$compat = json_decode($product['platform_data'] ?? '{}', true);

// 3. 获取所有分类
$categories = [];
$cat_res = $conn->query("SELECT id, name FROM product_categories ORDER BY sort_order ASC");
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = $row;
}
$conn->close();
?>
<?php $page_title = '编辑产品 - ' . htmlspecialchars($product['name']); include __DIR__ . '/head.php'; ?>
    <style>

        /* 顶部导航栏 - 移除侧边栏，改为全宽 */

        /* 🚀 智能识别区样式 */
        .ai-parser {
            background: #f0f7ff;
            border: 2px dashed #1f6dad;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .ai-parser textarea {
            width: 100%;
            height: 60px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            font-size: 14px;
            margin-top: 10px;
            resize: none;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-row.full { grid-template-columns: 1fr; }

        .form-input, .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        /* 兼容性参数显示区 */
        .compat-box { background: #fffbeb; border: 1px solid #fef3c7; border-radius: 10px; padding: 25px; display: none; }

        .btn-success { background: #1f6dad; color: white; }
        .btn-success:hover { background: #135286; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(31, 109, 173, 0.3); }

        .info-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin-bottom: 20px; }
        .form-card { max-width: 900px; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
<div class="page-header">
  <h1 class="page-title">📦 编辑产品资料</h1>
  <div style="display:flex;gap:10px;">
    <a href="products.php" class="btn btn-secondary">取消退出</a>
    <button class="btn btn-success" id="saveBtn" onclick="saveProduct()">确认修改并保存</button>
  </div>
</div>
        <div class="ai-parser">
            <label class="form-label">🚀 智能校对（粘贴新型号，可自动识别下方接口、世代等参数）</label>
            <textarea id="aiInput" placeholder="在此粘贴型号或参数，例如：Intel i5-12400F 16G 华硕 B660..." oninput="smartParse()"></textarea>
            <div id="parseResult"></div>
        </div>

        <form id="productForm">
            <input type="hidden" name="id" value="<?php echo $product_id; ?>">

            <div class="form-card">
                <h2 class="section-title">基本信息</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">产品类型</label>
                        <select name="product_type" id="product_type" class="form-select" onchange="toggleType()">
                            <option value="hardware" <?php echo $product['product_type'] == 'hardware' ? 'selected' : ''; ?>>💻 硬件配件</option>
                            <option value="device" <?php echo $product['product_type'] == 'device' ? 'selected' : ''; ?>>🖥️ 整机设备</option>
                        </select>
                    </div>
                    <div class="form-group" id="hw_cat_group">
                        <label class="form-label">配件分类</label>
                        <select name="category_id" id="category_id" class="form-select" onchange="toggleCompat()">
                            <option value="">请选择分类</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $product['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="dv_sub_group" style="display:none">
                        <label class="form-label">设备子类</label>
                        <select name="device_subtype" class="form-select">
                            <option value="desktop" <?php echo ($product['device_subtype'] ?? '') == 'desktop' ? 'selected' : ''; ?>>台式机</option>
                            <option value="storage" <?php echo ($product['device_subtype'] ?? '') == 'storage' ? 'selected' : ''; ?>>存储/NAS</option>
                            <option value="server" <?php echo ($product['device_subtype'] ?? '') == 'server' ? 'selected' : ''; ?>>服务器</option>
                        </select>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">产品名称</label>
                        <input type="text" name="name" id="p_name" class="form-input" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                    </div>
                </div>
            </div>

            <div id="compat_section" class="form-card compat-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                    <h2 class="section-title" style="margin-bottom:0; border:none; color:#b45309;">⚙️ 智能兼容性参数</h2>
                    <label style="font-size:13px;"><input type="checkbox" name="skip_compat" <?php if(isset($compat['ignore_check'])) echo 'checked'; ?>> 忽略自动校验</label>
                </div>
                
                <div id="ui_11" style="display:none" class="form-row">
                    <div class="form-group">
                        <label class="form-label">Socket 插槽 (接口)</label>
                        <input type="text" name="cpu_socket" id="cpu_socket" class="form-input" value="<?php echo $compat['socket'] ?? ''; ?>" placeholder="如: LGA1700">
                    </div>
                    <div class="form-group" style="grid-column: span 2">
                        <label class="form-label">对应世代 (逗号隔开)</label>
                        <input type="text" name="cpu_gens" id="cpu_gens" class="form-input" value="<?php echo isset($compat['generations']) ? implode(',', $compat['generations']) : ''; ?>" placeholder="如: Intel-12th, Intel-13th">
                    </div>
                </div>

                <div id="ui_12" style="display:none" class="form-row">
                    <div class="form-group">
                        <label class="form-label">支持 CPU 插槽</label>
                        <input type="text" name="mb_socket" id="mb_socket" class="form-input" value="<?php echo $compat['cpu_socket'] ?? ''; ?>" placeholder="如: AM4">
                    </div>
                    <div class="form-group">
                        <label class="form-label">内存类型</label>
                        <select name="mb_mem_type" id="mb_mem_type" class="form-select">
                            <option value="DDR4" <?php echo ($compat['memory_type']??'')=='DDR4'?'selected':''; ?>>DDR4</option>
                            <option value="DDR5" <?php echo ($compat['memory_type']??'')=='DDR5'?'selected':''; ?>>DDR5</option>
                            <option value="DDR3" <?php echo ($compat['memory_type']??'')=='DDR3'?'selected':''; ?>>DDR3</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">支持世代</label>
                        <input type="text" name="mb_gens" id="mb_gens" class="form-input" value="<?php echo isset($compat['support_generations']) ? implode(',', $compat['support_generations']) : ''; ?>" placeholder="如: Ryzen-5000">
                    </div>
                </div>

                <div id="ui_13" style="display:none">
                    <div class="form-group" style="width:30%">
                        <label class="form-label">内存代数</label>
                        <select name="mem_type" id="mem_type" class="form-select">
                            <option value="DDR4" <?php echo ($compat['memory_type']??'')=='DDR4'?'selected':''; ?>>DDR4</option>
                            <option value="DDR5" <?php echo ($compat['memory_type']??'')=='DDR5'?'selected':''; ?>>DDR5</option>
                            <option value="DDR3" <?php echo ($compat['memory_type']??'')=='DDR3'?'selected':''; ?>>DDR3</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h2 class="section-title">价格与库存</h2>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">SKU 编码</label>
                        <input type="text" name="sku" class="form-input" value="<?php echo htmlspecialchars($product['sku']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">单位</label>
                        <input type="text" name="unit" class="form-input" value="<?php echo htmlspecialchars($product['unit']); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">库存数量</label>
                        <input type="number" name="stock_quantity" class="form-input" value="<?php echo $product['stock_quantity']; ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">成本价 (¥)</label>
                        <input type="number" step="any" name="cost_price" id="cost_price" class="form-input" value="<?php echo $product['cost_price']; ?>" oninput="calcProfit()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">销售价 (¥)</label>
                        <input type="number" step="any" name="default_price" id="default_price" class="form-input" value="<?php echo $product['default_price']; ?>" oninput="calcProfit()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">库存预警线</label>
                        <input type="number" name="min_stock" class="form-input" value="<?php echo $product['min_stock']; ?>">
                    </div>
                </div>

                <div class="info-box" id="profitInfo">
                    <div id="profitResult">正在计算利润...</div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">规格描述 (Spec)</label>
                        <textarea name="spec" class="form-input" rows="3"><?php echo htmlspecialchars($product['spec']); ?></textarea>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script>
        // 🚀 智能解析
        function smartParse() {
            const text = document.getElementById('aiInput').value.toUpperCase();
            const resDiv = document.getElementById('parseResult');
            let found = [];

            const patterns = {
                'LGA1700': [/12\d00/, /13\d00/, /14\d00/, /B660/, /B760/, /H610/, /Z790/],
                'AM4': [/RYZEN [357]/, /B550/, /A520/, /B450/, /X570/],
                'LGA1200': [/10\d00/, /11\d00/, /H410/, /H510/, /B560/],
                'LGA1151': [/6\d00/, /7\d00/, /8\d00/, /9\d00/, /H110/, /B250/, /B360/, /H310/],
                'LGA2011-3': [/E5-26\d\d V[34]/, /X99/],
                'LGA2011': [/E5-26\d\d V[12]/, /X79/]
            };

            for (let s in patterns) {
                if (patterns[s].some(r => r.test(text))) {
                    if(document.getElementById('cpu_socket')) document.getElementById('cpu_socket').value = s;
                    if(document.getElementById('mb_socket')) document.getElementById('mb_socket').value = s;
                    found.push(s);
                }
            }
            if(text.includes("DDR5")) { 
                if(document.getElementById('mb_mem_type')) document.getElementById('mb_mem_type').value = "DDR5";
                if(document.getElementById('mem_type')) document.getElementById('mem_type').value = "DDR5";
                found.push("DDR5"); 
            }
            resDiv.innerHTML = found.map(f => `<span class="badge">已识别: ${f}</span>`).join('');
        }

        // UI 切换
        function toggleType() {
            const t = document.getElementById('product_type').value;
            document.getElementById('hw_cat_group').style.display = (t==='hardware'?'block':'none');
            document.getElementById('dv_sub_group').style.display = (t==='device'?'block':'none');
            toggleCompat();
        }

        function toggleCompat() {
            const c = document.getElementById('category_id').value;
            const box = document.getElementById('compat_section');
            box.style.display = (['11','12','13'].includes(c)) ? 'block' : 'none';
            document.getElementById('ui_11').style.display = (c==='11'?'grid':'none');
            document.getElementById('ui_12').style.display = (c==='12'?'grid':'none');
            document.getElementById('ui_13').style.display = (c==='13'?'block':'none');
        }

        // 利润计算
        function calcProfit() {
            const cost = parseFloat(document.getElementById('cost_price').value) || 0;
            const sell = parseFloat(document.getElementById('default_price').value) || 0;
            const profit = sell - cost;
            const rate = cost > 0 ? (profit / cost * 100).toFixed(2) : 0;
            document.getElementById('profitResult').innerHTML = `💰 预期利润: <strong>¥${profit.toFixed(2)}</strong> | 利润率: <strong>${rate}%</strong>`;
        }

        // 保存产品 (Fetch 逻辑)
        function saveProduct() {
            const form = document.getElementById('productForm');
            if (!form.checkValidity()) { form.reportValidity(); return; }

            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = '保存中...';

            fetch('product_save.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert('保存成功！');
                    window.location.href = 'products.php';
                } else {
                    alert('保存失败：' + data.message);
                    btn.disabled = false;
                    btn.textContent = '确认修改并保存';
                }
            })
            .catch(err => {
                alert('网络请求出错');
                btn.disabled = false;
            });
        }

        window.onload = function() {
            toggleType();
            calcProfit();
        };
    </script>
</body>
</html>