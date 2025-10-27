<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("数据库连接失败！");
}

echo "<h2>🔧 产品管理模块安装</h2><hr>";

// 检查字段是否存在
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// 1. 更新产品表字段
echo "<h3>1. 更新产品表字段</h3>";

$productColumns = [
    'product_type' => "ALTER TABLE `products` ADD COLUMN `product_type` varchar(50) DEFAULT 'hardware' COMMENT '产品类型 (hardware/device/software/service)' AFTER `category_id`",
    'supplier_name' => "ALTER TABLE `products` ADD COLUMN `supplier_name` varchar(255) DEFAULT NULL COMMENT '供应商名称' AFTER `spec`",
    'min_stock' => "ALTER TABLE `products` ADD COLUMN `min_stock` int(11) DEFAULT '10' COMMENT '最低库存预警' AFTER `stock_quantity`",
    'image_url' => "ALTER TABLE `products` ADD COLUMN `image_url` varchar(255) DEFAULT NULL COMMENT '产品图片' AFTER `compatibility_rules`"
];

foreach ($productColumns as $column => $sql) {
    if (columnExists($conn, 'products', $column)) {
        echo "<p style='color: orange;'>⚠ 字段 [$column] 已存在，跳过</p>";
    } else {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ 字段 [$column] 添加成功</p>";
        } else {
            echo "<p style='color: red;'>✗ 字段 [$column] 添加失败: " . $conn->error . "</p>";
        }
    }
}

// 2. 清空并重建产品分类
echo "<h3>2. 初始化产品分类</h3>";

$conn->query("TRUNCATE TABLE product_categories");

$categories = [
    // 硬件配件类
    ['id' => 1, 'parent_id' => 0, 'name' => '💻 硬件配件', 'sort' => 1],
    ['id' => 11, 'parent_id' => 1, 'name' => 'CPU处理器', 'sort' => 1],
    ['id' => 12, 'parent_id' => 1, 'name' => '主板', 'sort' => 2],
    ['id' => 13, 'parent_id' => 1, 'name' => '内存', 'sort' => 3],
    ['id' => 14, 'parent_id' => 1, 'name' => '硬盘/SSD', 'sort' => 4],
    ['id' => 15, 'parent_id' => 1, 'name' => '显卡', 'sort' => 5],
    ['id' => 16, 'parent_id' => 1, 'name' => '电源', 'sort' => 6],
    ['id' => 17, 'parent_id' => 1, 'name' => '机箱', 'sort' => 7],
    ['id' => 18, 'parent_id' => 1, 'name' => '散热器', 'sort' => 8],
    ['id' => 19, 'parent_id' => 1, 'name' => '其他配件', 'sort' => 9],
    
    // 整机设备类
    ['id' => 2, 'parent_id' => 0, 'name' => '🖥️ 整机设备', 'sort' => 2],
    ['id' => 21, 'parent_id' => 2, 'name' => '品牌台式机', 'sort' => 1],
    ['id' => 22, 'parent_id' => 2, 'name' => '笔记本电脑', 'sort' => 2],
    ['id' => 23, 'parent_id' => 2, 'name' => '一体机', 'sort' => 3],
    ['id' => 24, 'parent_id' => 2, 'name' => '服务器', 'sort' => 4],
    ['id' => 25, 'parent_id' => 2, 'name' => '工作站', 'sort' => 5],
    
    // 网络设备类
    ['id' => 3, 'parent_id' => 0, 'name' => '🌐 网络设备', 'sort' => 3],
    ['id' => 31, 'parent_id' => 3, 'name' => '交换机', 'sort' => 1],
    ['id' => 32, 'parent_id' => 3, 'name' => '路由器', 'sort' => 2],
    ['id' => 33, 'parent_id' => 3, 'name' => '无线AP', 'sort' => 3],
    ['id' => 34, 'parent_id' => 3, 'name' => '防火墙', 'sort' => 4],
    ['id' => 35, 'parent_id' => 3, 'name' => '网络存储NAS', 'sort' => 5],
    
    // 外设周边类
    ['id' => 4, 'parent_id' => 0, 'name' => '🖨️ 外设周边', 'sort' => 4],
    ['id' => 41, 'parent_id' => 4, 'name' => '显示器', 'sort' => 1],
    ['id' => 42, 'parent_id' => 4, 'name' => '打印机', 'sort' => 2],
    ['id' => 43, 'parent_id' => 4, 'name' => '扫描仪', 'sort' => 3],
    ['id' => 44, 'parent_id' => 4, 'name' => '投影仪', 'sort' => 4],
    ['id' => 45, 'parent_id' => 4, 'name' => '键鼠套装', 'sort' => 5],
    ['id' => 46, 'parent_id' => 4, 'name' => 'UPS电源', 'sort' => 6],
    
    // 监控安防类
    ['id' => 5, 'parent_id' => 0, 'name' => '📹 监控安防', 'sort' => 5],
    ['id' => 51, 'parent_id' => 5, 'name' => '摄像头', 'sort' => 1],
    ['id' => 52, 'parent_id' => 5, 'name' => '硬盘录像机', 'sort' => 2],
    ['id' => 53, 'parent_id' => 5, 'name' => '门禁系统', 'sort' => 3],
    ['id' => 54, 'parent_id' => 5, 'name' => '对讲系统', 'sort' => 4],
    
    // 软件类
    ['id' => 6, 'parent_id' => 0, 'name' => '💾 软件授权', 'sort' => 6],
    ['id' => 61, 'parent_id' => 6, 'name' => '操作系统', 'sort' => 1],
    ['id' => 62, 'parent_id' => 6, 'name' => 'Office办公软件', 'sort' => 2],
    ['id' => 63, 'parent_id' => 6, 'name' => '杀毒软件', 'sort' => 3],
    ['id' => 64, 'parent_id' => 6, 'name' => '设计软件', 'sort' => 4],
    ['id' => 65, 'parent_id' => 6, 'name' => '管理软件', 'sort' => 5],
    
    // 服务类
    ['id' => 7, 'parent_id' => 0, 'name' => '🔧 技术服务', 'sort' => 7],
    ['id' => 71, 'parent_id' => 7, 'name' => '上门安装', 'sort' => 1],
    ['id' => 72, 'parent_id' => 7, 'name' => '系统维护', 'sort' => 2],
    ['id' => 73, 'parent_id' => 7, 'name' => '数据恢复', 'sort' => 3],
    ['id' => 74, 'parent_id' => 7, 'name' => '网络布线', 'sort' => 4],
    ['id' => 75, 'parent_id' => 7, 'name' => '技术支持', 'sort' => 5],
];

$success = 0;
foreach ($categories as $cat) {
    $stmt = $conn->prepare("INSERT INTO product_categories (id, parent_id, name, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $cat['id'], $cat['parent_id'], $cat['name'], $cat['sort']);
    if ($stmt->execute()) {
        $success++;
    }
}

echo "<p style='color: green;'>✓ 成功创建 <strong>$success</strong> 个产品分类</p>";

// 3. 插入示例产品
echo "<h3>3. 插入示例产品数据</h3>";

$sample_products = [
    // 硬件配件
    ['cat' => 11, 'type' => 'hardware', 'sku' => 'CPU-001', 'name' => 'Intel i7-14700K', 'spec' => '20核28线程，最高5.6GHz', 'unit' => '个', 'cost' => 2999, 'price' => 3299, 'stock' => 15],
    ['cat' => 11, 'type' => 'hardware', 'sku' => 'CPU-002', 'name' => 'AMD Ryzen 7 7800X3D', 'spec' => '8核16线程，3D V-Cache', 'unit' => '个', 'cost' => 2799, 'price' => 3099, 'stock' => 12],
    ['cat' => 12, 'type' => 'hardware', 'sku' => 'MB-001', 'name' => '华硕 ROG STRIX Z790-E', 'spec' => 'ATX，DDR5，WiFi 6E', 'unit' => '块', 'cost' => 2199, 'price' => 2499, 'stock' => 8],
    ['cat' => 13, 'type' => 'hardware', 'sku' => 'RAM-001', 'name' => '金士顿 DDR5 32GB', 'spec' => '6000MHz，16GB×2', 'unit' => '套', 'cost' => 799, 'price' => 899, 'stock' => 25],
    ['cat' => 14, 'type' => 'hardware', 'sku' => 'SSD-001', 'name' => '三星 980 PRO 1TB', 'spec' => 'NVMe PCIe 4.0', 'unit' => '块', 'cost' => 699, 'price' => 799, 'stock' => 30],
    ['cat' => 15, 'type' => 'hardware', 'sku' => 'GPU-001', 'name' => 'RTX 4070 Ti', 'spec' => '12GB GDDR6X', 'unit' => '块', 'cost' => 5499, 'price' => 5999, 'stock' => 6],
    
    // 整机设备
    ['cat' => 21, 'type' => 'device', 'sku' => 'PC-001', 'name' => '戴尔 OptiPlex 7090', 'spec' => 'i7-11700/16G/512G SSD', 'unit' => '台', 'cost' => 4999, 'price' => 5499, 'stock' => 10],
    ['cat' => 22, 'type' => 'device', 'sku' => 'NB-001', 'name' => '联想 ThinkPad X1 Carbon', 'spec' => 'i7/16G/512G/14寸', 'unit' => '台', 'cost' => 8999, 'price' => 9999, 'stock' => 5],
    ['cat' => 24, 'type' => 'device', 'sku' => 'SVR-001', 'name' => '戴尔 PowerEdge R740', 'spec' => '双路至强/64G/4×2TB', 'unit' => '台', 'cost' => 28000, 'price' => 32000, 'stock' => 2],
    
    // 网络设备
    ['cat' => 31, 'type' => 'device', 'sku' => 'SW-001', 'name' => '思科 C9200-24T', 'spec' => '24口千兆三层交换机', 'unit' => '台', 'cost' => 11800, 'price' => 12800, 'stock' => 4],
    ['cat' => 33, 'type' => 'device', 'sku' => 'AP-001', 'name' => 'TP-LINK 企业级AP', 'spec' => 'WiFi 6，双频，POE供电', 'unit' => '个', 'cost' => 580, 'price' => 680, 'stock' => 20],
    ['cat' => 35, 'type' => 'device', 'sku' => 'NAS-001', 'name' => '群晖 DS920+', 'spec' => '4盘位NAS，含4×4TB硬盘', 'unit' => '台', 'cost' => 3899, 'price' => 4299, 'stock' => 3],
    
    // 外设周边
    ['cat' => 41, 'type' => 'device', 'sku' => 'MON-001', 'name' => '戴尔 27寸显示器', 'spec' => '2K IPS 75Hz', 'unit' => '台', 'cost' => 1699, 'price' => 1899, 'stock' => 15],
    ['cat' => 42, 'type' => 'device', 'sku' => 'PRT-001', 'name' => '惠普 LaserJet Pro', 'spec' => '黑白激光打印机', 'unit' => '台', 'cost' => 1599, 'price' => 1799, 'stock' => 8],
    ['cat' => 46, 'type' => 'device', 'sku' => 'UPS-001', 'name' => 'APC Smart-UPS 3000', 'spec' => '3000VA，在线式', 'unit' => '台', 'cost' => 7800, 'price' => 8500, 'stock' => 4],
    
    // 监控设备
    ['cat' => 51, 'type' => 'device', 'sku' => 'CAM-001', 'name' => '海康威视摄像头', 'spec' => '200万像素，H.265', 'unit' => '个', 'cost' => 380, 'price' => 450, 'stock' => 50],
    ['cat' => 52, 'type' => 'device', 'sku' => 'DVR-001', 'name' => '大华硬盘录像机', 'spec' => '16路，含4TB硬盘', 'unit' => '台', 'cost' => 2500, 'price' => 2800, 'stock' => 6],
    
    // 软件
    ['cat' => 61, 'type' => 'software', 'sku' => 'OS-001', 'name' => 'Windows 11 Pro', 'spec' => '专业版授权，永久使用', 'unit' => '套', 'cost' => 1200, 'price' => 1380, 'stock' => 100],
    ['cat' => 62, 'type' => 'software', 'sku' => 'OFF-001', 'name' => 'Office 2021 专业版', 'spec' => '包含Word/Excel/PPT等', 'unit' => '套', 'cost' => 2200, 'price' => 2499, 'stock' => 100],
    ['cat' => 63, 'type' => 'software', 'sku' => 'AV-001', 'name' => '卡巴斯基企业版', 'spec' => '10用户1年授权', 'unit' => '套', 'cost' => 800, 'price' => 980, 'stock' => 50],
    
    // 服务
    ['cat' => 71, 'type' => 'service', 'sku' => 'SVC-001', 'name' => '上门安装服务', 'spec' => '单台电脑系统安装调试', 'unit' => '次', 'cost' => 80, 'price' => 150, 'stock' => 999],
    ['cat' => 72, 'type' => 'service', 'sku' => 'SVC-002', 'name' => '系统维护服务', 'spec' => '定期维护，系统优化', 'unit' => '次', 'cost' => 150, 'price' => 280, 'stock' => 999],
    ['cat' => 73, 'type' => 'service', 'sku' => 'SVC-003', 'name' => '数据恢复服务', 'spec' => '硬盘/U盘数据恢复', 'unit' => '次', 'cost' => 300, 'price' => 500, 'stock' => 999],
    ['cat' => 74, 'type' => 'service', 'sku' => 'SVC-004', 'name' => '网络布线服务', 'spec' => '综合布线，每点位', 'unit' => '点', 'cost' => 120, 'price' => 200, 'stock' => 999],
];

$product_count = 0;
foreach ($sample_products as $p) {
    $stmt = $conn->prepare("
        INSERT INTO products 
        (category_id, product_type, sku, name, spec, unit, cost_price, default_price, stock_quantity, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");
    $stmt->bind_param("isssssddi", 
        $p['cat'], $p['type'], $p['sku'], $p['name'], $p['spec'], 
        $p['unit'], $p['cost'], $p['price'], $p['stock']
    );
    if ($stmt->execute()) {
        $product_count++;
    }
}

echo "<p style='color: green;'>✓ 成功创建 <strong>$product_count</strong> 个示例产品</p>";

$conn->close();

echo "<hr>";
echo "<h3>✅ 产品管理模块安装完成！</h3>";
echo "<p>已创建完整的产品分类体系：</p>";
echo "<ul>";
echo "<li>💻 硬件配件（CPU、主板、内存等9个子分类）</li>";
echo "<li>🖥️ 整机设备（品牌机、笔记本等5个子分类）</li>";
echo "<li>🌐 网络设备（交换机、路由器等5个子分类）</li>";
echo "<li>🖨️ 外设周边（显示器、打印机等6个子分类）</li>";
echo "<li>📹 监控安防（摄像头、录像机等4个子分类）</li>";
echo "<li>💾 软件授权（操作系统、办公软件等5个子分类）</li>";
echo "<li>🔧 技术服务（安装、维护等5个子分类）</li>";
echo "</ul>";
echo "<p><a href='products.php' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin-right: 10px;'>进入产品管理</a>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #f7fafc; border: 1px solid #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px;'>返回主页</a></p>";
?>