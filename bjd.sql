-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- 主机： localhost
-- 生成日期： 2025-10-23 22:33:19
-- 服务器版本： 8.0.35
-- PHP 版本： 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 数据库： `bjd`
--

DELIMITER $$
--
-- 存储过程
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `AddColumnIfNotExists` (IN `tableName` VARCHAR(100), IN `columnName` VARCHAR(100), IN `columnDefinition` VARCHAR(255))   BEGIN
    DECLARE column_count INT;
    
    SELECT COUNT(*) INTO column_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = tableName
    AND COLUMN_NAME = columnName;
    
    IF column_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE ', tableName, ' ADD COLUMN ', columnName, ' ', columnDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ 已添加字段: ', columnName) AS Result;
    ELSE
        SELECT CONCAT('- 字段已存在: ', columnName) AS Result;
    END IF;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- 表的结构 `accounts_payable`
--

CREATE TABLE `accounts_payable` (
  `id` int NOT NULL,
  `supplier_name` varchar(255) NOT NULL COMMENT '供应商名称',
  `bill_no` varchar(50) NOT NULL COMMENT '账单编号',
  `bill_date` date NOT NULL COMMENT '账单日期',
  `total_amount` decimal(10,2) NOT NULL COMMENT '应付总额',
  `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已付金额',
  `outstanding_amount` decimal(10,2) NOT NULL COMMENT '未付金额',
  `due_date` date DEFAULT NULL COMMENT '到期日期',
  `status` varchar(20) NOT NULL DEFAULT '未付款' COMMENT '状态 (未付款/部分付款/已付款/已逾期)',
  `category` varchar(50) DEFAULT NULL COMMENT '费用类别 (采购/租金/工资/其他)',
  `notes` text COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='应付账款表';

--
-- 转存表中的数据 `accounts_payable`
--

INSERT INTO `accounts_payable` (`id`, `supplier_name`, `bill_no`, `bill_date`, `total_amount`, `paid_amount`, `outstanding_amount`, `due_date`, `status`, `category`, `notes`, `created_at`, `updated_at`) VALUES
(1, '某某供应商', 'AP-2025001', '2025-01-10', 30000.00, 10000.00, 20000.00, '2025-02-10', '部分付款', '采购', '测试应付账款1', '2025-10-10 15:46:56', '2025-10-10 15:46:56'),
(2, '其他供应商', 'AP-2025002', '2025-01-12', 15000.00, 5000.00, 10000.00, '2025-02-12', '部分付款', '租金', '测试应付账款2', '2025-10-10 15:46:56', '2025-10-21 04:35:10'),
(3, '龙华陈鑫涛', 'AP-20251022-0001', '2025-10-22', 34409.00, 18000.00, 16409.00, NULL, '部分付款', '采购', '', '2025-10-22 06:25:06', '2025-10-22 06:25:36');

-- --------------------------------------------------------

--
-- 表的结构 `accounts_receivable`
--

CREATE TABLE `accounts_receivable` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL COMMENT '客户ID',
  `quote_id` int DEFAULT NULL COMMENT '关联报价单ID',
  `bill_no` varchar(50) NOT NULL COMMENT '账单编号',
  `bill_date` date NOT NULL COMMENT '账单日期',
  `total_amount` decimal(10,2) NOT NULL COMMENT '应收总额',
  `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已收金额',
  `outstanding_amount` decimal(10,2) NOT NULL COMMENT '未收金额',
  `due_date` date DEFAULT NULL COMMENT '到期日期',
  `status` varchar(20) NOT NULL DEFAULT '未收款' COMMENT '状态 (未收款/部分收款/已收款/已逾期)',
  `notes` text COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='应收账款表';

--
-- 转存表中的数据 `accounts_receivable`
--

INSERT INTO `accounts_receivable` (`id`, `customer_id`, `quote_id`, `bill_no`, `bill_date`, `total_amount`, `paid_amount`, `outstanding_amount`, `due_date`, `status`, `notes`, `created_at`, `updated_at`) VALUES
(1, 28, NULL, 'AR-20251021-0001', '2025-10-21', 300.00, 300.00, 0.00, NULL, '已收款', '', '2025-10-21 04:26:52', '2025-10-21 04:34:08'),
(2, 30, NULL, 'AR-20251022-0001', '2025-10-22', 3665.00, 0.00, 3665.00, NULL, '未收款', '', '2025-10-22 07:50:36', '2025-10-22 07:50:36'),
(3, 29, NULL, 'AR-20251022-0002', '2025-10-22', 7295.00, 0.00, 7295.00, NULL, '未收款', '监控工程', '2025-10-22 09:44:16', '2025-10-22 09:44:16'),
(4, 13, NULL, 'AR-20251022-0003', '2025-10-22', 4550.00, 0.00, 4550.00, NULL, '未收款', '广州黄埔', '2025-10-22 09:45:20', '2025-10-22 09:45:20'),
(5, 20, NULL, 'AR-20251023-0001', '2025-10-23', 150.00, 0.00, 150.00, NULL, '未收款', '九月份上门维修电脑', '2025-10-23 13:24:39', '2025-10-23 13:24:39'),
(6, 7, NULL, 'AR-20251023-0002', '2025-10-23', 250.00, 0.00, 250.00, NULL, '未收款', '一闪上门维修电脑  一次加240固态硬盘', '2025-10-23 13:25:22', '2025-10-23 13:25:22');

-- --------------------------------------------------------

--
-- 表的结构 `company_seals`
--

CREATE TABLE `company_seals` (
  `id` int NOT NULL,
  `user_id` int NOT NULL COMMENT '公章所属用户ID',
  `seal_name` varchar(100) NOT NULL COMMENT '公章名称',
  `seal_image` varchar(255) NOT NULL COMMENT '公章图片URL',
  `is_default` tinyint(1) DEFAULT '0' COMMENT '是否默认公章',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='电子公章表';

-- --------------------------------------------------------

--
-- 表的结构 `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL COMMENT '负责跟进的员工ID',
  `company_name` varchar(255) NOT NULL COMMENT '公司名称',
  `contact_name` varchar(100) NOT NULL COMMENT '联系人姓名',
  `phone` varchar(20) DEFAULT NULL COMMENT '联系人手机',
  `email` varchar(100) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL COMMENT '详细地址',
  `notes` text COMMENT '备注',
  `tags` json DEFAULT NULL COMMENT '客户标签，JSON格式',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='客户档案';

--
-- 转存表中的数据 `customers`
--

INSERT INTO `customers` (`id`, `user_id`, `company_name`, `contact_name`, `phone`, `email`, `address`, `notes`, `tags`, `created_at`, `updated_at`) VALUES
(1, NULL, '深圳灿宇星耀国际商贸有限公司', '王伟', '13800138001', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MAE467D350', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(2, NULL, '深圳鸿源创新技术有限公司', '李芳', '13912345678', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MADBMRDFXQ', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(3, NULL, '深圳市鼎轩供应链股份有限公司', '张军', '13620001001', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300788336393X', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(4, NULL, '深圳数贸云智人工智能有限公司', '赵丽', '13734567890', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MAED028Q3P', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(5, NULL, '深圳恒泰运供应链管理有限公司', '陈明', '13587654321', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5G8Q7427', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(6, NULL, '深圳市龙岗区生有五金塑胶制品厂', '刘涛', '13455554444', NULL, NULL, '统一社会信用代码/纳税人识别号: 92440300L058010965', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(7, NULL, '深圳市益思精密五金有限公司', '杨静', '18911110000', NULL, NULL, '统一社会信用代码/纳税人识别号: 9144030079542819XU', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(8, NULL, '深圳市菲炫电子科技有限公司', '黄磊', '18622221111', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300058965155E', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(9, NULL, '深圳市季霖环保科技有限公司', '周敏', '15933332222', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5DNYGF0F', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(10, NULL, '深圳市关贸云科技有限公司', '吴斌', '15844443333', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300356449722A', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(11, NULL, '红兔矢量实业（深圳）有限公司', '徐红', '13055556666', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5HX80Y1Q', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(12, NULL, '深圳巨航关务综合服务有限公司', '孙鹏', '13177778888', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300311872711X', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(13, NULL, '深圳市巨航国际物流有限公司', '胡兰', '13299990000', NULL, NULL, '统一社会信用代码/纳税人识别号: 914403007663509794', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(14, NULL, '深圳市坤辉达电子科技有限公司', '郭强', '15010010001', '', '', '统一社会信用代码/纳税人识别号: 914403003351782066', NULL, '2025-10-14 06:25:42', '2025-10-17 06:59:46'),
(16, NULL, '深圳市沧晟电子商务有限公司', '何伟', '15230304040', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5HWNTX3Y', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(17, NULL, '深圳市坂云科技有限公司', '高杰', '15340405050', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5FXK522M', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(18, NULL, '深圳市菲炫智能科技有限公司', '郑凯', '13350506060', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300058965155E', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(19, NULL, '深圳市锦上嘉科技有限公司', '梁燕', '13460607070', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300670021409A', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(20, NULL, '深圳市聚烽科技有限公司', '潘雷', '13570708080', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5FXY1Q12', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(21, NULL, '深圳市众锐智能有限公司', '冯涛', '13680809090', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MADGAWKD0C', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(22, NULL, '深圳市瑞克精密机床工具有限公司', '邓琳', '13790901010', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300595672315E', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(23, NULL, '深圳市南一国际物流有限公司', '曹阳', '13811122233', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5H8YXX8E', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(24, NULL, '广东艾瑞斯电器有限公司', '许芳', '13933344455', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5G2JM544', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(25, NULL, '深圳市龙峰源激业科技有限公司', '宋强', '15655566677', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MAEGL73H9D', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(26, NULL, '广州巨航国际物流有限公司', '唐艳', '18077788899', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440115MA59C2N60K', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(27, NULL, '深圳庭若精密科技有限公司', '韩军', '18199900011', NULL, NULL, '统一社会信用代码/纳税人识别号: 91440300MA5HLQC54A', NULL, '2025-10-14 06:25:42', '2025-10-14 06:25:42'),
(28, NULL, '同行', '同行', '13316973369', '', '', '', NULL, '2025-10-15 02:43:42', '2025-10-22 01:48:57'),
(29, NULL, '风云塑胶科技（深圳）有限公司', '罗总', '13417575113', '', '', '91440300MA5EC2R28Q', NULL, '2025-10-22 01:48:41', '2025-10-22 01:48:41'),
(30, NULL, '深圳市鑫诚艺不锈钢有限公司', '李宪福', '13662671763', '13662671763@163.com', '', '91440300072548139K', NULL, '2025-10-22 07:49:37', '2025-10-22 07:49:37');

-- --------------------------------------------------------

--
-- 表的结构 `default_pc_configs`
--

CREATE TABLE `default_pc_configs` (
  `id` int NOT NULL,
  `config_name` varchar(100) NOT NULL COMMENT '配置名称',
  `config_type` varchar(50) DEFAULT 'basic' COMMENT '配置类型：basic/gaming/office/workstation',
  `cpu_category_id` int DEFAULT '11' COMMENT 'CPU分类ID',
  `is_active` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='默认电脑配置模板';

--
-- 转存表中的数据 `default_pc_configs`
--

INSERT INTO `default_pc_configs` (`id`, `config_name`, `config_type`, `cpu_category_id`, `is_active`, `created_at`) VALUES
(1, '标准办公配置', 'office', 11, 1, '2025-10-11 07:07:33'),
(2, '游戏配置', 'gaming', 11, 1, '2025-10-11 07:07:33'),
(3, '专业工作站', 'workstation', 11, 1, '2025-10-11 07:07:33');

-- --------------------------------------------------------

--
-- 表的结构 `deliveries`
--

CREATE TABLE `deliveries` (
  `id` int NOT NULL COMMENT '送货单ID',
  `delivery_no` varchar(50) NOT NULL COMMENT '送货单号',
  `quote_id` int DEFAULT NULL COMMENT '关联报价单ID',
  `customer_id` int DEFAULT NULL COMMENT '客户ID',
  `customer_name` varchar(100) NOT NULL COMMENT '客户名称',
  `contact_name` varchar(100) NOT NULL COMMENT '联系人',
  `contact_phone` varchar(20) NOT NULL COMMENT '联系电话',
  `delivery_address` varchar(500) NOT NULL COMMENT '送货地址',
  `delivery_date` date NOT NULL COMMENT '送货日期',
  `delivery_time` varchar(50) DEFAULT NULL COMMENT '送货时间段',
  `delivery_person` varchar(50) DEFAULT NULL COMMENT '送货人',
  `vehicle_no` varchar(50) DEFAULT NULL COMMENT '车牌号',
  `status` enum('pending','delivering','completed','failed','cancelled') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `goods_amount` decimal(10,2) DEFAULT '0.00' COMMENT '货物金额',
  `freight_fee` decimal(10,2) DEFAULT '0.00' COMMENT '运费',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '总金额',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '支付方式',
  `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已付金额',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid' COMMENT '支付状态',
  `collect_on_delivery` tinyint(1) DEFAULT '0' COMMENT '是否货到付款',
  `actual_delivery_time` datetime DEFAULT NULL COMMENT '实际送达时间',
  `recipient_name` varchar(100) DEFAULT NULL COMMENT '收货人',
  `recipient_signature` varchar(200) DEFAULT NULL COMMENT '签收图片',
  `notes` text COMMENT '备注',
  `created_by` int NOT NULL COMMENT '创建人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='送货单主表';

--
-- 转存表中的数据 `deliveries`
--

INSERT INTO `deliveries` (`id`, `delivery_no`, `quote_id`, `customer_id`, `customer_name`, `contact_name`, `contact_phone`, `delivery_address`, `delivery_date`, `delivery_time`, `delivery_person`, `vehicle_no`, `status`, `goods_amount`, `freight_fee`, `total_amount`, `payment_method`, `paid_amount`, `payment_status`, `collect_on_delivery`, `actual_delivery_time`, `recipient_name`, `recipient_signature`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'SH202510170001', NULL, NULL, 'Liu Kaier', 'Liu Kaier', '3316973303', '香港', '2025-10-17', '', '', '', 'pending', 2.00, 0.00, 2.00, '', 0.00, 'unpaid', 0, NULL, NULL, NULL, '', 1, '2025-10-17 03:44:08', '2025-10-21 03:16:18');

-- --------------------------------------------------------

--
-- 表的结构 `delivery_items`
--

CREATE TABLE `delivery_items` (
  `id` int NOT NULL COMMENT '明细ID',
  `delivery_id` int NOT NULL COMMENT '送货单ID',
  `product_name` varchar(200) NOT NULL COMMENT '产品名称',
  `product_spec` varchar(200) DEFAULT NULL COMMENT '规格型号',
  `quantity` int NOT NULL DEFAULT '1' COMMENT '数量',
  `unit` varchar(20) DEFAULT '件' COMMENT '单位',
  `unit_price` decimal(10,2) DEFAULT '0.00' COMMENT '单价',
  `subtotal` decimal(10,2) DEFAULT '0.00' COMMENT '小计',
  `actual_quantity` int DEFAULT NULL COMMENT '实际送达数量',
  `notes` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='送货明细表';

--
-- 转存表中的数据 `delivery_items`
--

INSERT INTO `delivery_items` (`id`, `delivery_id`, `product_name`, `product_spec`, `quantity`, `unit`, `unit_price`, `subtotal`, `actual_quantity`, `notes`, `created_at`) VALUES
(6, 1, '23434', '343434', 1, '件', 2323.00, 2323.00, NULL, NULL, '2025-10-21 03:16:18');

-- --------------------------------------------------------

--
-- 表的结构 `delivery_logs`
--

CREATE TABLE `delivery_logs` (
  `id` int NOT NULL COMMENT '日志ID',
  `delivery_id` int NOT NULL COMMENT '送货单ID',
  `status` varchar(50) NOT NULL COMMENT '状态',
  `description` text NOT NULL COMMENT '描述',
  `location` varchar(200) DEFAULT NULL COMMENT '位置',
  `operator` varchar(50) NOT NULL COMMENT '操作人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='送货日志表';

--
-- 转存表中的数据 `delivery_logs`
--

INSERT INTO `delivery_logs` (`id`, `delivery_id`, `status`, `description`, `location`, `operator`, `created_at`) VALUES
(1, 1, 'pending', '创建送货单', NULL, 'Liu Kaier', '2025-10-17 03:44:08'),
(2, 1, 'updated', '更新送货单信息', NULL, 'Liu Kaier', '2025-10-17 07:05:43'),
(3, 1, 'updated', '更新送货单信息', NULL, 'Liu Kaier', '2025-10-17 07:06:22'),
(4, 1, 'updated', '更新送货单信息', NULL, 'Liu Kaier', '2025-10-17 07:11:38'),
(5, 1, 'updated', '更新送货单信息', NULL, 'Liu Kaier', '2025-10-17 07:12:05'),
(6, 1, 'updated', '更新送货单信息', NULL, 'Liu Kaier', '2025-10-21 03:16:18');

-- --------------------------------------------------------

--
-- 表的结构 `delivery_orders`
--

CREATE TABLE `delivery_orders` (
  `id` int NOT NULL,
  `quote_id` int DEFAULT NULL COMMENT '关联的报价单ID',
  `delivery_no` varchar(50) NOT NULL COMMENT '送货单号',
  `customer_id` int NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT '待发货' COMMENT '状态 (待发货/部分发货/已签收)',
  `logistics_info` varchar(255) DEFAULT NULL COMMENT '物流单号/配送信息',
  `delivery_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='送货单主表';

-- --------------------------------------------------------

--
-- 表的结构 `delivery_order_items`
--

CREATE TABLE `delivery_order_items` (
  `id` int NOT NULL,
  `delivery_id` int NOT NULL,
  `product_id` int NOT NULL,
  `qty` int NOT NULL COMMENT '本次送货数量',
  `serial_numbers` text COMMENT '送货序列号 (逗号分隔或JSON)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='送货单明细表';

-- --------------------------------------------------------

--
-- 表的结构 `delivery_payments`
--

CREATE TABLE `delivery_payments` (
  `id` int NOT NULL COMMENT '收款ID',
  `delivery_id` int NOT NULL COMMENT '送货单ID',
  `payment_type` enum('receivable','received') NOT NULL DEFAULT 'received' COMMENT '类型：receivable=应收, received=已收',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '支付方式',
  `payment_date` datetime NOT NULL COMMENT '支付日期',
  `payee` varchar(100) DEFAULT NULL COMMENT '收款人',
  `notes` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` int NOT NULL COMMENT '创建人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='送货收款记录表';

--
-- 转存表中的数据 `delivery_payments`
--

INSERT INTO `delivery_payments` (`id`, `delivery_id`, `payment_type`, `amount`, `payment_method`, `payment_date`, `payee`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'receivable', 2323.00, NULL, '2025-10-17 03:44:08', NULL, '送货费用应收', 1, '2025-10-17 03:44:08');

-- --------------------------------------------------------

--
-- 表的结构 `document_seals`
--

CREATE TABLE `document_seals` (
  `id` int NOT NULL COMMENT 'ID',
  `document_type` varchar(50) NOT NULL COMMENT '文档类型（quotation/delivery）',
  `document_id` int NOT NULL COMMENT '文档ID',
  `seal_id` int NOT NULL COMMENT '公章ID',
  `position_x` int DEFAULT '0' COMMENT 'X坐标',
  `position_y` int DEFAULT '0' COMMENT 'Y坐标',
  `seal_size` int DEFAULT '100' COMMENT '公章大小（像素）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文档公章配置表';

-- --------------------------------------------------------

--
-- 表的结构 `industries`
--

CREATE TABLE `industries` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL COMMENT '行业名称 (如: 组装机, 弱电工程)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='报价行业分类表';

-- --------------------------------------------------------

--
-- 表的结构 `payment_records`
--

CREATE TABLE `payment_records` (
  `id` int NOT NULL,
  `record_no` varchar(50) NOT NULL COMMENT '记录编号',
  `payment_type` varchar(20) NOT NULL COMMENT '类型 (收款/付款)',
  `related_type` varchar(20) NOT NULL COMMENT '关联类型 (应收账款/应付账款)',
  `related_id` int NOT NULL COMMENT '关联ID',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '付款方式 (现金/转账/支票/其他)',
  `payment_date` date NOT NULL COMMENT '收付款日期',
  `operator_id` int NOT NULL COMMENT '操作员ID',
  `notes` text COMMENT '备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='收付款记录表';

--
-- 转存表中的数据 `payment_records`
--

INSERT INTO `payment_records` (`id`, `record_no`, `payment_type`, `related_type`, `related_id`, `amount`, `payment_method`, `payment_date`, `operator_id`, `notes`, `created_at`) VALUES
(1, 'RC-20251021123408-000001', '收款', '应收账款', 1, 300.00, '银行转账', '2025-10-21', 1, '', '2025-10-21 04:34:08'),
(2, 'PY-20251021123510-000002', '付款', '应付账款', 2, 5000.00, '现金', '2025-10-21', 1, '', '2025-10-21 04:35:10'),
(3, 'PY-20251022142536-000003', '付款', '应付账款', 3, 18000.00, '银行转账', '2025-10-22', 1, '', '2025-10-22 06:25:36');

-- --------------------------------------------------------

--
-- 表的结构 `products`
--

CREATE TABLE `products` (
  `id` int NOT NULL,
  `category_id` int NOT NULL COMMENT '分类ID',
  `product_type` varchar(50) DEFAULT 'hardware' COMMENT '产品类型 (hardware/device/software/service)',
  `sku` varchar(50) NOT NULL COMMENT '产品编号/SKU',
  `name` varchar(255) NOT NULL COMMENT '产品名称',
  `spec` varchar(255) DEFAULT NULL COMMENT '规格型号/详细参数',
  `supplier_name` varchar(255) DEFAULT NULL COMMENT '供应商名称',
  `unit` varchar(20) NOT NULL DEFAULT '个' COMMENT '计量单位',
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '基础成本价 (内部核算)',
  `default_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '标准销售价',
  `tags` json DEFAULT NULL COMMENT '兼容性标签，JSON格式 (如: ["Intel", "LGA1700", "DDR5"])',
  `compatibility_rules` json DEFAULT NULL COMMENT '兼容性规则，JSON格式',
  `image_url` varchar(255) DEFAULT NULL COMMENT '产品图片',
  `stock_quantity` int NOT NULL DEFAULT '0' COMMENT '库存数量',
  `min_stock` int DEFAULT '10' COMMENT '最低库存预警',
  `is_active` tinyint(1) DEFAULT '1',
  `created_by` int NOT NULL DEFAULT '0' COMMENT '创建人用户ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `platform_tags` json DEFAULT NULL COMMENT '兼容性标签数组,JSON格式'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='产品库表';

--
-- 转存表中的数据 `products`
--

INSERT INTO `products` (`id`, `category_id`, `product_type`, `sku`, `name`, `spec`, `supplier_name`, `unit`, `cost_price`, `default_price`, `tags`, `compatibility_rules`, `image_url`, `stock_quantity`, `min_stock`, `is_active`, `created_by`, `created_at`, `updated_at`, `platform_tags`) VALUES
(1, 11, 'hardware', 'CPU-001', 'Intel i7-14700K', '20核28线程，最高5.6GHz', NULL, '个', 2999.00, 3299.00, NULL, NULL, NULL, 15, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\"]'),
(2, 11, 'hardware', 'CPU-002', 'AMD Ryzen 7 7800X3D', '8核16线程，3D V-Cache', NULL, '个', 2799.00, 3099.00, NULL, NULL, NULL, 12, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(3, 12, 'hardware', 'MB-001', '华硕 ROG STRIX Z790-E', 'ATX，DDR5，WiFi 6E', NULL, '块', 2199.00, 2499.00, NULL, NULL, NULL, 8, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\"]'),
(4, 13, 'hardware', 'RAM-001', '金士顿 DDR5 32GB', '6000MHz，16GB×2', NULL, '套', 799.00, 899.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-11 07:07:33', '[\"DDR5\"]'),
(5, 14, 'hardware', 'SSD-001', '三星 980 PRO 1TB', 'NVMe PCIe 4.0', NULL, '块', 699.00, 799.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(6, 15, 'hardware', 'GPU-001', 'RTX 4070 Ti', '12GB GDDR6X', NULL, '块', 5499.00, 5999.00, NULL, NULL, NULL, 6, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(7, 21, 'device', 'PC-001', '戴尔 OptiPlex 7090', 'i7-11700/16G/512G SSD', NULL, '台', 4999.00, 5499.00, NULL, NULL, NULL, 10, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(8, 22, 'device', 'NB-001', '联想 ThinkPad X1 Carbon', 'i7/16G/512G/14寸', NULL, '台', 8999.00, 9999.00, NULL, NULL, NULL, 5, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(9, 24, 'device', 'SVR-001', '戴尔 PowerEdge R740', '双路至强/64G/4×2TB', NULL, '台', 28000.00, 32000.00, NULL, NULL, NULL, 2, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(10, 31, 'device', 'SW-001', '思科 C9200-24T', '24口千兆三层交换机', NULL, '台', 11800.00, 12800.00, NULL, NULL, NULL, 4, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(11, 33, 'device', 'AP-001', 'TP-LINK 企业级AP', 'WiFi 6，双频，POE供电', NULL, '个', 580.00, 680.00, NULL, NULL, NULL, 20, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(12, 35, 'device', 'NAS-001', '群晖 DS920+', '4盘位NAS，含4×4TB硬盘', NULL, '台', 3899.00, 4299.00, NULL, NULL, NULL, 3, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(13, 41, 'hardware', 'MON-001', '戴尔 27寸显示器', '2K IPS 75Hz', NULL, '台', 1699.00, 1899.00, NULL, NULL, NULL, 15, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-12 03:13:09', NULL),
(14, 42, 'device', 'PRT-001', '惠普 LaserJet Pro', '黑白激光打印机', NULL, '台', 1599.00, 1799.00, NULL, NULL, NULL, 8, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(15, 46, 'device', 'UPS-001', 'APC Smart-UPS 3000', '3000VA，在线式', NULL, '台', 7800.00, 8500.00, NULL, NULL, NULL, 4, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(16, 51, 'device', 'CAM-001', '海康威视摄像头', '200万像素，H.265', NULL, '个', 380.00, 450.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(17, 52, 'device', 'DVR-001', '大华硬盘录像机', '16路，含4TB硬盘', NULL, '台', 2500.00, 2800.00, NULL, NULL, NULL, 6, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(18, 61, 'software', 'OS-001', 'Windows 11 Pro', '专业版授权，永久使用', NULL, '套', 1200.00, 1380.00, NULL, NULL, NULL, 100, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(19, 62, 'software', 'OFF-001', 'Office 2021 专业版', '包含Word/Excel/PPT等', NULL, '套', 2200.00, 2499.00, NULL, NULL, NULL, 100, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(20, 63, 'software', 'AV-001', '卡巴斯基企业版', '10用户1年授权', NULL, '套', 800.00, 980.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(21, 71, 'service', 'SVC-001', '上门安装服务', '单台电脑系统安装调试', NULL, '次', 80.00, 150.00, NULL, NULL, NULL, 999, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(22, 72, 'service', 'SVC-002', '系统维护服务', '定期维护，系统优化', NULL, '次', 150.00, 280.00, NULL, NULL, NULL, 999, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(23, 73, 'service', 'SVC-003', '数据恢复服务', '硬盘/U盘数据恢复', NULL, '次', 300.00, 500.00, NULL, NULL, NULL, 999, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(24, 74, 'service', 'SVC-004', '网络布线服务', '综合布线，每点位', NULL, '点', 120.00, 200.00, NULL, NULL, NULL, 999, 10, 1, 0, '2025-10-10 16:37:40', '2025-10-10 16:37:40', NULL),
(25, 11, 'hardware', 'CPU-I5-13400F', 'Intel Core i5-13400F', '10核16线程 2.5-4.6GHz', NULL, '个', 1199.00, 1299.00, NULL, NULL, NULL, 20, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\", \"DDR4\"]'),
(26, 11, 'hardware', 'CPU-I5-14400F', 'Intel Core i5-14400F', '10核16线程 2.5-4.7GHz', NULL, '个', 1399.00, 1499.00, NULL, NULL, NULL, 15, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\", \"DDR4\"]'),
(27, 11, 'hardware', 'CPU-I7-13700F', 'Intel Core i7-13700F', '16核24线程 2.1-5.2GHz', NULL, '个', 2199.00, 2299.00, NULL, NULL, NULL, 10, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\", \"DDR4\"]'),
(28, 11, 'hardware', 'CPU-I9-14900K', 'Intel Core i9-14900K', '24核32线程 3.2-6.0GHz', NULL, '个', 4199.00, 4299.00, NULL, NULL, NULL, 5, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\"]'),
(29, 11, 'hardware', 'CPU-R5-7600X', 'AMD Ryzen 5 7600X', '6核12线程 4.7-5.3GHz', NULL, '个', 1499.00, 1599.00, NULL, NULL, NULL, 15, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(30, 11, 'hardware', 'CPU-R7-7800X3D', 'AMD Ryzen 7 7800X3D', '8核16线程 3D V-Cache', NULL, '个', 2799.00, 2899.00, NULL, NULL, NULL, 8, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(31, 11, 'hardware', 'CPU-R9-7950X', 'AMD Ryzen 9 7950X', '16核32线程 4.5-5.7GHz', NULL, '个', 4199.00, 4299.00, NULL, NULL, NULL, 5, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(32, 12, 'hardware', 'MB-ASUS-B760M-K', '华硕 PRIME B760M-K', 'M-ATX B760芯片组', NULL, '个', 649.00, 699.00, NULL, NULL, NULL, 12, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\"]'),
(33, 12, 'hardware', 'MB-MSI-B760M-A', '微星 PRO B760M-A', 'M-ATX B760芯片组', NULL, '个', 699.00, 749.00, NULL, NULL, NULL, 10, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\", \"DDR4\"]'),
(34, 12, 'hardware', 'MB-GIGABYTE-B760M', '技嘉 B760M AORUS ELITE', 'M-ATX B760芯片组', NULL, '个', 849.00, 899.00, NULL, NULL, NULL, 8, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\"]'),
(35, 12, 'hardware', 'MB-ASUS-Z790', '华硕 TUF GAMING Z790-PLUS', 'ATX Z790芯片组', NULL, '个', 1799.00, 1899.00, NULL, NULL, NULL, 6, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"Intel\", \"LGA1700\", \"DDR5\"]'),
(36, 12, 'hardware', 'MB-ASUS-B650M-A', '华硕 PRIME B650M-A', 'M-ATX B650芯片组', NULL, '个', 849.00, 899.00, NULL, NULL, NULL, 10, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(37, 12, 'hardware', 'MB-MSI-B650-P', '微星 PRO B650-P', 'ATX B650芯片组', NULL, '个', 949.00, 999.00, NULL, NULL, NULL, 8, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(38, 12, 'hardware', 'MB-GIGABYTE-X670', '技嘉 X670 AORUS ELITE', 'ATX X670芯片组', NULL, '个', 1699.00, 1799.00, NULL, NULL, NULL, 5, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"AMD\", \"AM5\", \"DDR5\"]'),
(39, 13, 'hardware', 'RAM-KINGSTON-DDR5-32G-5600', '金士顿 DDR5 32GB 5600MHz', '16GBx2 双通道', NULL, '套', 649.00, 699.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"DDR5\"]'),
(40, 13, 'hardware', 'RAM-ADATA-DDR5-32G-6000', '威刚 DDR5 32GB 6000MHz', '16GBx2 RGB', NULL, '套', 749.00, 799.00, NULL, NULL, NULL, 20, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"DDR5\"]'),
(41, 13, 'hardware', 'RAM-GLOWAY-DDR5-16G-5200', '光威 DDR5 16GB 5200MHz', '单条 16GB', NULL, '条', 279.00, 299.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"DDR5\"]'),
(42, 13, 'hardware', 'RAM-KINGSTON-DDR4-16G-3200', '金士顿 DDR4 16GB 3200MHz', '8GBx2 双通道', NULL, '套', 279.00, 299.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"DDR4\"]'),
(43, 13, 'hardware', 'RAM-ADATA-DDR4-32G-3600', '威刚 DDR4 32GB 3600MHz', '16GBx2 RGB', NULL, '套', 469.00, 499.00, NULL, NULL, NULL, 20, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', '[\"DDR4\"]'),
(44, 14, 'hardware', 'SSD-SAMSUNG-980PRO-1TB', '三星 980 PRO 1TB', 'NVMe PCIe 4.0', NULL, '个', 649.00, 699.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(45, 14, 'hardware', 'SSD-WD-SN850X-2TB', '西数 SN850X 2TB', 'NVMe PCIe 4.0', NULL, '个', 1249.00, 1299.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(46, 14, 'SSD-CRUCIAL-P3-1TB', 'SSD-CRUCIAL-P3-1TB', '英睿达 P3 1TB', 'NVMe PCIe 3.0', NULL, '个', 399.00, 449.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(47, 15, 'hardware', 'GPU-RTX4060TI-8G', 'RTX 4060 Ti 8GB', 'GDDR6 显存', NULL, '个', 3099.00, 3199.00, NULL, NULL, NULL, 15, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(48, 15, 'hardware', 'GPU-RTX4070-12G', 'RTX 4070 12GB', 'GDDR6X 显存', NULL, '个', 4599.00, 4699.00, NULL, NULL, NULL, 10, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(49, 15, 'hardware', 'GPU-RX7700XT-12G', 'RX 7700 XT 12GB', 'GDDR6 显存', NULL, '个', 3299.00, 3399.00, NULL, NULL, NULL, 12, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(50, 16, 'hardware', 'PSU-HUNTKEY-WD600K', '航嘉 WD600K', '600W 80Plus金牌', NULL, '个', 369.00, 399.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(51, 16, 'hardware', 'PSU-SEASONIC-GX750', '海韵 FOCUS GX-750', '750W 80Plus金牌全模组', NULL, '个', 669.00, 699.00, NULL, NULL, NULL, 20, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(52, 16, 'hardware', 'PSU-CORSAIR-RM850', '海盗船 RM850', '850W 80Plus金牌全模组', NULL, '个', 799.00, 849.00, NULL, NULL, NULL, 15, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(53, 17, 'hardware', 'CASE-SAMA-HD5', '先马 黑洞5', 'M-ATX 侧透机箱', NULL, '个', 179.00, 199.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(54, 17, 'hardware', 'CASE-CM-TD500', '酷冷至尊 TD500', 'ATX 侧透RGB', NULL, '个', 379.00, 399.00, NULL, NULL, NULL, 18, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(55, 18, 'hardware', 'COOLER-DF-AK400', '九州风神 AK400', '塔式散热器 4热管', NULL, '个', 79.00, 89.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(56, 18, 'hardware', 'COOLER-THERMALRIGHT-PA120', '利民 PA120', '双塔散热器', NULL, '个', 159.00, 169.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-11 07:07:33', '2025-10-11 07:07:33', NULL),
(65, 45, 'hardware', 'KB-001', '双飞燕 USB键鼠套装', '有线USB接口，人体工学设计，防溅水键盘，1000DPI鼠标', '双飞燕', '套', 45.00, 68.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 03:07:38', '2025-10-12 03:13:09', NULL),
(66, 45, 'hardware', 'KB-002', '雷柏 USB键鼠套装', '有线USB接口，静音按键，舒适手感，1200DPI鼠标', '雷柏', '套', 55.00, 78.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 03:07:38', '2025-10-12 03:13:09', NULL),
(67, 45, 'hardware', 'KB-003', '宏碁 USB键鼠套装', '有线USB接口，商务办公，巧克力键盘，1600DPI鼠标', '宏碁', '套', 65.00, 88.00, NULL, NULL, NULL, 35, 10, 1, 0, '2025-10-12 03:07:38', '2025-10-12 03:13:09', NULL),
(69, 45, 'hardware', 'KBM-001', '双飞燕 USB键鼠套装', '有线USB接口，人体工学设计，防溅水键盘，1000DPI鼠标', '双飞燕', '套', 45.00, 68.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', NULL),
(70, 45, 'hardware', 'KBM-002', '雷柏 USB键鼠套装', '有线USB接口，静音按键，舒适手感，1200DPI鼠标', '雷柏', '套', 55.00, 78.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', NULL),
(71, 45, 'hardware', 'KBM-003', '宏碁 USB键鼠套装', '有线USB接口，商务办公，巧克力键盘，1600DPI鼠标', '宏碁', '套', 65.00, 88.00, NULL, NULL, NULL, 35, 10, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', NULL),
(72, 41, 'hardware', 'DISP-001', 'AOC 22寸显示器', '21.5英寸 IPS屏幕 1920×1080分辨率 75Hz刷新率 HDMI+VGA接口 低蓝光不闪屏', 'AOC', '台', 550.00, 699.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', '[\"22寸\", \"1080P\", \"HDMI\", \"IPS\", \"75Hz\"]'),
(73, 41, 'hardware', 'DISP-002', 'AOC 24寸显示器', '23.8英寸 IPS屏幕 1920×1080分辨率 75Hz刷新率 HDMI+DP接口 窄边框设计', 'AOC', '台', 650.00, 799.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', '[\"24寸\", \"1080P\", \"HDMI\", \"IPS\", \"75Hz\"]'),
(74, 41, 'hardware', 'DISP-003', 'AOC 27寸 2K显示器', '27英寸 IPS屏幕 2560×1440分辨率 75Hz刷新率 HDMI+DP接口 HDR技术 支架可升降旋转', 'AOC', '台', 950.00, 1199.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', '[\"27寸\", \"2K\", \"HDMI\", \"IPS\", \"75Hz\", \"HDR\"]'),
(75, 41, 'hardware', 'DISP-004', 'AOC 32寸 2K显示器', '31.5英寸 VA屏幕 2560×1440分辨率 75Hz刷新率 HDMI×2+DP接口 1500R曲面屏 护眼模式', 'AOC', '台', 1350.00, 1699.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 03:09:15', '2025-10-12 03:13:09', '[\"32寸\", \"2K\", \"HDMI\", \"VA\", \"75Hz\", \"曲面\"]'),
(76, 11, 'hardware', 'CPU-1155-001', 'Intel i5-2400', '四核四线程 3.1GHz 最高3.4GHz 6MB三级缓存', 'Intel', '个', 180.00, 280.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"32nm\"]'),
(77, 11, 'hardware', 'CPU-1155-002', 'Intel i5-3470', '四核四线程 3.2GHz 最高3.6GHz 6MB三级缓存', 'Intel', '个', 220.00, 320.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"22nm\"]'),
(78, 11, 'hardware', 'CPU-1155-003', 'Intel i7-2600', '四核八线程 3.4GHz 最高3.8GHz 8MB三级缓存', 'Intel', '个', 280.00, 420.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"32nm\"]'),
(79, 11, 'hardware', 'CPU-1155-004', 'Intel i7-3770', '四核八线程 3.4GHz 最高3.9GHz 8MB三级缓存', 'Intel', '个', 350.00, 480.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"22nm\"]'),
(80, 11, 'hardware', 'CPU-1155-005', 'Intel Xeon E3-1225 V2', '四核四线程 3.2GHz 最高3.6GHz 8MB三级缓存 集成HD P4000显卡', 'Intel', '个', 200.00, 310.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"22nm\", \"E3\"]'),
(81, 12, 'hardware', 'MB-1155-001', '华硕 H61M-K', 'mATX 支持LGA1155 DDR3双通道 SATA3.0', '华硕', '个', 180.00, 250.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"H61\"]'),
(82, 12, 'hardware', 'MB-1155-002', '技嘉 B75M-D3V', 'mATX 支持LGA1155 DDR3双通道 USB3.0', '技嘉', '个', 220.00, 300.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"B75\"]'),
(83, 12, 'hardware', 'MB-1155-003', '华擎 Z77 Pro4', 'ATX 支持LGA1155 DDR3双通道 超频支持', '华擎', '个', 280.00, 380.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1155\", \"DDR3\", \"Z77\"]'),
(84, 11, 'hardware', 'CPU-1150-001', 'Intel i5-4460', '四核四线程 3.2GHz 最高3.4GHz 6MB三级缓存', 'Intel', '个', 250.00, 350.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\"]'),
(85, 11, 'hardware', 'CPU-1150-002', 'Intel i5-4590', '四核四线程 3.3GHz 最高3.7GHz 6MB三级缓存', 'Intel', '个', 280.00, 380.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\"]'),
(86, 11, 'hardware', 'CPU-1150-003', 'Intel i7-4770', '四核八线程 3.4GHz 最高3.9GHz 8MB三级缓存', 'Intel', '个', 380.00, 520.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\"]'),
(87, 11, 'hardware', 'CPU-1150-004', 'Intel i7-4790K', '四核八线程 4.0GHz 最高4.4GHz 8MB三级缓存 超频版', 'Intel', '个', 480.00, 650.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\"]'),
(88, 11, 'hardware', 'CPU-1150-005', 'Intel Xeon E3-1225 V3', '四核四线程 3.2GHz 最高3.6GHz 8MB三级缓存 集成HD P4600显卡', 'Intel', '个', 260.00, 360.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\", \"E3\"]'),
(89, 11, 'hardware', 'CPU-1150-006', 'Intel Xeon E3-1226 V3', '四核四线程 3.3GHz 最高3.7GHz 8MB三级缓存 集成HD P4600显卡', 'Intel', '个', 280.00, 390.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\", \"E3\"]'),
(90, 11, 'hardware', 'CPU-1150-007', 'Intel Xeon E3-1246 V3', '四核八线程 3.5GHz 最高3.9GHz 8MB三级缓存 集成HD P4600显卡', 'Intel', '个', 380.00, 520.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"22nm\", \"E3\"]'),
(91, 12, 'hardware', 'MB-1150-001', '华硕 H81M-K', 'mATX 支持LGA1150 DDR3双通道 SATA3.0 USB3.0', '华硕', '个', 200.00, 280.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"H81\"]'),
(92, 12, 'hardware', 'MB-1150-002', '技嘉 B85M-D3H', 'mATX 支持LGA1150 DDR3双通道 USB3.0 千兆网卡', '技嘉', '个', 250.00, 350.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"B85\"]'),
(93, 12, 'hardware', 'MB-1150-003', '华擎 Z97 Pro4', 'ATX 支持LGA1150 DDR3双通道 超频支持 M.2接口', '华擎', '个', 320.00, 450.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"Z97\"]'),
(94, 12, 'hardware', 'MB-1150-004', '微星 B85-G43', 'ATX 支持LGA1150 DDR3双通道 USB3.0 军规用料', '微星', '个', 280.00, 380.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1150\", \"DDR3\", \"B85\"]'),
(95, 11, 'hardware', 'CPU-1151-001', 'Intel i5-6500', '四核四线程 3.2GHz 最高3.6GHz 6MB三级缓存', 'Intel', '个', 350.00, 480.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\"]'),
(96, 11, 'hardware', 'CPU-1151-002', 'Intel i5-7500', '四核四线程 3.4GHz 最高3.8GHz 6MB三级缓存', 'Intel', '个', 380.00, 520.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\"]'),
(97, 11, 'hardware', 'CPU-1151-003', 'Intel i7-6700', '四核八线程 3.4GHz 最高4.0GHz 8MB三级缓存', 'Intel', '个', 480.00, 650.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\"]'),
(98, 11, 'hardware', 'CPU-1151-004', 'Intel i7-7700K', '四核八线程 4.2GHz 最高4.5GHz 8MB三级缓存 超频版', 'Intel', '个', 650.00, 880.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\"]'),
(99, 11, 'hardware', 'CPU-1151-005', 'Intel Xeon E3-1225 V5', '四核四线程 3.3GHz 最高3.7GHz 8MB三级缓存 集成HD P530显卡', 'Intel', '个', 380.00, 510.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\", \"E3\"]'),
(100, 11, 'hardware', 'CPU-1151-006', 'Intel Xeon E3-1245 V5', '四核八线程 3.5GHz 最高3.9GHz 8MB三级缓存 集成HD P530显卡', 'Intel', '个', 520.00, 680.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\", \"E3\"]'),
(101, 11, 'hardware', 'CPU-1151-007', 'Intel Xeon E3-1230 V6', '四核八线程 3.5GHz 最高3.9GHz 8MB三级缓存 无核显', 'Intel', '个', 450.00, 600.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"14nm\", \"E3\"]'),
(102, 12, 'hardware', 'MB-1151-001', '华硕 H110M-K', 'mATX 支持LGA1151 DDR4双通道 USB3.0', '华硕', '个', 250.00, 350.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"H110\"]'),
(103, 12, 'hardware', 'MB-1151-002', '技嘉 B150M-D3H', 'mATX 支持LGA1151 DDR4双通道 USB3.0 M.2接口', '技嘉', '个', 320.00, 450.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"B150\"]'),
(104, 12, 'hardware', 'MB-1151-003', '华硕 B250M-PLUS', 'mATX 支持LGA1151 DDR4双通道 USB3.1 M.2接口', '华硕', '个', 380.00, 520.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"B250\"]'),
(105, 12, 'hardware', 'MB-1151-004', '微星 Z170A Gaming Pro', 'ATX 支持LGA1151 DDR4双通道 超频支持 RGB灯效', '微星', '个', 480.00, 650.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"Z170\"]'),
(106, 12, 'hardware', 'MB-1151-005', '华擎 Z270 Pro4', 'ATX 支持LGA1151 DDR4双通道 超频支持 双M.2', '华擎', '个', 520.00, 680.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"LGA1151\", \"DDR4\", \"Z270\"]'),
(107, 13, 'hardware', 'RAM-DDR3-001', '金士顿 DDR3 1600MHz 4GB', '台式机内存 单条4GB 1.5V电压', '金士顿', '条', 45.00, 68.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR3\", \"1600MHz\", \"4GB\"]'),
(108, 13, 'hardware', 'RAM-DDR3-002', '金士顿 DDR3 1600MHz 8GB', '台式机内存 单条8GB 1.5V电压', '金士顿', '条', 85.00, 120.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(109, 13, 'hardware', 'RAM-DDR3-003', '威刚 DDR3 1600MHz 8GB', '台式机内存 单条8GB 万紫千红系列', '威刚', '条', 80.00, 115.00, NULL, NULL, NULL, 35, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(110, 13, 'hardware', 'RAM-DDR3-004', '海盗船 DDR3 1600MHz 8GB', '复仇者系列 单条8GB 散热马甲', '海盗船', '条', 95.00, 135.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(111, 13, 'hardware', 'RAM-DDR3-005', '金士顿 DDR3 1600MHz 16GB套装', '台式机内存 8GBx2 双通道套装', '金士顿', '套', 165.00, 230.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR3\", \"1600MHz\", \"16GB\", \"双通道\"]'),
(112, 13, 'hardware', 'RAM-DDR4-001', '金士顿 DDR4 2400MHz 8GB', '台式机内存 单条8GB 1.2V电压', '金士顿', '条', 95.00, 135.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR4\", \"2400MHz\", \"8GB\"]'),
(113, 13, 'hardware', 'RAM-DDR4-002', '金士顿 DDR4 2666MHz 8GB', '台式机内存 单条8GB 骇客神条', '金士顿', '条', 105.00, 145.00, NULL, NULL, NULL, 45, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR4\", \"2666MHz\", \"8GB\"]'),
(114, 13, 'hardware', 'RAM-DDR4-003', '威刚 DDR4 3200MHz 8GB', 'XPG威龙系列 单条8GB 灯条', '威刚', '条', 115.00, 160.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR4\", \"3200MHz\", \"8GB\"]'),
(115, 13, 'hardware', 'RAM-DDR4-004', '金士顿 DDR4 3200MHz 16GB套装', '骇客神条 8GBx2 双通道套装 RGB灯效', '金士顿', '套', 220.00, 310.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR4\", \"3200MHz\", \"16GB\", \"双通道\"]'),
(116, 13, 'hardware', 'RAM-DDR4-005', '海盗船 DDR4 3600MHz 16GB套装', '复仇者RGB 8GBx2 超频内存', '海盗船', '套', 280.00, 390.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-12 03:42:58', '2025-10-12 03:42:58', '[\"DDR4\", \"3600MHz\", \"16GB\", \"双通道\"]'),
(117, 16, 'hardware', 'PSU-GW-001', '长城电源 200W', '额定200W 被动式PFC 双12V 80PLUS白牌', '长城', '个', 80.00, 120.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(118, 16, 'hardware', 'PSU-GW-002', '长城电源 300W', '额定300W 主动式PFC 双12V 80PLUS白牌', '长城', '个', 100.00, 150.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(119, 16, 'hardware', 'PSU-GW-003', '长城电源 400W', '额定400W 主动式PFC 双12V 80PLUS铜牌', '长城', '个', 130.00, 190.00, NULL, NULL, NULL, 40, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(120, 16, 'hardware', 'PSU-GW-004', '长城电源 500W', '额定500W 主动式PFC 双12V 80PLUS铜牌', '长城', '个', 160.00, 230.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(121, 16, 'hardware', 'PSU-GW-005', '长城电源 600W', '额定600W 主动式PFC 双12V 80PLUS铜牌 模组化', '长城', '个', 200.00, 280.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(122, 16, 'hardware', 'PSU-GW-006', '长城电源 700W', '额定700W 主动式PFC 双12V 80PLUS金牌 全模组', '长城', '个', 280.00, 380.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(123, 16, 'hardware', 'PSU-ATX-001', 'ATX电源 300W', '额定300W 被动式PFC 台式机通用电源', '无品牌', '个', 60.00, 95.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(124, 16, 'hardware', 'PSU-ATX-002', 'ATX电源 500W', '额定500W 主动式PFC 静音风扇', '无品牌', '个', 95.00, 145.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(125, 18, 'hardware', 'COOL-001', '四铜管横向散热器', '4热管 9cm风扇 支持Intel/AMD多平台 TDP 130W', '九州风神', '个', 35.00, 55.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(126, 18, 'hardware', 'COOL-002', '六铜管横向散热器', '6热管 12cm风扇 RGB灯效 TDP 180W 支持多平台', '酷冷至尊', '个', 65.00, 95.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(127, 18, 'hardware', 'COOL-003', '双铜管下压式散热器', '2热管 8cm风扇 静音设计 TDP 95W', '超频三', '个', 20.00, 35.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(128, 17, 'hardware', 'CASE-001', '宏基小机箱', 'Mini-ITX 小机箱 支持短显卡 USB3.0前置 带电源位', '宏基', '个', 80.00, 135.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(129, 17, 'hardware', 'CASE-002', '长城普通机箱', 'ATX中塔 支持长显卡 前置USB3.0 侧透设计', '长城', '个', 95.00, 150.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(130, 17, 'hardware', 'CASE-003', '爱国者炫影机箱', 'ATX中塔 RGB风扇 钢化玻璃侧透 背线设计', '爱国者', '个', 130.00, 198.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(131, 15, 'hardware', 'GPU-GT-001', 'GT710 1GB显卡', '1GB DDR3 64bit 静音散热 支持DVI/HDMI', '影驰', '个', 180.00, 258.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(132, 15, 'hardware', 'GPU-GT-002', 'GT720 2GB显卡', '2GB DDR3 64bit 低功耗设计 HDMI输出', '七彩虹', '个', 220.00, 298.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(133, 15, 'hardware', 'GPU-GT-003', 'GT730 2GB显卡', '2GB GDDR5 64bit 独立散热 办公游戏', '铭瑄', '个', 280.00, 368.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(134, 15, 'hardware', 'GPU-GTX-001', 'GTX750 1GB显卡', '1GB GDDR5 128bit Maxwell架构 低功耗', '索泰', '个', 350.00, 468.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(135, 15, 'hardware', 'GPU-GTX-002', 'GTX750Ti 2GB显卡', '2GB GDDR5 128bit 双风扇散热 性能级', '影驰', '个', 450.00, 598.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(136, 15, 'hardware', 'GPU-GTX-003', 'GT1030 2GB显卡', '2GB GDDR5 64bit Pascal架构 低功耗', '七彩虹', '个', 420.00, 568.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(137, 15, 'hardware', 'GPU-GTX-004', 'GTX1050 2GB显卡', '2GB GDDR5 128bit 单6pin供电 入门游戏', '华硕', '个', 650.00, 868.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(138, 15, 'hardware', 'GPU-GTX-005', 'GTX1050Ti 4GB显卡', '4GB GDDR5 128bit 双风扇 主流游戏', '微星', '个', 850.00, 1138.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(139, 15, 'hardware', 'GPU-GTX-006', 'GTX1060 3GB显卡', '3GB GDDR5 192bit 单8pin 1080P高特效', '技嘉', '个', 980.00, 1298.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(140, 15, 'hardware', 'GPU-GTX-007', 'GTX1060 6GB显卡', '6GB GDDR5 192bit 双风扇 2K游戏', '华硕', '个', 1280.00, 1698.00, NULL, NULL, NULL, 5, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(141, 15, 'hardware', 'GPU-RTX-001', 'RTX2060 6GB显卡', '6GB GDDR6 192bit 光追支持 2K高帧', '七彩虹', '个', 1680.00, 2198.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(142, 15, 'hardware', 'GPU-RTX-002', 'RTX2060 Super 8GB显卡', '8GB GDDR6 256bit 增强光追 高性能', '华硕', '个', 2080.00, 2698.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(143, 15, 'hardware', 'GPU-RTX30-001', 'RTX3050 8GB显卡', '8GB GDDR6 128bit DLSS3.0 1080P光追', '微星', '个', 1480.00, 1998.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(144, 15, 'hardware', 'GPU-RTX30-002', 'RTX3060 12GB显卡', '12GB GDDR6 192bit 三风扇 2K光追游戏', '技嘉', '个', 2180.00, 2898.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(145, 15, 'hardware', 'GPU-RTX30-003', 'RTX3060Ti 8GB显卡', '8GB GDDR6 256bit 高性能光追 2K/4K', '华硕', '个', 2680.00, 3498.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(146, 19, 'hardware', 'HDD-ST-001', '希捷机械硬盘 1TB', '7200转 64MB缓存 SATA3接口 台式机硬盘', '希捷', '个', 180.00, 258.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(147, 19, 'hardware', 'HDD-ST-002', '希捷机械硬盘 2TB', '7200转 256MB缓存 SATA3接口 酷鱼系列', '希捷', '个', 280.00, 388.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(148, 19, 'hardware', 'HDD-ST-003', '希捷机械硬盘 4TB', '5400转 256MB缓存 SATA3接口 监控级', '希捷', '个', 480.00, 658.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(149, 19, 'hardware', 'HDD-ST-004', '希捷机械硬盘 8TB', '7200转 256MB缓存 SATA3接口 企业级', '希捷', '个', 980.00, 1358.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(150, 19, 'hardware', 'HDD-WD-001', '西数机械硬盘 1TB', '7200转 64MB缓存 SATA3接口 蓝盘', '西部数据', '个', 185.00, 268.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(151, 19, 'hardware', 'HDD-WD-002', '西数机械硬盘 2TB', '7200转 256MB缓存 SATA3接口 蓝盘', '西部数据', '个', 285.00, 398.00, NULL, NULL, NULL, 28, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(152, 19, 'hardware', 'HDD-WD-003', '西数机械硬盘 4TB', '5400转 256MB缓存 SATA3接口 紫盘监控', '西部数据', '个', 520.00, 698.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(153, 19, 'hardware', 'HDD-WD-004', '西数机械硬盘 8TB', '7200转 256MB缓存 SATA3接口 红盘NAS', '西部数据', '个', 1080.00, 1498.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(154, 14, 'hardware', 'SSD-ST-001', '希捷固态硬盘 256GB', 'SATA3接口 读550MB/s 写500MB/s 2.5英寸', '希捷', '个', 150.00, 218.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(155, 14, 'hardware', 'SSD-ST-002', '希捷固态硬盘 512GB', 'SATA3接口 读560MB/s 写530MB/s 酷鱼系列', '希捷', '个', 260.00, 358.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(156, 14, 'hardware', 'SSD-ST-003', '希捷固态硬盘 1TB', 'NVMe M.2 读3500MB/s 写3000MB/s PCIe 3.0', '希捷', '个', 450.00, 618.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(157, 14, 'hardware', 'SSD-WD-001', '西数固态硬盘 256GB', 'SATA3接口 读545MB/s 写500MB/s 绿盘', '西部数据', '个', 155.00, 228.00, NULL, NULL, NULL, 28, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(158, 14, 'hardware', 'SSD-WD-002', '西数固态硬盘 512GB', 'NVMe M.2 读3400MB/s 写2900MB/s 蓝盘SN570', '西部数据', '个', 280.00, 388.00, NULL, NULL, NULL, 22, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(159, 14, 'hardware', 'SSD-WD-003', '西数固态硬盘 1TB', 'NVMe M.2 读5000MB/s 写4500MB/s 黑盘SN850', '西部数据', '个', 520.00, 718.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(160, 14, 'hardware', 'SSD-KS-001', '金士顿固态硬盘 240GB', 'SATA3接口 读550MB/s 写490MB/s A400系列', '金士顿', '个', 140.00, 198.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(161, 14, 'hardware', 'SSD-KS-002', '金士顿固态硬盘 480GB', 'SATA3接口 读560MB/s 写530MB/s UV500系列', '金士顿', '个', 240.00, 338.00, NULL, NULL, NULL, 28, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(162, 14, 'hardware', 'SSD-KS-003', '金士顿固态硬盘 1TB', 'NVMe M.2 读3500MB/s 写2900MB/s NV2系列', '金士顿', '个', 420.00, 588.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(163, 14, 'hardware', 'SSD-AC-001', '宏基固态硬盘 256GB', 'SATA3接口 读520MB/s 写480MB/s 2.5英寸', '宏基', '个', 130.00, 188.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(164, 14, 'hardware', 'SSD-AC-002', '宏基固态硬盘 512GB', 'NVMe M.2 读2400MB/s 写1800MB/s PCIe 3.0', '宏基', '个', 230.00, 318.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(165, 14, 'hardware', 'SSD-GW-001', '长城固态硬盘 240GB', 'SATA3接口 读530MB/s 写470MB/s 国产颗粒', '长城', '个', 125.00, 178.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(166, 14, 'hardware', 'SSD-GW-002', '长城固态硬盘 480GB', 'SATA3接口 读550MB/s 写510MB/s 性价比款', '长城', '个', 220.00, 308.00, NULL, NULL, NULL, 28, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', NULL),
(167, 13, 'hardware', 'RAM-LN-001', '联想 DDR3 1600MHz 8GB', '台式机内存 单条8GB 兼容性强', '联想', '条', 75.00, 108.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(168, 13, 'hardware', 'RAM-LN-002', '联想 DDR4 2666MHz 8GB', '台式机内存 单条8GB 原厂颗粒', '联想', '条', 98.00, 138.00, NULL, NULL, NULL, 35, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"2666MHz\", \"8GB\"]'),
(169, 13, 'hardware', 'RAM-HY-001', '海力士 DDR3 1600MHz 8GB', '台式机内存 单条8GB 原厂颗粒', '海力士', '条', 78.00, 112.00, NULL, NULL, NULL, 45, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(170, 13, 'hardware', 'RAM-HY-002', '海力士 DDR4 2666MHz 8GB', '台式机内存 单条8GB 高品质', '海力士', '条', 95.00, 135.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"2666MHz\", \"8GB\"]'),
(171, 13, 'hardware', 'RAM-HY-003', '海力士 DDR4 3200MHz 16GB', '台式机内存 单条16GB 大容量', '海力士', '条', 210.00, 295.00, NULL, NULL, NULL, 25, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"3200MHz\", \"16GB\"]'),
(172, 13, 'hardware', 'RAM-MC-001', '美光 DDR3 1600MHz 8GB', '台式机内存 单条8GB 英睿达系列', '美光', '条', 80.00, 115.00, NULL, NULL, NULL, 38, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(173, 13, 'hardware', 'RAM-MC-002', '美光 DDR4 2666MHz 8GB', '台式机内存 单条8GB 英睿达系列', '美光', '条', 92.00, 132.00, NULL, NULL, NULL, 35, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"2666MHz\", \"8GB\"]'),
(174, 13, 'hardware', 'RAM-MC-003', '美光 DDR4 3200MHz 8GB', '台式机内存 单条8GB 高频率', '美光', '条', 108.00, 148.00, NULL, NULL, NULL, 30, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"3200MHz\", \"8GB\"]'),
(175, 13, 'hardware', 'RAM-SS-001', '三星 DDR3 1600MHz 8GB', '台式机内存 单条8GB 原厂颗粒', '三星', '条', 82.00, 118.00, NULL, NULL, NULL, 40, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(176, 13, 'hardware', 'RAM-SS-002', '三星 DDR4 2666MHz 8GB', '台式机内存 单条8GB 高兼容', '三星', '条', 98.00, 138.00, NULL, NULL, NULL, 35, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"2666MHz\", \"8GB\"]'),
(177, 13, 'hardware', 'RAM-SS-003', '三星 DDR4 3200MHz 16GB', '台式机内存 单条16GB 企业级', '三星', '条', 220.00, 308.00, NULL, NULL, NULL, 20, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"3200MHz\", \"16GB\"]'),
(178, 13, 'hardware', 'RAM-GW-001', '长城 DDR3 1600MHz 8GB', '台式机内存 单条8GB 国产品牌', '长城', '条', 68.00, 98.00, NULL, NULL, NULL, 50, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR3\", \"1600MHz\", \"8GB\"]'),
(179, 13, 'hardware', 'RAM-GW-002', '长城 DDR4 2666MHz 8GB', '台式机内存 单条8GB 性价比高', '长城', '条', 85.00, 122.00, NULL, NULL, NULL, 45, 10, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"DDR4\", \"2666MHz\", \"8GB\"]'),
(180, 12, 'hardware', 'MB-JY-001', '精粤 H61M', 'mATX 支持LGA1155 DDR3双通道 集成显卡', '精粤', '个', 120.00, 168.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1155\", \"DDR3\", \"H61\"]'),
(181, 12, 'hardware', 'MB-JY-002', '精粤 H81M', 'mATX 支持LGA1150 DDR3双通道 USB3.0', '精粤', '个', 145.00, 198.00, NULL, NULL, NULL, 22, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1150\", \"DDR3\", \"H81\"]'),
(182, 12, 'hardware', 'MB-JY-003', '精粤 H310M', 'mATX 支持LGA1151 DDR4双通道 八代九代', '精粤', '个', 185.00, 258.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1151\", \"DDR4\", \"H310\"]'),
(183, 12, 'hardware', 'MB-GB-001', '技嘉 H510M', 'mATX 支持LGA1200 DDR4双通道 十代十一代', '技嘉', '个', 350.00, 488.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1200\", \"DDR4\", \"H510\"]'),
(184, 12, 'hardware', 'MB-GB-002', '技嘉 B560M', 'mATX 支持LGA1200 DDR4双通道 M.2接口', '技嘉', '个', 450.00, 618.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1200\", \"DDR4\", \"B560\"]'),
(185, 12, 'hardware', 'MB-GB-003', '技嘉 B660M', 'mATX 支持LGA1700 DDR4双通道 十二代', '技嘉', '个', 580.00, 798.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1700\", \"DDR4\", \"B660\"]'),
(186, 12, 'hardware', 'MB-GB-004', '技嘉 Z690 AORUS', 'ATX 支持LGA1700 DDR5双通道 超频RGB', '技嘉', '个', 1280.00, 1698.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1700\", \"DDR5\", \"Z690\"]'),
(187, 12, 'hardware', 'MB-AS-001', '华硕 H510M-K', 'mATX 支持LGA1200 DDR4双通道 稳定耐用', '华硕', '个', 380.00, 518.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1200\", \"DDR4\", \"H510\"]'),
(188, 12, 'hardware', 'MB-AS-002', '华硕 PRIME B560M', 'mATX 支持LGA1200 DDR4双通道 RGB灯效', '华硕', '个', 480.00, 658.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1200\", \"DDR4\", \"B560\"]'),
(189, 12, 'hardware', 'MB-AS-003', '华硕 TUF B660M', 'mATX 支持LGA1700 DDR4双通道 电竞特工', '华硕', '个', 650.00, 898.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1700\", \"DDR4\", \"B660\"]'),
(190, 12, 'hardware', 'MB-AS-004', '华硕 ROG Z790', 'ATX 支持LGA1700 DDR5双通道 高端玩家', '华硕', '个', 1680.00, 2298.00, NULL, NULL, NULL, 5, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"LGA1700\", \"DDR5\", \"Z790\"]'),
(191, 12, 'hardware', 'MB-GB-AM4-001', '技嘉 A320M', 'mATX 支持AM4 DDR4双通道 锐龙一二三代', '技嘉', '个', 280.00, 388.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"A320\"]'),
(192, 12, 'hardware', 'MB-GB-AM4-002', '技嘉 B450M', 'mATX 支持AM4 DDR4双通道 M.2接口', '技嘉', '个', 380.00, 518.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"B450\"]'),
(193, 12, 'hardware', 'MB-GB-AM4-003', '技嘉 B550M', 'mATX 支持AM4 DDR4双通道 PCIe 4.0', '技嘉', '个', 520.00, 718.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"B550\"]'),
(194, 12, 'hardware', 'MB-GB-AM4-004', '技嘉 X570 AORUS', 'ATX 支持AM4 DDR4双通道 高端超频', '技嘉', '个', 980.00, 1358.00, NULL, NULL, NULL, 6, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"X570\"]'),
(195, 12, 'hardware', 'MB-AS-AM4-001', '华硕 A320M-K', 'mATX 支持AM4 DDR4双通道 入门之选', '华硕', '个', 298.00, 408.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"A320\"]'),
(196, 12, 'hardware', 'MB-AS-AM4-002', '华硕 PRIME B450M', 'mATX 支持AM4 DDR4双通道 稳定可靠', '华硕', '个', 398.00, 548.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"B450\"]'),
(197, 12, 'hardware', 'MB-AS-AM4-003', '华硕 TUF B550M', 'mATX 支持AM4 DDR4双通道 电竞特工', '华硕', '个', 580.00, 798.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"B550\"]'),
(198, 12, 'hardware', 'MB-AS-AM4-004', '华硕 ROG X570', 'ATX 支持AM4 DDR4双通道 信仰之选', '华硕', '个', 1280.00, 1758.00, NULL, NULL, NULL, 5, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM4\", \"DDR4\", \"X570\"]'),
(199, 12, 'hardware', 'MB-GB-AM5-001', '技嘉 A620M', 'mATX 支持AM5 DDR5双通道 锐龙7000系', '技嘉', '个', 680.00, 928.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM5\", \"DDR5\", \"A620\"]'),
(200, 12, 'hardware', 'MB-GB-AM5-002', '技嘉 B650M', 'mATX 支持AM5 DDR5双通道 PCIe 5.0', '技嘉', '个', 980.00, 1358.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM5\", \"DDR5\", \"B650\"]'),
(201, 12, 'hardware', 'MB-GB-AM5-003', '技嘉 X670E AORUS', 'ATX 支持AM5 DDR5双通道 旗舰超频', '技嘉', '个', 1680.00, 2298.00, NULL, NULL, NULL, 4, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM5\", \"DDR5\", \"X670E\"]'),
(202, 12, 'hardware', 'MB-AS-AM5-001', '华硕 PRIME A620M', 'mATX 支持AM5 DDR5双通道 新平台入门', '华硕', '个', 720.00, 988.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM5\", \"DDR5\", \"A620\"]'),
(203, 12, 'hardware', 'MB-AS-AM5-002', '华硕 TUF B650M', 'mATX 支持AM5 DDR5双通道 电竞品质', '华硕', '个', 1080.00, 1488.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM5\", \"DDR5\", \"B650\"]'),
(204, 12, 'hardware', 'MB-AS-AM5-003', '华硕 ROG X670E', 'ATX 支持AM5 DDR5双通道 玩家国度', '华硕', '个', 2080.00, 2858.00, NULL, NULL, NULL, 3, 5, 1, 0, '2025-10-12 04:01:01', '2025-10-12 04:01:01', '[\"AM5\", \"DDR5\", \"X670E\"]'),
(214, 76, 'hardware', 'MHDD-ST-001', '希捷酷鱼 1TB机械硬盘', '7200转 64MB缓存 SATA3.0接口 3.5英寸 BarraCuda系列', '希捷', '个', 180.00, 258.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(215, 76, 'hardware', 'MHDD-ST-002', '希捷酷鱼 2TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 3.5英寸 BarraCuda系列', '希捷', '个', 280.00, 388.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(216, 76, 'hardware', 'MHDD-ST-003', '希捷酷鱼 4TB机械硬盘', '5900转 256MB缓存 SATA3.0接口 3.5英寸 大容量存储', '希捷', '个', 480.00, 658.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(217, 76, 'hardware', 'MHDD-ST-004', '希捷酷鱼 8TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 3.5英寸 高性能大容量', '希捷', '个', 980.00, 1358.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(218, 76, 'hardware', 'MHDD-ST-005', '希捷监控鹰 2TB机械硬盘', '5400转 256MB缓存 SATA3.0接口 SkyHawk系列 7×24小时运行', '希捷', '个', 320.00, 438.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(219, 76, 'hardware', 'MHDD-ST-006', '希捷监控鹰 4TB机械硬盘', '5900转 256MB缓存 SATA3.0接口 SkyHawk系列 监控专用', '希捷', '个', 520.00, 718.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(220, 76, 'hardware', 'MHDD-ST-007', '希捷监控鹰 8TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 SkyHawk系列 企业监控', '希捷', '个', 1080.00, 1488.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(221, 76, 'hardware', 'MHDD-ST-008', '希捷酷狼 4TB机械硬盘', '5900转 256MB缓存 SATA3.0接口 IronWolf系列 NAS专用', '希捷', '个', 580.00, 798.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(222, 76, 'hardware', 'MHDD-ST-009', '希捷酷狼 8TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 IronWolf系列 NAS优化', '希捷', '个', 1180.00, 1628.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:07:31', '2025-10-12 04:07:31', NULL),
(223, 76, 'hardware', 'MHDD-WD-001', '西数蓝盘 1TB机械硬盘', '7200转 64MB缓存 SATA3.0接口 3.5英寸 Blue系列', '西部数据', '个', 185.00, 268.00, NULL, NULL, NULL, 35, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(224, 76, 'hardware', 'MHDD-WD-002', '西数蓝盘 2TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 3.5英寸 Blue系列', '西部数据', '个', 285.00, 398.00, NULL, NULL, NULL, 28, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(225, 76, 'hardware', 'MHDD-WD-003', '西数蓝盘 4TB机械硬盘', '5400转 256MB缓存 SATA3.0接口 3.5英寸 大容量存储', '西部数据', '个', 520.00, 698.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(226, 76, 'hardware', 'MHDD-WD-004', '西数紫盘 2TB机械硬盘', '5400转 128MB缓存 SATA3.0接口 Purple系列 监控专用', '西部数据', '个', 330.00, 458.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(227, 76, 'hardware', 'MHDD-WD-005', '西数紫盘 4TB机械硬盘', '5400转 256MB缓存 SATA3.0接口 Purple系列 7×24运行', '西部数据', '个', 560.00, 768.00, NULL, NULL, NULL, 20, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(228, 76, 'hardware', 'MHDD-WD-006', '西数紫盘 8TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 Purple系列 企业监控', '西部数据', '个', 1180.00, 1618.00, NULL, NULL, NULL, 12, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(229, 76, 'hardware', 'MHDD-WD-007', '西数红盘 4TB机械硬盘', '5400转 256MB缓存 SATA3.0接口 Red系列 NAS优化', '西部数据', '个', 620.00, 858.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(230, 76, 'hardware', 'MHDD-WD-008', '西数红盘 8TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 Red系列 NAS专用', '西部数据', '个', 1280.00, 1758.00, NULL, NULL, NULL, 10, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(231, 76, 'hardware', 'MHDD-WD-009', '西数黑盘 2TB机械硬盘', '7200', '西部数据', '个', 380.00, 528.00, NULL, NULL, NULL, 15, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-21 17:19:30', NULL),
(232, 76, 'hardware', 'MHDD-WD-010', '西数黑盘 4TB机械硬盘', '7200转 256MB缓存 SATA3.0接口 Black系列 游戏优化', '西部数据', '个', 780.00, 1068.00, NULL, NULL, NULL, 8, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(233, 76, 'hardware', 'MHDD-TS-001', '东芝 1TB机械硬盘', '7200转 64MB缓存 SATA3.0接口 3.5英寸 性价比之选', '东芝', '个', 175.00, 248.00, NULL, NULL, NULL, 30, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(234, 76, 'hardware', 'MHDD-TS-002', '东芝 2TB机械硬盘', '7200', '东芝', '个', 270.00, 368.00, NULL, NULL, NULL, 25, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-17 07:00:08', NULL),
(235, 76, 'hardware', 'MHDD-TS-003', '东芝 4TB机械硬盘', '5400转 256MB缓存 SATA3.0接口 3.5英寸 大容量', '东芝', '个', 460.00, 628.00, NULL, NULL, NULL, 18, 5, 1, 0, '2025-10-12 04:07:32', '2025-10-12 04:07:32', NULL),
(252, 11, 'hardware', 'MANUAL-20251012-0001', 'I3 4130', '0', 'INTEL', '个', 85.00, 100.00, NULL, NULL, NULL, 0, 0, 1, 0, '2025-10-12 09:44:33', '2025-10-21 03:16:01', NULL),
(253, 31, 'device', 'SW-TL-IPC548GP-W4', 'TP-LINK TL-IPC548GP-W4 400万全彩', '400万像素 全彩摄像机', 'TP-LINK', '台', 100.00, 132.00, NULL, NULL, NULL, 20, 10, 1, 1, '2025-10-21 17:10:06', '2025-10-21 17:10:06', NULL),
(254, 31, 'device', 'SW-MCS1318D-P', '水星 MCS1318D-P 16+2千兆上联POE交换机', '16口千兆+2口上联 千兆交换机', '水星', '台', 180.00, 230.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:10:06', '2025-10-21 17:10:06', NULL),
(255, 31, 'device', 'BOX-600-400-280', '防水盒 600×400×280×125 铁', '金属防水盒', '通用', '个', 20.00, 30.00, NULL, NULL, NULL, 50, 10, 1, 1, '2025-10-21 17:10:06', '2025-10-21 17:10:06', NULL),
(256, 31, 'device', 'SW-NVR6120G-L', 'TP-LINK TL-NVR6120G-L (20路/单盘位)', '20路单盘位 网络硬盘录像机', 'TP-LINK', '台', 150.00, 190.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:12:01', '2025-10-21 17:12:01', NULL),
(257, 31, 'accessory', 'SUPPORT-708', '监控支架大鸭嘴 铁708', '铁质大鸭嘴支架 适配监控安装', '通用', '个', 3.00, 4.00, NULL, NULL, NULL, 100, 10, 1, 1, '2025-10-21 17:12:01', '2025-10-21 17:12:01', NULL),
(258, 32, 'display', 'L228IPS', '长城睿显 L228IPS 21.5寸 IPS 黑', '21.5寸 IPS 屏幕', '长城', '台', 250.00, 295.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(259, 32, 'display', 'L248IPS', '长城睿显 L248IPS 24寸 IPS 黑', '24寸 IPS 屏幕', '长城', '台', 270.00, 315.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(260, 31, 'device', 'DS-7804N-Z1/X', '海康 DS-7804N-Z1/X 4路智能录像机', '4路 智能网络录像机', '海康', '台', 180.00, 215.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(261, 31, 'storage', 'HDD-4TB-HGST', '希捷 4TB 监控硬盘 海康logo', '4TB SATA 监控级硬盘', '希捷', '个', 460.00, 498.00, NULL, NULL, NULL, 20, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(262, 31, 'accessory', 'WM07P-A', '监控支架 WM07P-A (宇视) 塑料', '监控摄像机塑料支架', '宇视', '个', 4.00, 5.00, NULL, NULL, NULL, 50, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(263, 32, 'display', 'HKC-V2518M', 'HKC 惠科 V2518M 24.5寸 1K 100Hz', '24.5寸 100Hz IPS 显示器', 'HKC', '台', 310.00, 349.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(264, 31, 'camera', 'DS-2CD1245-LA', '海康威视 400万白光全彩枪 DS-2CD1245-LA 4MM', '400万 白光全彩 枪型', '海康威视', '个', 145.00, 163.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(265, 31, 'switch', 'MSG10CPS', '水星 MSG10CPS 8+2 全千兆 POE (65W)', '8口+2上联 全千兆 PoE 交换机', '水星', '台', 120.00, 135.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(266, 31, 'device', 'TL-NVR6216-L', 'TP 16路双盘录像机 TL-NVR6216-L', '16路 双盘位 网络硬盘录像机', 'TP-LINK', '台', 250.00, 280.00, NULL, NULL, NULL, 5, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(267, 31, 'camera', 'TL-IPC534EP-4', 'TP-LINK TL-IPC534EP-4 300万 PoE 枪型', '300万 PoE 枪型摄像机', 'TP-LINK', '个', 98.00, 115.00, NULL, NULL, NULL, 20, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(268, 31, 'camera', 'MIPC3312P-4', '水星 300万半球 MIPC3312P-4 POE', '300万 半球型 PoE 摄像机', '水星', '个', 60.00, 69.00, NULL, NULL, NULL, 20, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(269, 31, 'device', 'TL-NVR6108K-L', 'TP-LINK TL-NVR6108K-L (8路/单盘位)', '8路 单盘位 网络录像机', 'TP-LINK', '台', 140.00, 165.00, NULL, NULL, NULL, 10, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-21 17:17:47', NULL),
(270, 31, 'hardware', 'NET-PACK', '网络直通/包', '0', '通用', '个', 20.00, 25.00, NULL, NULL, NULL, 100, 10, 1, 1, '2025-10-21 17:17:47', '2025-10-23 14:30:37', NULL);

-- --------------------------------------------------------

--
-- 表的结构 `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int NOT NULL,
  `parent_id` int NOT NULL DEFAULT '0' COMMENT '父级ID (0为顶级分类)',
  `name` varchar(100) NOT NULL COMMENT '分类名称 (如：CPU, 主板, 内存)',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='产品分类表';

--
-- 转存表中的数据 `product_categories`
--

INSERT INTO `product_categories` (`id`, `parent_id`, `name`, `sort_order`, `created_at`) VALUES
(1, 0, '💻 硬件配件', 1, '2025-10-10 16:37:40'),
(2, 0, '🖥️ 整机设备', 2, '2025-10-10 16:37:40'),
(3, 0, '🌐 网络设备', 3, '2025-10-10 16:37:40'),
(4, 0, '🖨️ 外设周边', 4, '2025-10-10 16:37:40'),
(5, 0, '📹 监控安防', 5, '2025-10-10 16:37:40'),
(6, 0, '💾 软件授权', 6, '2025-10-10 16:37:40'),
(7, 0, '🔧 技术服务', 7, '2025-10-10 16:37:40'),
(11, 1, 'CPU处理器', 1, '2025-10-10 16:37:40'),
(12, 1, '主板', 2, '2025-10-10 16:37:40'),
(13, 1, '内存', 3, '2025-10-10 16:37:40'),
(14, 1, '硬盘/SSD', 4, '2025-10-10 16:37:40'),
(15, 1, '显卡', 5, '2025-10-10 16:37:40'),
(16, 1, '电源', 6, '2025-10-10 16:37:40'),
(17, 1, '机箱', 7, '2025-10-10 16:37:40'),
(18, 1, '散热器', 8, '2025-10-10 16:37:40'),
(19, 1, '其他配件', 9, '2025-10-10 16:37:40'),
(21, 2, '品牌台式机', 1, '2025-10-10 16:37:40'),
(22, 2, '笔记本电脑', 2, '2025-10-10 16:37:40'),
(23, 2, '一体机', 3, '2025-10-10 16:37:40'),
(24, 2, '服务器', 4, '2025-10-10 16:37:40'),
(25, 2, '工作站', 5, '2025-10-10 16:37:40'),
(31, 3, '交换机', 1, '2025-10-10 16:37:40'),
(32, 3, '路由器', 2, '2025-10-10 16:37:40'),
(33, 3, '无线AP', 3, '2025-10-10 16:37:40'),
(34, 3, '防火墙', 4, '2025-10-10 16:37:40'),
(35, 3, '网络存储NAS', 5, '2025-10-10 16:37:40'),
(41, 4, '显示器', 1, '2025-10-10 16:37:40'),
(42, 4, '打印机', 2, '2025-10-10 16:37:40'),
(43, 4, '扫描仪', 3, '2025-10-10 16:37:40'),
(44, 4, '投影仪', 4, '2025-10-10 16:37:40'),
(45, 4, '键鼠套装', 5, '2025-10-10 16:37:40'),
(46, 4, 'UPS电源', 6, '2025-10-10 16:37:40'),
(51, 5, '摄像头', 1, '2025-10-10 16:37:40'),
(52, 5, '硬盘录像机', 2, '2025-10-10 16:37:40'),
(53, 5, '门禁系统', 3, '2025-10-10 16:37:40'),
(54, 5, '对讲系统', 4, '2025-10-10 16:37:40'),
(61, 6, '操作系统', 1, '2025-10-10 16:37:40'),
(62, 6, 'Office办公软件', 2, '2025-10-10 16:37:40'),
(63, 6, '杀毒软件', 3, '2025-10-10 16:37:40'),
(64, 6, '设计软件', 4, '2025-10-10 16:37:40'),
(65, 6, '管理软件', 5, '2025-10-10 16:37:40'),
(71, 7, '上门安装', 1, '2025-10-10 16:37:40'),
(72, 7, '系统维护', 2, '2025-10-10 16:37:40'),
(73, 7, '数据恢复', 3, '2025-10-10 16:37:40'),
(74, 7, '网络布线', 4, '2025-10-10 16:37:40'),
(75, 7, '技术支持', 5, '2025-10-10 16:37:40'),
(76, 1, '机械硬盘', 5, '2025-10-12 04:01:01');

-- --------------------------------------------------------

--
-- 表的结构 `quotes`
--

CREATE TABLE `quotes` (
  `id` int NOT NULL,
  `user_id` int NOT NULL COMMENT '创建报价单的员工',
  `customer_id` int NOT NULL,
  `project_name` varchar(255) DEFAULT NULL COMMENT '项目名称（施工类）',
  `project_location` varchar(255) DEFAULT NULL COMMENT '项目地址（施工类）',
  `construction_period` varchar(100) DEFAULT NULL COMMENT '工期（施工类）',
  `quote_no` varchar(50) NOT NULL COMMENT '报价单号',
  `template_type` varchar(50) DEFAULT 'assembled_pc' COMMENT '模板类型 (assembled_pc/brand_pc/weak_current/strong_current)',
  `quote_date` date NOT NULL,
  `valid_days` int DEFAULT '15' COMMENT '有效期天数',
  `status` varchar(20) NOT NULL DEFAULT '草稿' COMMENT '状态 (草稿/已发送/已成交/已过期)',
  `final_amount` decimal(10,2) NOT NULL COMMENT '最终报价总金额',
  `terms` text COMMENT '条款说明',
  `discount` decimal(10,2) DEFAULT '0.00' COMMENT '整单折扣金额',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='报价单主表';

--
-- 转存表中的数据 `quotes`
--

INSERT INTO `quotes` (`id`, `user_id`, `customer_id`, `project_name`, `project_location`, `construction_period`, `quote_no`, `template_type`, `quote_date`, `valid_days`, `status`, `final_amount`, `terms`, `discount`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '', '', '', 'QT-PC-20251010-001', 'assembled_pc', '2025-10-10', 3, '草稿', 1180.00, NULL, 0.00, '2025-10-10 16:02:57', '2025-10-22 03:30:46'),
(2, 1, 0, '', '', '', 'QT-PC-20251010-002', 'assembled_pc', '2025-10-10', 3, '草稿', 0.00, NULL, 0.00, '2025-10-10 16:20:31', '2025-10-22 03:30:46'),
(3, 1, 0, '', '', '', 'QT-PC-20251010-003', 'assembled_pc', '2025-10-10', 15, '草稿', 0.00, NULL, 0.00, '2025-10-10 16:22:05', '2025-10-22 03:30:46'),
(4, 1, 0, '', '', '', 'QT-PC-20251010-004', 'assembled_pc', '2025-10-10', 15, '草稿', 0.00, NULL, 0.00, '2025-10-10 16:25:08', '2025-10-22 03:30:46'),
(5, 1, 1, '', '', '', 'QT-PC-20251010-005', 'assembled_pc', '2025-10-10', 3, '草稿', 0.00, NULL, 0.00, '2025-10-10 16:28:17', '2025-10-22 03:30:46'),
(6, 1, 0, '', '', '', 'QT-PC-20251011-001', 'assembled_pc', '2025-10-11', 15, '草稿', 0.00, NULL, 0.00, '2025-10-11 06:16:07', '2025-10-22 03:30:46'),
(7, 1, 0, NULL, NULL, NULL, 'QT-20251011-001', '', '2025-10-11', 15, '草稿', 0.00, NULL, 0.00, '2025-10-11 06:18:19', '2025-10-22 03:30:46'),
(8, 1, 0, '', '', '', 'QT-PC-20251011-002', 'assembled_pc', '2025-10-11', 15, '草稿', 0.00, NULL, 0.00, '2025-10-11 06:24:52', '2025-10-22 03:30:46'),
(9, 1, 0, '', '', '', 'QT-PC-20251011-003', 'assembled_pc', '2025-10-11', 15, '已发送', 0.00, NULL, 0.00, '2025-10-11 06:24:56', '2025-10-22 03:30:46'),
(10, 1, 1, NULL, NULL, NULL, 'QT-PC-20251011-004', 'assembled_pc', '2025-10-11', 3, '草稿', 0.00, NULL, 0.00, '2025-10-11 07:22:43', '2025-10-22 03:30:46'),
(11, 1, 1, NULL, NULL, NULL, 'QT-PC-20251012-001', 'assembled_pc', '2025-10-12', 3, '草稿', 16668.00, NULL, 0.00, '2025-10-12 03:14:49', '2025-10-22 03:30:46'),
(12, 1, 1, NULL, NULL, NULL, 'QT-PC-20251012-002', 'assembled_pc', '2025-10-12', 15, '草稿', 18563.00, NULL, 0.00, '2025-10-12 04:37:43', '2025-10-22 03:30:46'),
(17, 1, 10, '项目A', '上海', '30天', 'Q2025001', '标准', '2025-10-12', 30, '1', 15000.00, '标准条款', 0.10, '2025-10-12 07:52:41', '2025-10-22 03:30:46'),
(18, 1, 1, '', '', '', 'Q202510120001', 'assembled_pc', '2025-10-12', 15, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 07:58:30', '2025-10-22 03:30:46'),
(21, 1, 1, '', '', '', 'Q202510120002', 'assembled_pc', '2025-10-12', 15, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 07:59:10', '2025-10-22 03:30:46'),
(22, 1, 1, '', '', '', 'Q202510120003', 'assembled_pc', '2025-10-12', 1, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 07:59:38', '2025-10-22 03:30:46'),
(25, 1, 1, '', '', '', 'Q202510120004', 'assembled_pc', '2025-10-12', 2, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 08:02:11', '2025-10-22 03:30:46'),
(30, 1, 1, '', '', '', 'Q202510120005', 'assembled_pc', '2025-10-12', 15, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 08:08:09', '2025-10-22 03:30:46'),
(31, 1, 1, '', '', '', 'Q202510120006', 'assembled_pc', '2025-10-12', 5, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 08:19:26', '2025-10-22 03:30:46'),
(43, 1, 1, '', '', '', 'Q202510120008', 'assembled_pc', '2025-10-12', 15, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 08:43:07', '2025-10-22 03:30:46'),
(48, 1, 1, '', '', '', 'Q202510120009', 'assembled_pc', '2025-10-12', 15, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 09:14:02', '2025-10-22 03:30:46'),
(55, 1, 1, '', '', '', 'Q202510120010', 'assembled_pc', '2025-10-12', 15, '草稿', 0.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 09:29:24', '2025-10-22 03:30:46'),
(56, 1, 1, '', '', '', 'Q202510120011', 'assembled_pc', '2025-10-12', 2, '草稿', 650.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 09:29:35', '2025-10-22 03:30:46'),
(58, 1, 1, '', '', '', 'Q202510120013', 'assembled_pc', '2025-10-12', 15, '已作废', 100.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-12 09:44:33', '2025-10-22 03:30:46'),
(59, 1, 1, '', '', '', 'Q202510130001', 'assembled_pc', '2025-10-13', 15, '已作废', 520.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-13 03:35:02', '2025-10-22 03:30:46'),
(60, 1, 1, '', '', '', 'Q202510130002', 'assembled_pc', '2025-10-13', 15, '已作废', 600.00, '1. 所有配件均为全新正品\r\n2. 提供详细配置清单\r\n3. 免费组装调试\r\n4. 质保期按各配件厂商标准执行', 0.00, '2025-10-13 03:35:14', '2025-10-22 03:30:46'),
(61, 1, 29, '', '', '', 'Q202510228540', 'assembled_pc', '2025-10-22', 3, '已作废', 0.00, '1. 所有配件均为全新正品行货\\n2. 提供详细配置清单\\n3. 免费组装调试\\n4. 质保期按各配件厂商标准执行\\n5. 新机保修三年，机械硬盘保修两年', 0.00, '2025-10-22 03:26:29', '2025-10-22 03:35:54'),
(66, 1, 29, '', '', '', 'Q202510224887', 'assembled_pc', '2025-10-22', 3, '已作废', 0.00, '1. 所有配件均为全新正品行货\\n2. 提供详细配置清单\\n3. 免费组装调试\\n4. 质保期按各配件厂商标准执行\\n5. 新机保修三年，机械硬盘保修两年', 0.00, '2025-10-22 03:50:52', '2025-10-22 03:50:57');

-- --------------------------------------------------------

--
-- 表的结构 `quote_attachments`
--

CREATE TABLE `quote_attachments` (
  `id` int NOT NULL,
  `quote_id` int NOT NULL,
  `file_name` varchar(255) NOT NULL COMMENT '文件名',
  `file_path` varchar(255) NOT NULL COMMENT '文件路径',
  `file_type` varchar(50) DEFAULT NULL COMMENT '文件类型',
  `file_size` int DEFAULT NULL COMMENT '文件大小（字节）',
  `uploaded_by` int NOT NULL COMMENT '上传者ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='报价单附件表';

-- --------------------------------------------------------

--
-- 表的结构 `quote_config_templates`
--

CREATE TABLE `quote_config_templates` (
  `id` int NOT NULL,
  `template_code` varchar(50) NOT NULL COMMENT '模板代码',
  `template_name` varchar(100) NOT NULL COMMENT '模板名称',
  `description` varchar(255) DEFAULT NULL COMMENT '描述',
  `target_price` decimal(10,2) DEFAULT NULL COMMENT '目标价格',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='组装机配置模板表';

--
-- 转存表中的数据 `quote_config_templates`
--

INSERT INTO `quote_config_templates` (`id`, `template_code`, `template_name`, `description`, `target_price`, `created_at`) VALUES
(1, 'intel_high', 'Intel 高端配置', 'i7-14700K + RTX4070Ti 游戏主机', 12000.00, '2025-10-11 06:48:01'),
(2, 'intel_mid', 'Intel 中端配置', 'i5-11400F + GTX1660S 性价比主机', 5500.00, '2025-10-11 06:48:01'),
(3, 'amd_high', 'AMD 高端配置', 'R9-7950X + RTX5060Ti 旗舰主机', 15000.00, '2025-10-11 06:48:01'),
(4, 'amd_mid', 'AMD 中端配置', 'R5-5600X + RX6650XT 游戏主机', 6000.00, '2025-10-11 06:48:01'),
(5, 'office_basic', '办公入门配置', 'i3-10100 集显办公电脑', 2800.00, '2025-10-11 06:48:01');

-- --------------------------------------------------------

--
-- 表的结构 `quote_config_template_items`
--

CREATE TABLE `quote_config_template_items` (
  `id` int NOT NULL,
  `template_id` int NOT NULL,
  `seq` int NOT NULL COMMENT '序号',
  `product_id` int NOT NULL COMMENT '产品ID',
  `quantity` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='配置模板明细';

-- --------------------------------------------------------

--
-- 表的结构 `quote_items`
--

CREATE TABLE `quote_items` (
  `id` int NOT NULL,
  `quote_id` int NOT NULL,
  `seq` int NOT NULL COMMENT '排序序号',
  `category` varchar(100) DEFAULT NULL COMMENT '分类名称（施工类用）',
  `product_id` int DEFAULT NULL COMMENT '关联产品ID',
  `product_name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL COMMENT '品牌',
  `model` varchar(100) DEFAULT NULL COMMENT '型号',
  `spec` varchar(255) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `warranty` varchar(100) DEFAULT NULL COMMENT '质保期限',
  `quantity` int NOT NULL,
  `price` decimal(10,2) NOT NULL COMMENT '报价给客户的单价',
  `cost` decimal(10,2) NOT NULL COMMENT '报价时快照的产品成本价 (内部核算用)',
  `subtotal` decimal(10,2) NOT NULL COMMENT '销售小计 (price * quantity)',
  `cost_subtotal` decimal(10,2) NOT NULL COMMENT '成本小计 (cost * quantity)',
  `custom_fields` json DEFAULT NULL COMMENT '用于模板自定义字段',
  `remark` text COMMENT '备注说明',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='报价单明细表';

--
-- 转存表中的数据 `quote_items`
--

INSERT INTO `quote_items` (`id`, `quote_id`, `seq`, `category`, `product_id`, `product_name`, `brand`, `model`, `spec`, `unit`, `warranty`, `quantity`, `price`, `cost`, `subtotal`, `cost_subtotal`, `custom_fields`, `remark`, `updated_at`) VALUES
(1, 1, 1, NULL, NULL, '主板', '技嘉H610', '', 'M', '个', '', 1, 400.00, 0.00, 400.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(2, 1, 2, NULL, NULL, '处理器', 'INTEL I5', '', '12400', '个', '', 1, 780.00, 0.00, 780.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(3, 2, 1, NULL, NULL, '主板', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(4, 2, 2, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(5, 2, 3, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(6, 2, 4, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(7, 2, 5, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(8, 2, 6, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(9, 2, 7, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(10, 2, 8, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(11, 3, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(12, 3, 2, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(13, 3, 3, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(14, 3, 4, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(15, 3, 5, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(16, 3, 6, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(17, 3, 7, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(18, 3, 8, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(19, 3, 9, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(20, 3, 10, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(21, 3, 11, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(22, 3, 12, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(23, 3, 13, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(24, 4, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(25, 4, 2, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(26, 4, 3, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(27, 4, 4, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(28, 4, 5, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(29, 4, 6, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(30, 4, 7, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(31, 4, 8, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(32, 4, 9, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(33, 4, 10, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(34, 4, 11, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(35, 4, 12, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(36, 4, 13, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(37, 4, 14, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(38, 5, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(39, 5, 2, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(40, 5, 3, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(41, 5, 4, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(42, 5, 5, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(43, 5, 6, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(44, 5, 7, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(45, 5, 8, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(46, 5, 9, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(47, 5, 10, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(48, 5, 11, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(49, 5, 12, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(50, 5, 13, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(51, 5, 14, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(52, 5, 15, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(53, 6, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(54, 6, 2, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(55, 6, 3, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(56, 6, 4, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(57, 6, 5, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(58, 6, 6, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(59, 6, 7, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(60, 6, 8, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(61, 6, 9, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(62, 6, 10, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(63, 6, 11, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(64, 6, 12, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(65, 6, 13, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(66, 8, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(67, 9, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(68, 10, 1, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(69, 10, 2, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(70, 10, 3, NULL, NULL, '', '', '', '', '条', '', 2, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(71, 10, 4, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(72, 10, 5, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(73, 10, 6, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(74, 10, 7, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(75, 10, 8, NULL, NULL, '', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(76, 10, 9, NULL, NULL, '', '', '', '', '台', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(77, 10, 10, NULL, NULL, '', '', '', '', '套', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(78, 11, 1, NULL, NULL, 'Intel i7-14700K', '', '', '20核28线程，最高5.6GHz', '个', '', 1, 3299.00, 0.00, 3299.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(79, 11, 2, NULL, NULL, '华硕 ROG STRIX Z790-E', '', '', 'ATX，DDR5，WiFi 6E', '个', '', 1, 2499.00, 0.00, 2499.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(80, 11, 3, NULL, NULL, '金士顿 DDR5 32GB', '', '', '6000MHz，16GB×2', '条', '', 2, 899.00, 0.00, 1798.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(81, 11, 4, NULL, NULL, '三星 980 PRO 1TB', '', '', 'NVMe PCIe 4.0', '个', '', 1, 799.00, 0.00, 799.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(82, 11, 5, NULL, NULL, 'RTX 4070 Ti', '', '', '12GB GDDR6X', '个', '', 1, 5999.00, 0.00, 5999.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(83, 11, 6, NULL, NULL, '海韵 FOCUS GX-750', '', '', '750W 80Plus金牌全模组', '个', '', 1, 699.00, 0.00, 699.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(84, 11, 7, NULL, NULL, '先马 黑洞5', '', '', 'M-ATX 侧透机箱', '个', '', 1, 199.00, 0.00, 199.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(85, 11, 8, NULL, NULL, '九州风神 AK400', '', '', '塔式散热器 4热管', '个', '', 1, 89.00, 0.00, 89.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(86, 11, 9, NULL, NULL, 'AOC 27寸 2K显示器', '', '', '27英寸 IPS屏幕 2560×1440分辨率 75Hz刷新率 HDMI+DP接口 HDR技术 支架可升降旋转', '台', '', 1, 1199.00, 0.00, 1199.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(87, 11, 10, NULL, NULL, '宏碁 USB键鼠套装', '', '', '有线USB接口，商务办公，巧克力键盘，1600DPI鼠标', '套', '', 1, 88.00, 0.00, 88.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(88, 12, 1, NULL, NULL, 'Intel i7-14700K', '', '', '20核28线程，最高5.6GHz', '个', '', 1, 3299.00, 0.00, 3299.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(89, 12, 2, NULL, NULL, '华硕 ROG STRIX Z790-E', '', '', 'ATX，DDR5，WiFi 6E', '个', '', 1, 2499.00, 0.00, 2499.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(90, 12, 3, NULL, NULL, '金士顿 DDR5 32GB', '', '', '6000MHz，16GB×2', '条', '', 2, 899.00, 0.00, 1798.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(91, 12, 4, NULL, NULL, '三星 980 PRO 1TB', '', '', 'NVMe PCIe 4.0', '个', '', 1, 799.00, 0.00, 799.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(92, 12, 5, NULL, NULL, '希捷酷鱼 8TB机械硬盘', '', '', '7200转 256MB缓存 SATA3.0接口 3.5英寸 高性能大容量', '个', '', 1, 1358.00, 0.00, 1358.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(93, 12, 6, NULL, NULL, 'RTX 4070 Ti', '', '', '12GB GDDR6X', '个', '', 1, 5999.00, 0.00, 5999.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(94, 12, 7, NULL, NULL, '长城电源 700W', '', '', '额定700W 主动式PFC 双12V 80PLUS金牌 全模组', '个', '', 1, 380.00, 0.00, 380.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(95, 12, 8, NULL, NULL, '酷冷至尊 TD500', '', '', 'ATX 侧透RGB', '个', '', 1, 399.00, 0.00, 399.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(96, 12, 9, NULL, NULL, '四铜管横向散热器', '', '', '4热管 9cm风扇 支持Intel/AMD多平台 TDP 130W', '个', '', 1, 55.00, 0.00, 55.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(97, 12, 10, NULL, NULL, '戴尔 27寸显示器', '', '', '2K IPS 75Hz', '台', '', 1, 1899.00, 0.00, 1899.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(98, 12, 11, NULL, NULL, '雷柏 USB键鼠套装', '', '', '有线USB接口，静音按键，舒适手感，1200DPI鼠标', '套', '', 1, 78.00, 0.00, 78.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(100, 56, 1, 'CPU处理器', 87, 'Intel i7-4790K', '', '', '四核八线程 4.0GHz 最高4.4GHz 8MB三级缓存 超频版', '个', '', 1, 650.00, 552.50, 650.00, 552.50, NULL, '', '2025-10-22 03:32:31'),
(104, 59, 1, 'CPU处理器', 86, 'Intel i7-4770', '', '', '四核八线程 3.4GHz 最高3.9GHz 8MB三级缓存', '个', '', 1, 520.00, 442.00, 520.00, 442.00, NULL, '', '2025-10-22 03:32:31'),
(107, 60, 1, 'CPU处理器', 80, 'Intel Xeon E3-1225 V2', '', '', '四核四线程 3.2GHz 最高3.6GHz 8MB三级缓存 集成HD P4000显卡', '个', '', 1, 600.00, 510.00, 600.00, 510.00, NULL, '', '2025-10-22 03:32:31'),
(108, 58, 1, 'CPU处理器', 252, 'I3 4130', '', '', '', '个', '', 1, 100.00, 85.00, 100.00, 85.00, NULL, '', '2025-10-22 03:32:31'),
(115, 61, 1, NULL, 95, '处理器', '', '', '四核四线程 3.2GHz 最高3.6GHz 6MB三级缓存', '个', '', 1, 0.00, 350.00, 0.00, 350.00, NULL, '', '2025-10-22 03:32:31'),
(116, 61, 2, NULL, NULL, '主板', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(117, 61, 3, NULL, NULL, '内存', '', '', '', '条', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(118, 61, 4, NULL, NULL, '硬盘/SSD', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(119, 61, 5, NULL, NULL, '显卡', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(120, 61, 6, NULL, NULL, '电源', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(121, 61, 7, NULL, NULL, '机箱', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(122, 61, 8, NULL, NULL, '散热器', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(123, 61, 9, NULL, NULL, '显示器', '', '', '', '台', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(124, 61, 10, NULL, NULL, '键鼠套装', '', '', '', '套', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:32:31'),
(195, 66, 1, NULL, NULL, '处理器', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(196, 66, 2, NULL, NULL, '主板', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(197, 66, 3, NULL, NULL, '内存', '', '', '', '条', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(198, 66, 4, NULL, NULL, '硬盘/SSD', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(199, 66, 5, NULL, NULL, '显卡', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(200, 66, 6, NULL, NULL, '电源', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(201, 66, 7, NULL, NULL, '机箱', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(202, 66, 8, NULL, NULL, '散热器', '', '', '', '个', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(203, 66, 9, NULL, NULL, '显示器', '', '', '', '台', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52'),
(204, 66, 10, NULL, NULL, '键鼠套装', '', '', '', '套', '', 1, 0.00, 0.00, 0.00, 0.00, NULL, '', '2025-10-22 03:50:52');

-- --------------------------------------------------------

--
-- 表的结构 `quote_templates`
--

CREATE TABLE `quote_templates` (
  `id` int NOT NULL,
  `template_code` varchar(50) NOT NULL COMMENT '模板代码',
  `template_name` varchar(100) NOT NULL COMMENT '模板名称',
  `template_type` varchar(50) NOT NULL COMMENT '模板类型',
  `description` text COMMENT '模板描述',
  `default_terms` text COMMENT '默认条款说明',
  `field_config` json DEFAULT NULL COMMENT '字段配置（JSON）',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='报价模板配置表';

--
-- 转存表中的数据 `quote_templates`
--

INSERT INTO `quote_templates` (`id`, `template_code`, `template_name`, `template_type`, `description`, `default_terms`, `field_config`, `is_active`, `created_at`) VALUES
(1, 'assembled_pc', '组装电脑报价单', 'assembled_pc', '适用于组装台式机、游戏主机等DIY配置', '1. 所有配件均为全新正品\n2. 提供详细配置清单\n3. 免费组装调试\n4. 质保期按各配件厂商标准执行', NULL, 1, '2025-10-10 15:51:33'),
(2, 'brand_pc', '品牌整机报价单', 'brand_pc', '适用于品牌台式机、笔记本、一体机、服务器、网络设备等', '1. 原厂正品，提供官方质保\n2. 全国联保服务\n3. 提供增值税发票\n4. 可提供上门安装服务', NULL, 1, '2025-10-10 15:51:33'),
(3, 'weak_current', '弱电工程报价单', 'weak_current', '适用于网络布线、监控系统、门禁系统、综合布线等弱电工程', '1. 所有材料符合国家标准\n2. 施工符合规范要求\n3. 提供竣工图纸\n4. 质保期1年，终身维护\n5. 工期以实际情况为准', NULL, 1, '2025-10-10 15:51:33'),
(4, 'strong_current', '强电工程报价单', 'strong_current', '适用于电力安装、配电系统、照明工程等强电施工', '1. 持证电工施工\n2. 材料符合国家3C标准\n3. 施工符合电力安装规范\n4. 提供验收报告\n5. 质保期2年\n6. 工期以合同约定为准', NULL, 1, '2025-10-10 15:51:33');

-- --------------------------------------------------------

--
-- 表的结构 `repair_jobs`
--

CREATE TABLE `repair_jobs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL COMMENT '创建任务的员工ID',
  `job_no` varchar(50) NOT NULL COMMENT '维修任务编号',
  `customer_id` int NOT NULL,
  `device_name` varchar(255) NOT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `fault_description` text,
  `current_status` varchar(50) NOT NULL DEFAULT '待检测',
  `technician_id` int DEFAULT NULL COMMENT '负责维修的技师ID',
  `quote_id` int DEFAULT NULL COMMENT '关联的维修报价单ID',
  `final_repair_fee` decimal(10,2) DEFAULT '0.00',
  `start_date` date NOT NULL,
  `completion_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='维修任务主表';

-- --------------------------------------------------------

--
-- 表的结构 `repair_job_logs`
--

CREATE TABLE `repair_job_logs` (
  `id` int NOT NULL,
  `job_id` int NOT NULL,
  `operator_id` int NOT NULL,
  `log_type` varchar(20) NOT NULL COMMENT '日志类型 (status_change/action_taken/note)',
  `details` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='维修进度日志表';

-- --------------------------------------------------------

--
-- 表的结构 `repair_logs`
--

CREATE TABLE `repair_logs` (
  `id` int NOT NULL COMMENT '日志ID',
  `repair_id` int NOT NULL COMMENT '维修单ID',
  `status` varchar(50) NOT NULL COMMENT '状态',
  `description` text NOT NULL COMMENT '描述',
  `operator` varchar(50) NOT NULL COMMENT '操作人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='维修进度日志表';

--
-- 转存表中的数据 `repair_logs`
--

INSERT INTO `repair_logs` (`id`, `repair_id`, `status`, `description`, `operator`, `created_at`) VALUES
(1, 1, 'pending', '创建维修单', '杨静', '2025-10-17 07:25:40'),
(2, 2, 'pending', '创建维修单', '郑凯', '2025-10-17 07:38:46'),
(3, 3, 'pending', '创建维修单', '系统管理员', '2025-10-21 04:55:11'),
(4, 3, 'updated', '添加配件: 3434', '系统管理员', '2025-10-21 04:55:38'),
(5, 3, 'updated', '更新费用信息', '系统管理员', '2025-10-21 04:55:48');

-- --------------------------------------------------------

--
-- 表的结构 `repair_orders`
--

CREATE TABLE `repair_orders` (
  `id` int NOT NULL COMMENT '维修单ID',
  `order_no` varchar(50) NOT NULL COMMENT '维修单号',
  `repair_type` enum('onsite','inshop') NOT NULL DEFAULT 'inshop' COMMENT '维修类型：onsite=上门服务, inshop=带回维修',
  `customer_id` int DEFAULT NULL COMMENT '客户ID',
  `customer_name` varchar(100) NOT NULL COMMENT '客户姓名',
  `contact_phone` varchar(20) NOT NULL COMMENT '联系电话',
  `contact_address` varchar(500) DEFAULT NULL COMMENT '联系地址',
  `device_type` varchar(100) DEFAULT NULL COMMENT '设备类型',
  `device_brand` varchar(100) DEFAULT NULL COMMENT '设备品牌',
  `device_model` varchar(100) DEFAULT NULL COMMENT '设备型号',
  `device_sn` varchar(100) DEFAULT NULL COMMENT '设备序列号',
  `fault_description` text NOT NULL COMMENT '故障描述',
  `appearance_check` text COMMENT '外观检查',
  `accessories` varchar(500) DEFAULT NULL COMMENT '附带配件',
  `receive_date` datetime NOT NULL COMMENT '接收日期',
  `expected_finish_date` date DEFAULT NULL COMMENT '预计完成日期',
  `actual_finish_date` datetime DEFAULT NULL COMMENT '实际完成日期',
  `status` enum('pending','repairing','testing','completed','delivered','cancelled') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `technician` varchar(50) DEFAULT NULL COMMENT '维修技师',
  `repair_result` text COMMENT '维修结果',
  `service_fee` decimal(10,2) DEFAULT '0.00' COMMENT '服务费',
  `parts_fee` decimal(10,2) DEFAULT '0.00' COMMENT '配件费',
  `other_fee` decimal(10,2) DEFAULT '0.00' COMMENT '其他费用',
  `total_fee` decimal(10,2) DEFAULT '0.00' COMMENT '总费用',
  `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '已付金额',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid' COMMENT '支付状态',
  `notes` text COMMENT '备注',
  `created_by` int NOT NULL COMMENT '创建人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='维修单主表';

--
-- 转存表中的数据 `repair_orders`
--

INSERT INTO `repair_orders` (`id`, `order_no`, `repair_type`, `customer_id`, `customer_name`, `contact_phone`, `contact_address`, `device_type`, `device_brand`, `device_model`, `device_sn`, `fault_description`, `appearance_check`, `accessories`, `receive_date`, `expected_finish_date`, `actual_finish_date`, `status`, `technician`, `repair_result`, `service_fee`, `parts_fee`, `other_fee`, `total_fee`, `paid_amount`, `payment_status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'WX202510170001', 'onsite', 7, '杨静', '18911110000', '232323', '2323', '', '', '', '232323', '', '', '2025-10-17 07:25:40', NULL, NULL, 'pending', '', NULL, 232323.00, 0.00, 0.00, 232323.00, 0.00, 'unpaid', '', 1, '2025-10-17 07:25:40', '2025-10-17 07:25:40'),
(2, 'WX202510170002', 'onsite', 18, '郑凯', '13350506060', '3434', '3434', '', '', '', '343434', '', '', '2025-10-17 07:38:46', NULL, NULL, 'pending', '', NULL, 70.00, 0.00, 0.00, 70.00, 0.00, 'unpaid', '', 1, '2025-10-17 07:38:46', '2025-10-17 07:38:46'),
(3, 'WX20251021125511', 'inshop', 28, '同行', '13316973369', '', '', '', '', '', '343434', '', '', '2025-10-21 12:55:11', NULL, NULL, 'pending', NULL, NULL, 0.00, 4334.00, 0.00, 4334.00, 0.00, 'unpaid', '', 1, '2025-10-21 04:55:11', '2025-10-21 04:55:38');

-- --------------------------------------------------------

--
-- 表的结构 `repair_parts`
--

CREATE TABLE `repair_parts` (
  `id` int NOT NULL COMMENT '配件ID',
  `repair_id` int NOT NULL COMMENT '维修单ID',
  `part_name` varchar(200) NOT NULL COMMENT '配件名称',
  `part_model` varchar(100) DEFAULT NULL COMMENT '配件型号',
  `quantity` int NOT NULL DEFAULT '1' COMMENT '数量',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '单价',
  `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '小计',
  `supplier` varchar(200) DEFAULT NULL COMMENT '供应商',
  `supplier_cost` decimal(10,2) DEFAULT '0.00' COMMENT '采购成本',
  `notes` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='维修配件明细表';

--
-- 转存表中的数据 `repair_parts`
--

INSERT INTO `repair_parts` (`id`, `repair_id`, `part_name`, `part_model`, `quantity`, `unit_price`, `subtotal`, `supplier`, `supplier_cost`, `notes`, `created_at`) VALUES
(1, 3, '3434', '', 1, 4334.00, 4334.00, '', 0.00, '', '2025-10-21 04:55:38');

-- --------------------------------------------------------

--
-- 表的结构 `repair_payments`
--

CREATE TABLE `repair_payments` (
  `id` int NOT NULL COMMENT '收付款ID',
  `repair_id` int NOT NULL COMMENT '维修单ID',
  `payment_type` enum('receivable','payable') NOT NULL COMMENT '类型：receivable=应收, payable=应付',
  `amount` decimal(10,2) NOT NULL COMMENT '金额',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '支付方式',
  `payment_date` datetime NOT NULL COMMENT '支付日期',
  `payee_payer` varchar(100) DEFAULT NULL COMMENT '收款人/付款人',
  `notes` varchar(500) DEFAULT NULL COMMENT '备注',
  `created_by` int NOT NULL COMMENT '创建人',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='维修收付款记录表';

--
-- 转存表中的数据 `repair_payments`
--

INSERT INTO `repair_payments` (`id`, `repair_id`, `payment_type`, `amount`, `payment_method`, `payment_date`, `payee_payer`, `notes`, `created_by`, `created_at`) VALUES
(1, 1, 'receivable', 232323.00, NULL, '2025-10-17 07:25:40', NULL, '维修费用应收', 1, '2025-10-17 07:25:40'),
(2, 2, 'receivable', 70.00, NULL, '2025-10-17 07:38:46', NULL, '维修费用应收', 1, '2025-10-17 07:38:46');

-- --------------------------------------------------------

--
-- 表的结构 `seals`
--

CREATE TABLE `seals` (
  `id` int NOT NULL COMMENT '公章ID',
  `seal_name` varchar(200) NOT NULL COMMENT '公章名称',
  `seal_type` varchar(50) NOT NULL COMMENT '公章类型（公章/财务章/合同章/法人章）',
  `file_path` varchar(255) NOT NULL COMMENT '公章文件路径',
  `description` text COMMENT '备注说明',
  `is_default` tinyint DEFAULT '0' COMMENT '是否默认:1=默认,0=非默认',
  `uploaded_by` int NOT NULL COMMENT '上传人ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='公章管理表';

--
-- 转存表中的数据 `seals`
--

INSERT INTO `seals` (`id`, `seal_name`, `seal_type`, `file_path`, `description`, `is_default`, `uploaded_by`, `created_at`, `updated_at`) VALUES
(1, '深圳市凯尔耐特科技有限公司', '公章', 'seal_1761226754_68fa300271c2b.png', '', 1, 1, '2025-10-23 13:39:14', '2025-10-23 13:39:18');

-- --------------------------------------------------------

--
-- 表的结构 `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL COMMENT '供应商ID',
  `supplier_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '供应商编号',
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '公司名称',
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `contact_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系邮箱',
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公司地址',
  `tax_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税号/纳税人识别号',
  `bank_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '开户行',
  `bank_account` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '银行账号',
  `payment_terms` int DEFAULT '30' COMMENT '账期天数(0=现结)',
  `credit_limit` decimal(15,2) DEFAULT '0.00' COMMENT '信用额度(0=不限额)',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '备注说明',
  `is_active` tinyint DEFAULT '1' COMMENT '是否启用:1=启用,0=停用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商表';

--
-- 转存表中的数据 `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `company_name`, `contact_person`, `contact_phone`, `contact_email`, `address`, `tax_number`, `bank_name`, `bank_account`, `payment_terms`, `credit_limit`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'S202510220001', '北京华硕科技有限公司', '张经理', '13800138001', 'zhang@asus.com', '北京市朝阳区建国路88号', '110108MA001234', '中国工商银行北京朝阳支行', '6222001234567890123', 30, 100000.00, '主板、显卡供应商', 1, '2025-10-22 07:36:11', '2025-10-23 12:55:09'),
(2, 'S202510220002', '深圳金士顿贸易公司', '李经理', '13800138002', 'li@kingston.com', '深圳市福田区华强北路168号', '440300MA002345', '中国建设银行深圳分行', '6227001234567890123', 60, 200000.00, '内存、固态硬盘供应商', 1, '2025-10-22 07:36:11', '2025-10-22 07:36:11'),
(3, 'S202510220003', '上海Intel代理商', '王经理', '13800138003', 'wang@intel.com', '上海市浦东新区张江高科技园区', '310115MA003456', '中国农业银行上海浦东支行', '6228001234567890123', 45, 150000.00, 'CPU处理器供应商', 1, '2025-10-22 07:36:11', '2025-10-22 07:36:11'),
(4, 'S202510220004', '广州西部数据科技', '赵经理', '13800138004', 'zhao@wd.com', '广州市天河区体育西路123号', '440106MA004567', '招商银行广州分行', '6214001234567890123', 30, 80000.00, '机械硬盘、移动硬盘供应商', 1, '2025-10-22 07:36:11', '2025-10-22 07:36:11'),
(5, 'S202510230005', '龙华陈鑫涛', '陈鑫涛', '28960660', '', '', '', '', '0', 30, 0.00, '', 1, '2025-10-23 13:04:58', '2025-10-23 13:22:20');

-- --------------------------------------------------------

--
-- 表的结构 `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL COMMENT '手机号，用于登录/注册',
  `nickname` varchar(50) DEFAULT NULL COMMENT '昵称/姓名',
  `role` varchar(50) NOT NULL DEFAULT 'employee' COMMENT '角色 (admin/employee/technician)',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态 (1:启用, 0:禁用)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '登录密码哈希值',
  `email` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户/员工表';

--
-- 转存表中的数据 `users`
--

INSERT INTO `users` (`id`, `username`, `phone`, `nickname`, `role`, `status`, `created_at`, `updated_at`, `password`, `email`, `last_login`) VALUES
(1, '系统管理员', '13316973303', '系统管理员', 'admin', 1, '2025-10-10 14:58:32', '2025-10-23 04:33:00', '$2y$10$4I7aDLCYqGZl5xU2eVj1kuiGqv5ojuurOoQVn.K3BparsNeQFsTEe', NULL, NULL),
(2, 'kaiernet', '', 'kaiernet', 'user', 1, '2025-10-23 04:47:29', '2025-10-23 04:52:22', '$2y$10$UlXCq0jr1UVKK0u6JWcQyeU9JemPePhNQ65rhawAlBlEM5OXhehOW', '', NULL),
(10, 'kaier', '13316973369', 'kaier', 'admin', 1, '2025-10-23 04:59:54', '2025-10-23 05:01:52', '$2y$10$Kbi3FZpJNC629lM6AYgMKuZFD/0fswd.uyVnizr23QUg6kA11Ladm', '', NULL);

--
-- 转储表的索引
--

--
-- 表的索引 `accounts_payable`
--
ALTER TABLE `accounts_payable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_no` (`bill_no`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `accounts_receivable`
--
ALTER TABLE `accounts_receivable`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bill_no` (`bill_no`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_status` (`status`);

--
-- 表的索引 `company_seals`
--
ALTER TABLE `company_seals`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- 表的索引 `default_pc_configs`
--
ALTER TABLE `default_pc_configs`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `deliveries`
--
ALTER TABLE `deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_no` (`delivery_no`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_quote` (`quote_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_delivery_date` (`delivery_date`);

--
-- 表的索引 `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_id` (`delivery_id`);

--
-- 表的索引 `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_id` (`delivery_id`);

--
-- 表的索引 `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `delivery_no` (`delivery_no`),
  ADD KEY `idx_quote_id` (`quote_id`);

--
-- 表的索引 `delivery_order_items`
--
ALTER TABLE `delivery_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_id` (`delivery_id`);

--
-- 表的索引 `delivery_payments`
--
ALTER TABLE `delivery_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery_id` (`delivery_id`),
  ADD KEY `idx_payment_type` (`payment_type`);

--
-- 表的索引 `document_seals`
--
ALTER TABLE `document_seals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document` (`document_type`,`document_id`),
  ADD KEY `idx_seal_id` (`seal_id`);

--
-- 表的索引 `industries`
--
ALTER TABLE `industries`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `payment_records`
--
ALTER TABLE `payment_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `record_no` (`record_no`),
  ADD KEY `idx_related` (`related_type`,`related_id`);

--
-- 表的索引 `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_sku` (`sku`);

--
-- 表的索引 `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- 表的索引 `quotes`
--
ALTER TABLE `quotes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `quote_no` (`quote_no`),
  ADD KEY `idx_customer_id` (`customer_id`);

--
-- 表的索引 `quote_attachments`
--
ALTER TABLE `quote_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quote_id` (`quote_id`);

--
-- 表的索引 `quote_config_templates`
--
ALTER TABLE `quote_config_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_code` (`template_code`);

--
-- 表的索引 `quote_config_template_items`
--
ALTER TABLE `quote_config_template_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_template_id` (`template_id`);

--
-- 表的索引 `quote_items`
--
ALTER TABLE `quote_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_quote_id` (`quote_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- 表的索引 `quote_templates`
--
ALTER TABLE `quote_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_code` (`template_code`);

--
-- 表的索引 `repair_jobs`
--
ALTER TABLE `repair_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `job_no` (`job_no`),
  ADD KEY `idx_customer_id` (`customer_id`),
  ADD KEY `idx_technician_id` (`technician_id`);

--
-- 表的索引 `repair_job_logs`
--
ALTER TABLE `repair_job_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`);

--
-- 表的索引 `repair_logs`
--
ALTER TABLE `repair_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_repair_id` (`repair_id`);

--
-- 表的索引 `repair_orders`
--
ALTER TABLE `repair_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_no` (`order_no`),
  ADD KEY `idx_customer` (`customer_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_repair_type` (`repair_type`),
  ADD KEY `idx_receive_date` (`receive_date`);

--
-- 表的索引 `repair_parts`
--
ALTER TABLE `repair_parts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_repair_id` (`repair_id`);

--
-- 表的索引 `repair_payments`
--
ALTER TABLE `repair_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_repair_id` (`repair_id`),
  ADD KEY `idx_payment_type` (`payment_type`);

--
-- 表的索引 `seals`
--
ALTER TABLE `seals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`),
  ADD KEY `idx_is_default` (`is_default`);

--
-- 表的索引 `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_supplier_code` (`supplier_code`),
  ADD KEY `idx_company_name` (`company_name`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 表的索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_nickname` (`nickname`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- 在导出的表使用AUTO_INCREMENT
--

--
-- 使用表AUTO_INCREMENT `accounts_payable`
--
ALTER TABLE `accounts_payable`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `accounts_receivable`
--
ALTER TABLE `accounts_receivable`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `company_seals`
--
ALTER TABLE `company_seals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- 使用表AUTO_INCREMENT `default_pc_configs`
--
ALTER TABLE `default_pc_configs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `deliveries`
--
ALTER TABLE `deliveries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '送货单ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `delivery_items`
--
ALTER TABLE `delivery_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '明细ID', AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `delivery_logs`
--
ALTER TABLE `delivery_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID', AUTO_INCREMENT=7;

--
-- 使用表AUTO_INCREMENT `delivery_orders`
--
ALTER TABLE `delivery_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `delivery_order_items`
--
ALTER TABLE `delivery_order_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `delivery_payments`
--
ALTER TABLE `delivery_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '收款ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `document_seals`
--
ALTER TABLE `document_seals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID';

--
-- 使用表AUTO_INCREMENT `industries`
--
ALTER TABLE `industries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `payment_records`
--
ALTER TABLE `payment_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `products`
--
ALTER TABLE `products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=271;

--
-- 使用表AUTO_INCREMENT `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- 使用表AUTO_INCREMENT `quotes`
--
ALTER TABLE `quotes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- 使用表AUTO_INCREMENT `quote_attachments`
--
ALTER TABLE `quote_attachments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `quote_config_templates`
--
ALTER TABLE `quote_config_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `quote_config_template_items`
--
ALTER TABLE `quote_config_template_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `quote_items`
--
ALTER TABLE `quote_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=205;

--
-- 使用表AUTO_INCREMENT `quote_templates`
--
ALTER TABLE `quote_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- 使用表AUTO_INCREMENT `repair_jobs`
--
ALTER TABLE `repair_jobs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `repair_job_logs`
--
ALTER TABLE `repair_job_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- 使用表AUTO_INCREMENT `repair_logs`
--
ALTER TABLE `repair_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '日志ID', AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `repair_orders`
--
ALTER TABLE `repair_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '维修单ID', AUTO_INCREMENT=4;

--
-- 使用表AUTO_INCREMENT `repair_parts`
--
ALTER TABLE `repair_parts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '配件ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `repair_payments`
--
ALTER TABLE `repair_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '收付款ID', AUTO_INCREMENT=3;

--
-- 使用表AUTO_INCREMENT `seals`
--
ALTER TABLE `seals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '公章ID', AUTO_INCREMENT=2;

--
-- 使用表AUTO_INCREMENT `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT COMMENT '供应商ID', AUTO_INCREMENT=6;

--
-- 使用表AUTO_INCREMENT `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- 限制导出的表
--

--
-- 限制表 `delivery_items`
--
ALTER TABLE `delivery_items`
  ADD CONSTRAINT `fk_delivery_items` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE;

--
-- 限制表 `delivery_logs`
--
ALTER TABLE `delivery_logs`
  ADD CONSTRAINT `fk_delivery_logs` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE;

--
-- 限制表 `delivery_payments`
--
ALTER TABLE `delivery_payments`
  ADD CONSTRAINT `fk_delivery_payments` FOREIGN KEY (`delivery_id`) REFERENCES `deliveries` (`id`) ON DELETE CASCADE;

--
-- 限制表 `repair_logs`
--
ALTER TABLE `repair_logs`
  ADD CONSTRAINT `fk_repair_logs` FOREIGN KEY (`repair_id`) REFERENCES `repair_orders` (`id`) ON DELETE CASCADE;

--
-- 限制表 `repair_parts`
--
ALTER TABLE `repair_parts`
  ADD CONSTRAINT `fk_repair_parts` FOREIGN KEY (`repair_id`) REFERENCES `repair_orders` (`id`) ON DELETE CASCADE;

--
-- 限制表 `repair_payments`
--
ALTER TABLE `repair_payments`
  ADD CONSTRAINT `fk_repair_payments` FOREIGN KEY (`repair_id`) REFERENCES `repair_orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
