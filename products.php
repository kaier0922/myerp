<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

// 获取筛选条件
$category_id = $_GET['category'] ?? '';
$product_type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// 构建查询
$where = ["p.is_active = 1"];
$params = [];
$types = '';

if ($category_id) {
    $where[] = "p.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

if ($product_type) {
    $where[] = "p.product_type = ?";
    $params[] = $product_type;
    $types .= 's';
}

if ($search) {
    $where[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.spec LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= 'sss';
}

$where_sql = implode(' AND ', $where);

// 获取产品列表
$sql = "
    SELECT p.*, pc.name as category_name
    FROM products p
    LEFT JOIN product_categories pc ON p.category_id = pc.id
    WHERE $where_sql
    ORDER BY p.created_at DESC
    LIMIT 100
";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// 获取统计数据
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN product_type = 'hardware' THEN 1 ELSE 0 END) as hardware,
        SUM(CASE WHEN product_type = 'device' THEN 1 ELSE 0 END) as device,
        SUM(CASE WHEN product_type = 'software' THEN 1 ELSE 0 END) as software,
        SUM(CASE WHEN product_type = 'service' THEN 1 ELSE 0 END) as service,
        SUM(CASE WHEN stock_quantity <= min_stock THEN 1 ELSE 0 END) as low_stock
    FROM products WHERE is_active = 1
")->fetch_assoc();

// 获取顶级分类
$categories = $conn->query("SELECT * FROM product_categories WHERE parent_id = 0 ORDER BY sort_order");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>产品管理 - 企业管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            text-decoration: none;
        }

        .navbar-brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .btn-back {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
        }

        .page-header {
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
        }

        .stat-value.warning {
            color: #f59e0b;
        }

        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            gap: 8px;
        }

        .filter-btn {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            color: #4a5568;
            transition: all 0.2s;
            text-decoration: none;
        }

        .filter-btn:hover, .filter-btn.active {
            background: #eef2ff;
            border-color: #667eea;
            color: #667eea;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .content-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 12px;
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tr:hover {
            background: #f7fafc;
        }

        .product-info {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .product-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .product-details {
            flex: 1;
        }

        .product-name {
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 2px;
        }

        .product-spec {
            font-size: 12px;
            color: #94a3b8;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge.hardware { background: #dbeafe; color: #1e3a8a; }
        .badge.device { background: #d1fae5; color: #065f46; }
        .badge.software { background: #fef3c7; color: #92400e; }
        .badge.service { background: #e9d5ff; color: #6b21a8; }

        .stock-badge {
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .stock-good { background: #d1fae5; color: #065f46; }
        .stock-low { background: #fed7aa; color: #92400e; }
        .stock-out { background: #fee2e2; color: #991b1b; }

        .price-cell {
            text-align: right;
        }

        .price-main {
            font-weight: 700;
            color: #1a202c;
        }

        .price-cost {
            font-size: 11px;
            color: #94a3b8;
        }

        .btn-group {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-sm:hover {
            background: #edf2f7;
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 16px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <div class="navbar-brand-icon">📊</div>
            <span>企业管理系统</span>
        </a>
        <a href="index.php" class="btn-back">← 返回主页</a>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">📦 产品管理</h1>
            <a href="product_add.php" class="btn-primary">+ 添加产品</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">产品总数</div>
                <div class="stat-value"><?php echo $stats['total'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">硬件配件</div>
                <div class="stat-value"><?php echo $stats['hardware'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">整机设备</div>
                <div class="stat-value"><?php echo $stats['device'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">软件授权</div>
                <div class="stat-value"><?php echo $stats['software'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">技术服务</div>
                <div class="stat-value"><?php echo $stats['service'] ?? 0; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">库存预警</div>
                <div class="stat-value warning"><?php echo $stats['low_stock'] ?? 0; ?></div>
            </div>
        </div>

        <div class="filter-section">
            <div class="filter-row">
                <div class="filter-group">
                    <a href="products.php" class="filter-btn <?php echo empty($product_type) ? 'active' : ''; ?>">全部</a>
                    <a href="?type=hardware" class="filter-btn <?php echo $product_type == 'hardware' ? 'active' : ''; ?>">💻 配件</a>
                    <a href="?type=device" class="filter-btn <?php echo $product_type == 'device' ? 'active' : ''; ?>">🖥️ 设备</a>
                    <a href="?type=software" class="filter-btn <?php echo $product_type == 'software' ? 'active' : ''; ?>">💾 软件</a>
                    <a href="?type=service" class="filter-btn <?php echo $product_type == 'service' ? 'active' : ''; ?>">🔧 服务</a>
                </div>

                <form method="GET" class="search-box" style="margin-left: auto;">
                    <?php if ($product_type): ?>
                        <input type="hidden" name="type" value="<?php echo $product_type; ?>">
                    <?php endif; ?>
                    <span class="search-icon">🔍</span>
                    <input type="text" name="search" class="search-input" placeholder="搜索产品名称、SKU、规格..." value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </div>

        <div class="content-card">
            <table>
                <thead>
                    <tr>
                        <th>产品信息</th>
                        <th>类型</th>
                        <th>SKU</th>
                        <th>分类</th>
                        <th>单位</th>
                        <th>库存</th>
                        <th style="text-align: right;">价格</th>
                        <th style="text-align: center;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($products->num_rows > 0): ?>
                        <?php 
                        $type_icons = [
                            'hardware' => '💻',
                            'device' => '🖥️',
                            'software' => '💾',
                            'service' => '🔧'
                        ];
                        $type_names = [
                            'hardware' => '配件',
                            'device' => '设备',
                            'software' => '软件',
                            'service' => '服务'
                        ];
                        ?>
                        <?php while ($p = $products->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <div class="product-info">
                                    <div class="product-icon"><?php echo $type_icons[$p['product_type']] ?? '📦'; ?></div>
                                    <div class="product-details">
                                        <div class="product-name"><?php echo htmlspecialchars($p['name']); ?></div>
                                        <?php if ($p['spec']): ?>
                                            <div class="product-spec"><?php echo htmlspecialchars($p['spec']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $p['product_type']; ?>">
                                    <?php echo $type_names[$p['product_type']] ?? $p['product_type']; ?>
                                </span>
                            </td>
                            <td><code><?php echo htmlspecialchars($p['sku']); ?></code></td>
                            <td><?php echo htmlspecialchars($p['category_name'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($p['unit']); ?></td>
                            <td>
                                <?php
                                $stock = $p['stock_quantity'];
                                $min = $p['min_stock'];
                                if ($stock == 0) {
                                    $class = 'stock-out';
                                    $text = '缺货';
                                } elseif ($stock <= $min) {
                                    $class = 'stock-low';
                                    $text = $stock;
                                } else {
                                    $class = 'stock-good';
                                    $text = $stock;
                                }
                                ?>
                                <span class="stock-badge <?php echo $class; ?>"><?php echo $text; ?></span>
                            </td>
                            <td class="price-cell">
                                <div class="price-main">¥<?php echo number_format($p['default_price'], 2); ?></div>
                                <div class="price-cost">成本 ¥<?php echo number_format($p['cost_price'], 2); ?></div>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="product_edit.php?id=<?php echo $p['id']; ?>" class="btn-sm">编辑</a>
                                    <button class="btn-sm" onclick="deleteProduct(<?php echo $p['id']; ?>)">删除</button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #a0aec0; padding: 40px;">
                                暂无产品数据，点击右上角"添加产品"开始创建
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function deleteProduct(id) {
            if (confirm('确定要删除这个产品吗？')) {
                // TODO: 实现删除功能
                alert('删除功能开发中... ID: ' + id);
            }
        }
    </script>
</body>
</html>