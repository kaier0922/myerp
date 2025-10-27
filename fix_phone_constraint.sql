-- 方案2: 修改数据库，让 phone 可以为空或移除唯一约束

USE bjd;

-- 方案2A: 移除 phone 的 UNIQUE 约束（推荐）
-- 这样多个用户可以不填手机号

-- 1. 删除 phone 的唯一索引
ALTER TABLE users DROP INDEX phone;

-- 2. 修改 phone 字段允许为 NULL
ALTER TABLE users MODIFY COLUMN phone VARCHAR(20) NULL COMMENT '手机号，可选';

-- 验证修改
SELECT '=== 修改后的 users 表结构 ===' AS '';
DESCRIBE users;

SELECT '=== phone 字段索引检查 ===' AS '';
SHOW INDEX FROM users WHERE Column_name = 'phone';

SELECT '=== 测试：现在可以有多个空 phone 了 ===' AS Status;
