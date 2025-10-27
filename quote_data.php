<?php
/**
 * ============================================================================
 * 文件名: quote_data.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 获取报价单数据接口（用于送货单导入）
 * 
 * 功能说明：
 * 1. 根据报价单ID获取完整数据
 * 2. 包含客户信息
 * 3. 包含产品明细
 * 
 * GET 参数：
 * - id: 报价单ID（必需）
 * 
 * 返回 JSON：
 * {
 *   "success": true/false,
 *   "message": "提示信息",
 *   "customer_name": "客户名称",
 *   "contact_name": "联系人",
 *   "phone": "电话",
 *   "address": "地址",
 *   "items": [
 *     {
 *       "product_name": "产品名称",
 *       "spec": "规格",
 *       "quantity": 1,
 *       "unit": "件",
 *       "price": 100.00
 *     }
 *   ]
 * }
 * ============================================================================
 */

// ==================== 初始化 ====================
session_start();
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

// ==================== 权限验证 ====================
if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'success' => false,
        'message' => '请先登录'
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 获取参数 ====================
$quote_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($quote_id <= 0) {
    die(json_encode([
        'success' => false,
        'message' => '无效的报价单ID'
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 连接数据库 ====================
try {
    $conn = getDBConnection();
} catch (Exception $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
    die(json_encode([
        'success' => false,
        'message' => '数据库连接失败'
    ], JSON_UNESCAPED_UNICODE));
}

// ==================== 查询报价单基本信息 ====================
$quote_sql = "
    SELECT 
        q.id,
        q.quote_no,
        q.customer_id,
        c.company_name,
        c.contact_name,
        c.phone,
        c.address
    FROM quotes q
    LEFT JOIN customers c ON q.customer_id = c.id
    WHERE q.id = ?
";

$quote_stmt = $conn->prepare($quote_sql);
$quote_stmt->bind_param('i', $quote_id);
$quote_stmt->execute();
$quote_result = $quote_stmt->get_result();

if ($quote_result->num_rows === 0) {
    $quote_stmt->close();
    $conn->close();
    die(json_encode([
        'success' => false,
        'message' => '报价单不存在'
    ], JSON_UNESCAPED_UNICODE));
}

$quote = $quote_result->fetch_assoc();
$quote_stmt->close();

// ==================== 查询报价单明细 ====================
$items_sql = "
    SELECT 
        product_name,
        spec,
        quantity,
        unit,
        price,
        subtotal
    FROM quote_items
    WHERE quote_id = ?
    ORDER BY id
";

$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param('i', $quote_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$items = [];
while ($item = $items_result->fetch_assoc()) {
    $items[] = [
        'product_name' => $item['product_name'],
        'spec' => $item['spec'],
        'quantity' => intval($item['quantity']),
        'unit' => $item['unit'],
        'price' => floatval($item['price'])
    ];
}

$items_stmt->close();
$conn->close();

// ==================== 返回数据 ====================
echo json_encode([
    'success' => true,
    'customer_name' => $quote['company_name'] ?? '',
    'contact_name' => $quote['contact_name'] ?? '',
    'phone' => $quote['phone'] ?? '',
    'address' => $quote['address'] ?? '',
    'items' => $items
], JSON_UNESCAPED_UNICODE);
?>
```

---

## 文件清单（已完成）：

### ✅ 已完成的送货管理核心文件：

1. **deliveries.php** - 送货单列表页面
   - 筛选搜索
   - 统计看板
   - 分页显示
   - 货到付款标识

2. **delivery_add.php** - 新建送货单页面
   - 双来源（手动/报价单导入）
   - 客户信息管理
   - 动态产品明细
   - 费用自动计算
   - 货到付款选项

3. **delivery_save.php** - 保存处理
   - 新建送货单
   - 更新送货单
   - 送货明细
   - 应收记录
   - 送货单号生成

4. **delivery_delete.php** - 删除处理
   - 级联删除
   - 关联数据清理

5. **quote_data.php** - 报价单数据接口
   - 从报价单导入数据
   - 自动填充客户和产品信息

---

## 关键功能特性：

### ✅ 1. **送货单号自动生成**
```
格式：SH + 年月日 + 4位序号
示例：SH202510170001
```

### ✅ 2. **双来源创建**
- 📝 **手动创建**：直接输入客户和产品信息
- 📋 **从报价单导入**：选择已审核的报价单自动填充

### ✅ 3. **费用管理**
- 货物金额（自动从明细计算）
- 运费
- 总金额汇总
- 货到付款选项

### ✅ 4. **应收管理集成**
- 创建送货单自动生成应收记录
- 在 `delivery_payments` 表中管理
- 支持货到付款标记

### ✅ 5. **完整的数据追踪**
- 送货明细表
- 进度日志表
- 收款记录表

---

## 数据库关系：
```
deliveries (送货单主表)
    ├── delivery_items (送货明细)
    ├── delivery_logs (送货日志)
    └── delivery_payments (收款记录)
    
关联：
    deliveries.quote_id → quotes.id (可选)
    deliveries.customer_id → customers.id (可选)