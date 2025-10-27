<?php
/**
 * ============================================================================
 * 文件名: customer_edit.php
 * 版本: 1.0
 * 创建日期: 2025-10-13
 * 说明: 编辑客户页面
 * 
 * 功能说明：
 * 1. 加载现有客户数据
 * 2. 修改客户信息
 * 3. 提交到 customer_save.php 更新
 * ============================================================================
 */

session_start();
require_once 'config.php';

// ==================== 权限验证 ====================
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ==================== 获取客户ID ====================
$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($customer_id <= 0) {
    die('无效的客户ID');
}

// ==================== 连接数据库 ====================
$conn = getDBConnection();

// ==================== 加载客户数据 ====================
$sql = "SELECT * FROM customers WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('客户不存在');
}

$customer = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑客户 - <?php echo htmlspecialchars($customer['company_name']); ?></title>
    <style>
        /* ==================== 全局样式 ==================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        /* ==================== 导航栏 ==================== */
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
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
        }

        .navbar-actions {
            display: flex;
            gap: 12px;
        }

        /* ==================== 按钮样式 ==================== */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary {
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
        }

        /* ==================== 主内容区 ==================== */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        /* ==================== 表单卡片 ==================== */
        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* ==================== 表单样式 ==================== */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }

        .form-label.required::after {
            content: '*';
            color: #ef4444;
            margin-left: 4px;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-info {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            color: #1e3a8a;
        }

        /* ==================== 响应式 ==================== */
        @media (max-width: 768px) {
            .navbar {
                padding: 0 16px;
            }

            .main-content {
                padding: 16px;
            }

            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== 导航栏 ==================== -->
    <nav class="navbar">
        <div class="navbar-brand">👥 编辑客户</div>
        <div class="navbar-actions">
            <a href="customers.php" class="btn btn-secondary">返回列表</a>
            <button class="btn btn-success" onclick="updateCustomer()">保存修改</button>
        </div>
    </nav>

    <!-- ==================== 主内容区 ==================== -->
    <main class="main-content">
        <form id="customerForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $customer_id; ?>">

            <!-- ==================== 基本信息 ==================== -->
            <div class="form-card">
                <h2 class="section-title">基本信息</h2>

                <div class="alert alert-info">
                    <strong>📝 客户ID：</strong><?php echo $customer['id']; ?> | 
                    <strong>创建时间：</strong><?php echo $customer['created_at']; ?>
                </div>

                <div class="form-group">
                    <label class="form-label required">公司名称</label>
                    <input type="text" name="company_name" class="form-input" 
                           value="<?php echo htmlspecialchars($customer['company_name'] ?? ''); ?>" 
                           placeholder="请输入公司名称" required>
                </div>

                <div class="form-group">
                    <label class="form-label required">联系人</label>
                    <input type="text" name="contact_name" class="form-input" 
                           value="<?php echo htmlspecialchars($customer['contact_name'] ?? ''); ?>" 
                           placeholder="请输入联系人姓名" required>
                </div>

                <div class="form-group">
                    <label class="form-label required">联系电话</label>
                    <input type="tel" name="phone" class="form-input" 
                           value="<?php echo htmlspecialchars($customer['phone'] ?? ''); ?>" 
                           placeholder="请输入联系电话" required>
                    <div class="form-hint">示例：13800138000</div>
                </div>

                <div class="form-group">
                    <label class="form-label">邮箱</label>
                    <input type="email" name="email" class="form-input" 
                           value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>" 
                           placeholder="请输入邮箱地址（可选）">
                    <div class="form-hint">示例：example@company.com</div>
                </div>

                <div class="form-group">
                    <label class="form-label">公司地址</label>
                    <textarea name="address" class="form-textarea" 
                              placeholder="请输入公司地址（可选）" rows="3"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">备注</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="其他备注信息（可选）" rows="4"><?php echo htmlspecialchars($customer['notes'] ?? ''); ?></textarea>
                </div>
            </div>
        </form>
    </main>

    <!-- ==================== JavaScript ==================== -->
    <script>
        console.log('=== 编辑客户页面加载 ===');

        /**
         * 更新客户
         */
        function updateCustomer() {
            const form = document.getElementById('customerForm');

            // 验证表单
            if (!form.checkValidity()) {
                alert('请填写所有必填项！');
                return;
            }

            const formData = new FormData(form);

            // 显示加载状态
            const saveButton = event.target;
            saveButton.disabled = true;
            saveButton.textContent = '保存中...';

            fetch('customer_save.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.href = 'customers.php';
                } else {
                    alert('保存失败: ' + data.message);
                    saveButton.disabled = false;
                    saveButton.textContent = '保存修改';
                }
            })
            .catch(error => {
                console.error('保存错误:', error);
                alert('保存出错: ' + error.message);
                saveButton.disabled = false;
                saveButton.textContent = '保存修改';
            });
        }

        console.log('=== 初始化完成 ===');
    </script>
</body>
</html>