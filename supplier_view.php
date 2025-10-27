<?php
/**
 * =====================================================
 * 文件名：supplier_view.php
 * 功能：查看供应商详情
 * 描述：查看供应商的详细信息和往来记录
 * 版本：1.0
 * 更新日期：2025-10-22
 * =====================================================
 */

session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// 获取供应商ID
$supplier_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($supplier_id <= 0) {
    $_SESSION['error_message'] = '无效的供应商ID';
    header('Location: suppliers.php');
    exit;
}

// ==================== 获取供应商信息 ====================
$stmt = $conn->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = '供应商不存在';
    header('Location: suppliers.php');
    exit;
}

$supplier = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>供应商详情 - 企业管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
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

        .navbar-user {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1000px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            line-height: 1.5;
        }

        .btn-back {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        .info-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .info-section-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }

        .info-value {
            font-size: 15px;
            color: #1a202c;
            word-break: break-all;
        }

        .info-value.empty {
            color: #cbd5e0;
            font-style: italic;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px 32px;
            border-radius: 12px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-banner.inactive {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .banner-info h2 {
            font-size: 24px;
            margin-bottom: 8px;
        }

        .banner-info p {
            opacity: 0.9;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span>📊</span>
            <span>企业管理系统</span>
        </a>
        <div class="navbar-user">
            <span>👤 <?php echo htmlspecialchars($nickname); ?></span>
        </div>
    </nav>

    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">供应商详情</h1>
            <div class="btn-group">
                <a href="suppliers.php" class="btn btn-back">← 返回列表</a>
                <a href="supplier_edit.php?id=<?php echo $supplier_id; ?>" class="btn btn-primary">✏️ 编辑</a>
            </div>
        </div>

        <!-- 状态横幅 -->
        <div class="status-banner <?php echo ($supplier['is_active'] == 0) ? 'inactive' : ''; ?>">
            <div class="banner-info">
                <h2><?php echo htmlspecialchars($supplier['company_name']); ?></h2>
                <p>供应商编号：<?php echo htmlspecialchars($supplier['supplier_code']); ?></p>
            </div>
            <div>
                <?php if ($supplier['is_active'] == 1): ?>
                    <span class="badge badge-success" style="font-size: 14px; padding: 8px 16px;">✓ 合作中</span>
                <?php else: ?>
                    <span class="badge badge-danger" style="font-size: 14px; padding: 8px 16px;">✗ 已停用</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 基本信息 -->
        <div class="info-card">
            <h3 class="info-section-title">
                <span>📋</span>
                <span>基本信息</span>
            </h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">公司名称</div>
                    <div class="info-value"><?php echo htmlspecialchars($supplier['company_name']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">联系人</div>
                    <div class="info-value"><?php echo htmlspecialchars($supplier['contact_person']); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">联系电话</div>
                    <div class="info-value">
                        <a href="tel:<?php echo htmlspecialchars($supplier['contact_phone']); ?>" style="color: #667eea; text-decoration: none;">
                            <?php echo htmlspecialchars($supplier['contact_phone']); ?>
                        </a>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">联系邮箱</div>
                    <div class="info-value <?php echo empty($supplier['contact_email']) ? 'empty' : ''; ?>">
                        <?php if ($supplier['contact_email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($supplier['contact_email']); ?>" style="color: #667eea; text-decoration: none;">
                                <?php echo htmlspecialchars($supplier['contact_email']); ?>
                            </a>
                        <?php else: ?>
                            未填写
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item" style="grid-column: 1 / -1;">
                    <div class="info-label">公司地址</div>
                    <div class="info-value <?php echo empty($supplier['address']) ? 'empty' : ''; ?>">
                        <?php echo $supplier['address'] ? htmlspecialchars($supplier['address']) : '未填写'; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 财务信息 -->
        <div class="info-card">
            <h3 class="info-section-title">
                <span>💰</span>
                <span>财务信息</span>
            </h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">税号</div>
                    <div class="info-value <?php echo empty($supplier['tax_number']) ? 'empty' : ''; ?>">
                        <?php echo $supplier['tax_number'] ? htmlspecialchars($supplier['tax_number']) : '未填写'; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">开户行</div>
                    <div class="info-value <?php echo empty($supplier['bank_name']) ? 'empty' : ''; ?>">
                        <?php echo $supplier['bank_name'] ? htmlspecialchars($supplier['bank_name']) : '未填写'; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">银行账号</div>
                    <div class="info-value <?php echo empty($supplier['bank_account']) ? 'empty' : ''; ?>">
                        <?php echo $supplier['bank_account'] ? htmlspecialchars($supplier['bank_account']) : '未填写'; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">账期</div>
                    <div class="info-value">
                        <?php 
                        if ($supplier['payment_terms'] == 0) {
                            echo '现结';
                        } else {
                            echo $supplier['payment_terms'] . ' 天';
                        }
                        ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">信用额度</div>
                    <div class="info-value">
                        <?php 
                        if ($supplier['credit_limit'] == 0) {
                            echo '不限额';
                        } else {
                            echo '¥' . number_format($supplier['credit_limit'], 2);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 备注信息 -->
        <?php if ($supplier['description']): ?>
        <div class="info-card">
            <h3 class="info-section-title">
                <span>📝</span>
                <span>备注说明</span>
            </h3>
            <div style="color: #4a5568; line-height: 1.6;">
                <?php echo nl2br(htmlspecialchars($supplier['description'])); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 系统信息 -->
        <div class="info-card">
            <h3 class="info-section-title">
                <span>⏱️</span>
                <span>系统信息</span>
            </h3>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">创建时间</div>
                    <div class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($supplier['created_at'])); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">最后更新</div>
                    <div class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($supplier['updated_at'])); ?></div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
