<?php
// install_finance.php - 安装财务管理模块数据库表
header('Content-Type: text/html; charset=utf-8');

require_once 'config.php';

$conn = getDBConnection();

if (!$conn) {
    die("数据库连接失败！");
}

echo "<h2>🔧 财务管理模块安装</h2>";
echo "<hr>";

$tables = [
    'accounts_receivable' => "
        CREATE TABLE IF NOT EXISTS `accounts_receivable` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `customer_id` int(11) NOT NULL COMMENT '客户ID',
          `quote_id` int(11) DEFAULT NULL COMMENT '关联报价单ID',
          `bill_no` varchar(50) NOT NULL COMMENT '账单编号',
          `bill_date` date NOT NULL COMMENT '账单日期',
          `total_amount` decimal(10,2) NOT NULL COMMENT '应收总额',
          `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已收金额',
          `outstanding_amount` decimal(10,2) NOT NULL COMMENT '未收金额',
          `due_date` date DEFAULT NULL COMMENT '到期日期',
          `status` varchar(20) NOT NULL DEFAULT '未收款' COMMENT '状态',
          `notes` text COMMENT '备注',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `bill_no` (`bill_no`),
          KEY `idx_customer_id` (`customer_id`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应收账款表'
    ",
    'accounts_payable' => "
        CREATE TABLE IF NOT EXISTS `accounts_payable` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `supplier_name` varchar(255) NOT NULL COMMENT '供应商名称',
          `bill_no` varchar(50) NOT NULL COMMENT '账单编号',
          `bill_date` date NOT NULL COMMENT '账单日期',
          `total_amount` decimal(10,2) NOT NULL COMMENT '应付总额',
          `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已付金额',
          `outstanding_amount` decimal(10,2) NOT NULL COMMENT '未付金额',
          `due_date` date DEFAULT NULL COMMENT '到期日期',
          `status` varchar(20) NOT NULL DEFAULT '未付款' COMMENT '状态',
          `category` varchar(50) DEFAULT NULL COMMENT '费用类别',
          `notes` text COMMENT '备注',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `bill_no` (`bill_no`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应付账款表'
    ",
    'payment_records' => "
        CREATE TABLE IF NOT EXISTS `payment_records` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `record_no` varchar(50) NOT NULL COMMENT '记录编号',
          `payment_type` varchar(20) NOT NULL COMMENT '类型',
          `related_type` varchar(20) NOT NULL COMMENT '关联类型',
          `related_id` int(11) NOT NULL COMMENT '关联ID',
          `amount` decimal(10,2) NOT NULL COMMENT '金额',
          `payment_method` varchar(50) DEFAULT NULL COMMENT '付款方式',
          `payment_date` date NOT NULL COMMENT '收付款日期',
          `operator_id` int(11) NOT NULL COMMENT '操作员ID',
          `notes` text COMMENT '备注',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `record_no` (`record_no`),
          KEY `idx_related` (`related_type`, `related_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='收付款记录表'
    "
];

$success_count = 0;
$error_count = 0;

foreach ($tables as $table_name => $sql) {
    echo "<p>正在创建表：<strong>$table_name</strong> ... ";
    
    if ($conn->query($sql)) {
        echo "<span style='color: green;'>✓ 成功</span></p>";
        $success_count++;
    } else {
        echo "<span style='color: red;'>✗ 失败: " . $conn->error . "</span></p>";
        $error_count++;
    }
}

echo "<hr>";
echo "<h3>安装结果</h3>";
echo "<p>✓ 成功创建 <strong>$success_count</strong> 个表</p>";
if ($error_count > 0) {
    echo "<p>✗ 失败 <strong>$error_count</strong> 个表</p>";
}

// 插入测试数据
echo "<hr>";
echo "<h3>插入测试数据</h3>";

// 检查是否有客户数据
$customer_count = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];

if ($customer_count > 0) {
    // 获取第一个客户ID
    $first_customer = $conn->query("SELECT id FROM customers LIMIT 1")->fetch_assoc();
    $customer_id = $first_customer['id'];
    
    // 插入应收账款测试数据
    $test_receivable = "
        INSERT INTO accounts_receivable 
        (customer_id, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status, notes) 
        VALUES 
        ($customer_id, 'AR-2025001', '2025-01-15', 50000.00, 20000.00, 30000.00, '2025-02-15', '部分收款', '测试应收账款1'),
        ($customer_id, 'AR-2025002', '2025-01-20', 80000.00, 0.00, 80000.00, '2025-02-20', '未收款', '测试应收账款2')
        ON DUPLICATE KEY UPDATE id=id
    ";
    
    if ($conn->query($test_receivable)) {
        echo "<p>✓ 已插入应收账款测试数据</p>";
    }
}

// 插入应付账款测试数据
$test_payable = "
    INSERT INTO accounts_payable 
    (supplier_name, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status, category, notes) 
    VALUES 
    ('某某供应商', 'AP-2025001', '2025-01-10', 30000.00, 10000.00, 20000.00, '2025-02-10', '部分付款', '采购', '测试应付账款1'),
    ('其他供应商', 'AP-2025002', '2025-01-12', 15000.00, 0.00, 15000.00, '2025-02-12', '未付款', '租金', '测试应付账款2')
    ON DUPLICATE KEY UPDATE id=id
";

if ($conn->query($test_payable)) {
    echo "<p>✓ 已插入应付账款测试数据</p>";
}

$conn->close();

echo "<hr>";
echo "<h3>✅ 安装完成！</h3>";
echo "<p><a href='finance.php' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px;'>进入财务管理</a></p>";
echo "<p><a href='index.php' style='color: #667eea;'>返回主页</a></p>";
?>