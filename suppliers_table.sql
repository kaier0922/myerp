-- =====================================================
-- 供应商表 (suppliers)
-- 功能：存储供应商基本信息、联系方式、财务信息等
-- 创建日期：2025-10-22
-- =====================================================

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '供应商ID',
  `supplier_code` varchar(50) NOT NULL COMMENT '供应商编号',
  `company_name` varchar(255) NOT NULL COMMENT '公司名称',
  `contact_person` varchar(100) DEFAULT NULL COMMENT '联系人',
  `contact_phone` varchar(50) DEFAULT NULL COMMENT '联系电话',
  `contact_email` varchar(100) DEFAULT NULL COMMENT '联系邮箱',
  `address` varchar(500) DEFAULT NULL COMMENT '公司地址',
  `tax_number` varchar(100) DEFAULT NULL COMMENT '税号/纳税人识别号',
  `bank_name` varchar(200) DEFAULT NULL COMMENT '开户行',
  `bank_account` varchar(100) DEFAULT NULL COMMENT '银行账号',
  `payment_terms` int DEFAULT 30 COMMENT '账期天数(0=现结)',
  `credit_limit` decimal(15,2) DEFAULT 0.00 COMMENT '信用额度(0=不限额)',
  `description` text COMMENT '备注说明',
  `is_active` tinyint DEFAULT 1 COMMENT '是否启用:1=启用,0=停用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_supplier_code` (`supplier_code`),
  KEY `idx_company_name` (`company_name`),
  KEY `idx_active` (`is_active`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='供应商表';

-- =====================================================
-- 插入示例数据（可选）
-- =====================================================

INSERT INTO `suppliers` 
(`supplier_code`, `company_name`, `contact_person`, `contact_phone`, `contact_email`, `address`, `tax_number`, `bank_name`, `bank_account`, `payment_terms`, `credit_limit`, `description`, `is_active`) 
VALUES
('S202510220001', '北京华硕科技有限公司', '张经理', '13800138001', 'zhang@asus.com', '北京市朝阳区建国路88号', '110108MA001234', '中国工商银行北京朝阳支行', '6222001234567890123', 30, 100000.00, '主板、显卡供应商', 1),
('S202510220002', '深圳金士顿贸易公司', '李经理', '13800138002', 'li@kingston.com', '深圳市福田区华强北路168号', '440300MA002345', '中国建设银行深圳分行', '6227001234567890123', 60, 200000.00, '内存、固态硬盘供应商', 1),
('S202510220003', '上海Intel代理商', '王经理', '13800138003', 'wang@intel.com', '上海市浦东新区张江高科技园区', '310115MA003456', '中国农业银行上海浦东支行', '6228001234567890123', 45, 150000.00, 'CPU处理器供应商', 1),
('S202510220004', '广州西部数据科技', '赵经理', '13800138004', 'zhao@wd.com', '广州市天河区体育西路123号', '440106MA004567', '招商银行广州分行', '6214001234567890123', 30, 80000.00, '机械硬盘、移动硬盘供应商', 1);

-- =====================================================
-- 说明
-- =====================================================
-- 1. supplier_code: 供应商编号，格式 S+日期+序号，唯一
-- 2. payment_terms: 账期天数，0表示现结，30表示30天账期
-- 3. credit_limit: 信用额度，0表示不限额
-- 4. is_active: 1=启用，0=停用（软删除）
-- 5. 索引说明：
--    - uk_supplier_code: 供应商编号唯一索引
--    - idx_company_name: 公司名称索引，加快搜索
--    - idx_active: 状态索引，用于筛选
--    - idx_created_at: 创建时间索引，用于排序
