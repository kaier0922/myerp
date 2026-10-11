<?php
session_start();
require_once __DIR__ . '/auth_helper.php';
require_super_admin();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    die('请先登录');
}

$conn = getDBConnection();

echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装销售合同模块</title>
    <link rel="stylesheet" href="common.css">
<style>
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1f6dad; margin-top: 0; }
        .success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #dbeafe; border: 1px solid #93c5fd; color: #135286; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn:hover { background: #5568d3; }
        pre { background: #f8fafc; padding: 10px; border-left: 3px solid #1f6dad; overflow-x: auto; }
        .step { margin: 20px 0; padding: 15px; background: #f8fafc; border-radius: 5px; }
        .step h3 { margin-top: 0; color: #2d3748; }
    </style>
</head>
<body>
<div class="container">
    <h1>📦 安装销售合同模块</h1>';

// 检查表是否已存在
$tables_exist = [];
$tables_to_check = ['contracts', 'contract_items'];

foreach ($tables_to_check as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    $tables_exist[$table] = ($result && $result->num_rows > 0);
}

$all_exist = !in_array(false, $tables_exist);

if ($all_exist) {
    echo '<div class="success">✓ 数据库表已存在，无需重复安装</div>';
    echo '<a href="contracts.php" class="btn">前往合同管理</a>';
    echo '<a href="index.php" class="btn" style="background: #718096; margin-left: 10px;">返回主页</a>';
} else {
    echo '<div class="info">
        <h3>安装说明</h3>
        <p>此脚本将创建以下数据库表：</p>
        <ul>
            <li><strong>contracts</strong> - 销售合同主表</li>
            <li><strong>contract_items</strong> - 合同明细表</li>
        </ul>
    </div>';
    
    // 显示当前状态
    echo '<div class="step">';
    echo '<h3>当前状态</h3>';
    foreach ($tables_exist as $table => $exists) {
        if ($exists) {
            echo "<div class='success'>✓ {$table} 表已存在</div>";
        } else {
            echo "<div class='error'>✗ {$table} 表不存在</div>";
        }
    }
    echo '</div>';
    
    // 执行安装
    if (isset($_POST['install'])) {
        echo '<div class="step"><h3>安装进度</h3>';
        
        $errors = [];
        
        try {
            // 创建 contracts 表
            if (!$tables_exist['contracts']) {
                $sql = "CREATE TABLE IF NOT EXISTS `contracts` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `contract_no` varchar(50) NOT NULL COMMENT '合同编号',
                  `customer_id` int(11) NOT NULL COMMENT '客户ID',
                  `contract_date` date NOT NULL COMMENT '合同日期',
                  `effective_date` date DEFAULT NULL COMMENT '生效日期',
                  `expiry_date` date DEFAULT NULL COMMENT '到期日期',
                  `total_amount` decimal(12,2) DEFAULT 0.00 COMMENT '合同总金额',
                  `payment_terms` text COMMENT '付款条款',
                  `delivery_terms` text COMMENT '交付条款',
                  `warranty_terms` text COMMENT '质保条款',
                  `other_terms` text COMMENT '其他条款',
                  `status` enum('draft','active','completed','cancelled') DEFAULT 'draft' COMMENT '状态',
                  `notes` text COMMENT '备注',
                  `created_by` int(11) DEFAULT NULL COMMENT '创建人',
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `contract_no` (`contract_no`),
                  KEY `idx_customer` (`customer_id`),
                  KEY `idx_date` (`contract_date`),
                  KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='销售合同表'";
                
                if ($conn->query($sql)) {
                    echo "<div class='success'>✓ contracts 表创建成功</div>";
                } else {
                    $errors[] = "创建 contracts 表失败：" . $conn->error;
                }
            }
            
            // 创建 contract_items 表
            if (!$tables_exist['contract_items']) {
                $sql = "CREATE TABLE IF NOT EXISTS `contract_items` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `contract_id` int(11) NOT NULL COMMENT '合同ID',
                  `seq` int(11) DEFAULT 1 COMMENT '序号',
                  `product_name` varchar(200) NOT NULL COMMENT '产品/服务名称',
                  `specification` text COMMENT '规格说明',
                  `unit` varchar(20) DEFAULT '项' COMMENT '单位',
                  `quantity` decimal(10,2) DEFAULT 1.00 COMMENT '数量',
                  `unit_price` decimal(12,2) DEFAULT 0.00 COMMENT '单价',
                  `amount` decimal(12,2) DEFAULT 0.00 COMMENT '金额',
                  `delivery_date` date DEFAULT NULL COMMENT '交付日期',
                  `notes` varchar(500) DEFAULT NULL COMMENT '备注',
                  PRIMARY KEY (`id`),
                  KEY `idx_contract` (`contract_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='合同明细表'";
                
                if ($conn->query($sql)) {
                    echo "<div class='success'>✓ contract_items 表创建成功</div>";
                } else {
                    $errors[] = "创建 contract_items 表失败：" . $conn->error;
                }
            }
            
            if (count($errors) > 0) {
                echo '<div class="error">';
                echo '<h4>发生错误：</h4><ul>';
                foreach ($errors as $error) {
                    echo "<li>{$error}</li>";
                }
                echo '</ul></div>';
            } else {
                echo '<div class="success"><h4>✓ 安装完成！</h4><p>所有数据库表已创建成功。</p></div>';
                echo '<a href="contracts.php" class="btn">前往合同管理</a>';
                echo '<a href="contract_create.php" class="btn" style="background: #1f6dad; margin-left: 10px;">创建第一份合同</a>';
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>安装失败：" . $e->getMessage() . "</div>";
        }
        
        echo '</div>';
    } else {
        // 显示安装按钮
        echo '<form method="POST">';
        echo '<button type="submit" name="install" class="btn" style="font-size: 16px; padding: 15px 30px;">🚀 开始安装</button>';
        echo '<a href="index.php" class="btn" style="background: #718096; margin-left: 10px;">取消</a>';
        echo '</form>';
    }
}

echo '</div>
</body>
</html>';

$conn->close();
?>
