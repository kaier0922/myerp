-- =====================================================
-- 公章管理表
-- =====================================================

CREATE TABLE IF NOT EXISTS `seals` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '公章ID',
  `seal_name` varchar(200) NOT NULL COMMENT '公章名称',
  `seal_type` varchar(50) NOT NULL COMMENT '公章类型（公章/财务章/合同章/法人章）',
  `file_path` varchar(255) NOT NULL COMMENT '公章文件路径',
  `description` text COMMENT '备注说明',
  `is_default` tinyint DEFAULT '0' COMMENT '是否默认:1=默认,0=非默认',
  `uploaded_by` int NOT NULL COMMENT '上传人ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_uploaded_by` (`uploaded_by`),
  KEY `idx_is_default` (`is_default`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='公章管理表';

-- =====================================================
-- 文档公章配置表（保存每个文档的公章位置）
-- =====================================================

CREATE TABLE IF NOT EXISTS `document_seals` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `document_type` varchar(50) NOT NULL COMMENT '文档类型（quotation/delivery）',
  `document_id` int NOT NULL COMMENT '文档ID',
  `seal_id` int NOT NULL COMMENT '公章ID',
  `position_x` int DEFAULT '0' COMMENT 'X坐标',
  `position_y` int DEFAULT '0' COMMENT 'Y坐标',
  `seal_size` int DEFAULT '100' COMMENT '公章大小（像素）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_document` (`document_type`, `document_id`),
  KEY `idx_seal_id` (`seal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文档公章配置表';
