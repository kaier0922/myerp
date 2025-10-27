<?php
/**
 * =====================================================
 * 文件名：payment_add_receivable.php
 * 功能：新增应收账款
 * 描述：用于录入新的应收账款信息，包括客户选择、金额、到期日期等
 * 修复：解决日期为空时的数据库错误
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
$conn = getDBConnection();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'];
    $bill_no = $_POST['bill_no'];
    $bill_date = $_POST['bill_date'];
    $total_amount = $_POST['total_amount'];
    // 修复：如果日期为空字符串，设置为 NULL
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $notes = $_POST['notes'] ?? '';
    
    // 插入应收账款数据
    $sql = "INSERT INTO accounts_receivable 
            (customer_id, bill_no, bill_date, total_amount, outstanding_amount, due_date, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issddss", $customer_id, $bill_no, $bill_date, $total_amount, $total_amount, $due_date, $notes);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = '应收款添加成功！';
        header('Location: finance.php');
        exit;
    } else {
        $error = '添加失败：' . $conn->error;
    }
}

// 获取客户列表
$customers = $conn->query("SELECT id, company_name, contact_name FROM customers ORDER BY company_name");

// 自动生成账单编号
$today = date('Ymd');
$bill_no_prefix = "AR-{$today}-";
$last_bill = $conn->query("SELECT bill_no FROM accounts_receivable WHERE bill_no LIKE '{$bill_no_prefix}%' ORDER BY id DESC LIMIT 1")->fetch_assoc();
if ($last_bill) {
    $last_num = intval(substr($last_bill['bill_no'], -4));
    $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
} else {
    $new_num = '0001';
}
$default_bill_no = $bill_no_prefix . $new_num;

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增应收款 - 财务管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
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

        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            margin-bottom: 32px;
            padding-bottom: 16px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
        }

        .required {
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 24px;
        }

        .form-hint {
            font-size: 13px;
            color: #718096;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <a href="finance.php" class="navbar-brand">
            <span>💳</span>
            <span>新增应收款</span>
        </a>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="form-card">
            <div class="form-header">
                <h1 class="form-title">新增应收账款</h1>
            </div>

            <!-- 错误提示 -->
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- 应收款表单 -->
            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">账单编号 <span class="required">*</span></label>
                    <input type="text" name="bill_no" class="form-input" 
                           value="<?php echo $default_bill_no; ?>" required>
                    <div class="form-hint">系统自动生成，可修改</div>
                </div>

                <div class="form-group">
                    <label class="form-label">客户 <span class="required">*</span></label>
                    <select name="customer_id" class="form-select" required>
                        <option value="">请选择客户</option>
                        <?php while ($customer = $customers->fetch_assoc()): ?>
                            <option value="<?php echo $customer['id']; ?>">
                                <?php echo htmlspecialchars($customer['company_name']); ?> 
                                (<?php echo htmlspecialchars($customer['contact_name']); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">账单日期 <span class="required">*</span></label>
                    <input type="date" name="bill_date" class="form-input" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">应收金额 <span class="required">*</span></label>
                    <input type="number" name="total_amount" class="form-input" 
                           step="0.01" min="0" placeholder="0.00" required>
                    <div class="form-hint">单位：元</div>
                </div>

                <div class="form-group">
                    <label class="form-label">到期日期</label>
                    <input type="date" name="due_date" class="form-input">
                    <div class="form-hint">可选，用于提醒逾期</div>
                </div>

                <div class="form-group">
                    <label class="form-label">备注说明</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="可选填写账单相关说明..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" 
                            onclick="window.location.href='finance.php'">取消</button>
                    <button type="submit" class="btn btn-primary">💾 保存应收款</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>