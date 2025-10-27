<?php
header('Content-Type: text/html; charset=utf-8');
require_once 'config.php';

$conn = getDBConnection();
if (!$conn) {
    die("数据库连接失败！");
}

echo "<h2>🔧 报价模块安装/更新</h2><hr>";

// 检查字段是否存在的函数
function columnExists($conn, $table, $column) {
    $result = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $result && $result->num_rows > 0;
}

// 1. 修改quotes表
echo "<h3>1. 更新报价单主表</h3>";

$quotesColumns = [
    'template_type' => "ALTER TABLE `quotes` ADD COLUMN `template_type` varchar(50) DEFAULT 'assembled_pc' COMMENT '模板类型' AFTER `quote_no`",
    'project_name' => "ALTER TABLE `quotes` ADD COLUMN `project_name` varchar(255) DEFAULT NULL COMMENT '项目名称' AFTER `customer_id`",
    'project_location' => "ALTER TABLE `quotes` ADD COLUMN `project_location` varchar(255) DEFAULT NULL COMMENT '项目地址' AFTER `project_name`",
    'construction_period' => "ALTER TABLE `quotes` ADD COLUMN `construction_period` varchar(100) DEFAULT NULL COMMENT '工期' AFTER `project_location`"
];

foreach ($quotesColumns as $column => $sql) {
    if (columnExists($conn, 'quotes', $column)) {
        echo "<p style='color: orange;'>⚠ 字段 [$column] 已存在，跳过</p>";
    } else {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ 字段 [$column] 添加成功</p>";
        } else {
            echo "<p style='color: red;'>✗ 字段 [$column] 添加失败: " . $conn->error . "</p>";
        }
    }
}

// 2. 修改quote_items表
echo "<h3>2. 更新报价单明细表</h3>";

$itemsColumns = [
    'category' => "ALTER TABLE `quote_items` ADD COLUMN `category` varchar(100) DEFAULT NULL COMMENT '分类名称' AFTER `seq`",
    'brand' => "ALTER TABLE `quote_items` ADD COLUMN `brand` varchar(100) DEFAULT NULL COMMENT '品牌' AFTER `product_name`",
    'model' => "ALTER TABLE `quote_items` ADD COLUMN `model` varchar(100) DEFAULT NULL COMMENT '型号' AFTER `brand`",
    'warranty' => "ALTER TABLE `quote_items` ADD COLUMN `warranty` varchar(100) DEFAULT NULL COMMENT '质保期限' AFTER `unit`",
    'remark' => "ALTER TABLE `quote_items` ADD COLUMN `remark` text COMMENT '备注说明' AFTER `custom_fields`"
];

foreach ($itemsColumns as $column => $sql) {
    if (columnExists($conn, 'quote_items', $column)) {
        echo "<p style='color: orange;'>⚠ 字段 [$column] 已存在，跳过</p>";
    } else {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ 字段 [$column] 添加成功</p>";
        } else {
            echo "<p style='color: red;'>✗ 字段 [$column] 添加失败: " . $conn->error . "</p>";
        }
    }
}

// 3. 创建报价模板表
echo "<h3>3. 创建报价模板表</h3>";

$createTemplates = "
CREATE TABLE IF NOT EXISTS `quote_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_code` varchar(50) NOT NULL COMMENT '模板代码',
  `template_name` varchar(100) NOT NULL COMMENT '模板名称',
  `template_type` varchar(50) NOT NULL COMMENT '模板类型',
  `description` text COMMENT '模板描述',
  `default_terms` text COMMENT '默认条款说明',
  `field_config` json COMMENT '字段配置',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_code` (`template_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='报价模板配置表'
";

if ($conn->query($createTemplates)) {
    echo "<p style='color: green;'>✓ 报价模板表创建成功或已存在</p>";
} else {
    echo "<p style='color: red;'>✗ 失败: " . $conn->error . "</p>";
}

// 4. 插入默认模板
echo "<h3>4. 插入默认模板数据</h3>";

$templates = [
    [
        'code' => 'assembled_pc',
        'name' => '组装电脑报价单',
        'type' => 'assembled_pc',
        'desc' => '适用于组装台式机、游戏主机等DIY配置',
        'terms' => "1. 所有配件均为全新正品\n2. 提供详细配置清单\n3. 免费组装调试\n4. 质保期按各配件厂商标准执行"
    ],
    [
        'code' => 'brand_pc',
        'name' => '品牌整机报价单',
        'type' => 'brand_pc',
        'desc' => '适用于品牌台式机、笔记本、一体机、服务器、网络设备等',
        'terms' => "1. 原厂正品，提供官方质保\n2. 全国联保服务\n3. 提供增值税发票\n4. 可提供上门安装服务"
    ],
    [
        'code' => 'weak_current',
        'name' => '弱电工程报价单',
        'type' => 'weak_current',
        'desc' => '适用于网络布线、监控系统、门禁系统、综合布线等弱电工程',
        'terms' => "1. 所有材料符合国家标准\n2. 施工符合规范要求\n3. 提供竣工图纸\n4. 质保期1年，终身维护\n5. 工期以实际情况为准"
    ],
    [
        'code' => 'strong_current',
        'name' => '强电工程报价单',
        'type' => 'strong_current',
        'desc' => '适用于电力安装、配电系统、照明工程等强电施工',
        'terms' => "1. 持证电工施工\n2. 材料符合国家3C标准\n3. 施工符合电力安装规范\n4. 提供验收报告\n5. 质保期2年\n6. 工期以合同约定为准"
    ]
];

foreach ($templates as $tpl) {
    $stmt = $conn->prepare("
        INSERT INTO quote_templates (template_code, template_name, template_type, description, default_terms)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            template_name = VALUES(template_name),
            description = VALUES(description),
            default_terms = VALUES(default_terms)
    ");
    
    $stmt->bind_param("sssss", $tpl['code'], $tpl['name'], $tpl['type'], $tpl['desc'], $tpl['terms']);
    
    if ($stmt->execute()) {
        echo "<p style='color: green;'>✓ 模板 [{$tpl['name']}] 安装成功</p>";
    } else {
        echo "<p style='color: red;'>✗ 模板 [{$tpl['name']}] 安装失败: " . $stmt->error . "</p>";
    }
}

// 5. 创建附件表
echo "<h3>5. 创建报价单附件表</h3>";

$createAttachments = "
CREATE TABLE IF NOT EXISTS `quote_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `quote_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL COMMENT '文件名',
  `file_path` varchar(255) NOT NULL COMMENT '文件路径',
  `file_type` varchar(50) DEFAULT NULL COMMENT '文件类型',
  `file_size` int(11) DEFAULT NULL COMMENT '文件大小',
  `uploaded_by` int(11) NOT NULL COMMENT '上传者ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_quote_id` (`quote_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='报价单附件表'
";

if ($conn->query($createAttachments)) {
    echo "<p style='color: green;'>✓ 附件表创建成功或已存在</p>";
} else {
    echo "<p style='color: red;'>✗ 失败: " . $conn->error . "</p>";
}

// 6. 检查并添加测试客户（如果没有客户数据）
echo "<h3>6. 检查测试数据</h3>";

$customer_count = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];

if ($customer_count == 0) {
    echo "<p style='color: orange;'>⚠ 没有客户数据，正在添加测试客户...</p>";
    
    $test_customer = "
        INSERT INTO customers (company_name, contact_name, phone, address)
        VALUES ('测试公司A', '张三', '13800138000', '广东省深圳市南山区')
    ";
    
    if ($conn->query($test_customer)) {
        echo "<p style='color: green;'>✓ 测试客户添加成功</p>";
    } else {
        echo "<p style='color: red;'>✗ 测试客户添加失败: " . $conn->error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ 已有 $customer_count 个客户数据</p>";
}

$conn->close();

echo "<hr>";
echo "<h3>✅ 报价模块安装完成！</h3>";
echo "<p><a href='quotes.php' style='display: inline-block; padding: 10px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; margin-right: 10px;'>进入报价管理</a>";
echo "<a href='index.php' style='display: inline-block; padding: 10px 20px; background: #f7fafc; border: 1px solid #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 8px;'>返回主页</a></p>";
?>