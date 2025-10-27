<?php
/**
 * =====================================================
 * 文件名：payment_receive.php
 * 功能：应收款收款记录
 * 描述：用于录入收款信息，更新应收账款状态和金额
 * 修复：解决 bind_param 参数数量不匹配问题
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

$ar_id = $_GET['id'] ?? 0;

// 获取应收账款信息
$ar_info = $conn->query("
    SELECT ar.*, c.company_name, c.contact_name
    FROM accounts_receivable ar
    LEFT JOIN customers c ON ar.customer_id = c.id
    WHERE ar.id = $ar_id
")->fetch_assoc();

if (!$ar_info) {
    die('账款不存在');
}

// 处理收款提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $notes = $_POST['notes'] ?? '';
    
    // 开始数据库事务
    $conn->begin_transaction();
    
    try {
        // 生成收款记录编号
        $record_no = 'RC-' . date('YmdHis') . '-' . str_pad($ar_id, 6, '0', STR_PAD_LEFT);
        
        // 插入收款记录
        $sql1 = "INSERT INTO payment_records 
                 (record_no, payment_type, related_type, related_id, amount, payment_method, payment_date, operator_id, notes) 
                 VALUES (?, '收款', '应收账款', ?, ?, ?, ?, ?, ?)";
        $stmt1 = $conn->prepare($sql1);
        // 修复：类型字符串应该是 "sidssis" (7个参数)，而不是 "sidssiss" (8个)
        $stmt1->bind_param("sidssis", $record_no, $ar_id, $amount, $payment_method, $payment_date, $user_id, $notes);
        $stmt1->execute();
        
        // 更新应收账款金额和状态
        $new_paid = $ar_info['paid_amount'] + $amount;
        $new_outstanding = $ar_info['outstanding_amount'] - $amount;
        
        // 根据剩余金额判断状态
        if ($new_outstanding <= 0) {
            $new_status = '已收款';
            $new_outstanding = 0;
        } elseif ($new_paid > 0) {
            $new_status = '部分收款';
        } else {
            $new_status = $ar_info['status'];
        }
        
        $sql2 = "UPDATE accounts_receivable 
                 SET paid_amount = ?, outstanding_amount = ?, status = ? 
                 WHERE id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ddsi", $new_paid, $new_outstanding, $new_status, $ar_id);
        $stmt2->execute();
        
        // 提交事务
        $conn->commit();
        $_SESSION['message'] = '收款记录成功！';
        header('Location: finance.php');
        exit;
        
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $error = '收款失败：' . $e->getMessage();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>收款记录 - 财务管理系统</title>
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

        /* 账款信息卡片样式 */
        .info-card {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .info-row:last-child {
            margin-bottom: 0;
        }

        .info-label {
            color: #4c51bf;
            font-weight: 500;
        }

        .info-value {
            color: #1a202c;
            font-weight: 600;
        }

        .amount-highlight {
            font-size: 24px;
            color: #dc2626;
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
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
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
            <span>💵</span>
            <span>收款记录</span>
        </a>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 账款信息展示 -->
        <div class="info-card">
            <div class="info-row">
                <span class="info-label">账单编号:</span>
                <span class="info-value"><?php echo htmlspecialchars($ar_info['bill_no']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">客户名称:</span>
                <span class="info-value"><?php echo htmlspecialchars($ar_info['company_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">应收总额:</span>
                <span class="info-value">¥<?php echo number_format($ar_info['total_amount'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">已收金额:</span>
                <span class="info-value">¥<?php echo number_format($ar_info['paid_amount'], 2); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">待收金额:</span>
                <span class="amount-highlight">¥<?php echo number_format($ar_info['outstanding_amount'], 2); ?></span>
            </div>
        </div>

        <!-- 收款表单 -->
        <div class="form-card">
            <div class="form-header">
                <h1 class="form-title">录入收款信息</h1>
            </div>

            <!-- 错误提示 -->
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">收款金额 <span class="required">*</span></label>
                    <input type="number" name="amount" class="form-input" 
                           step="0.01" min="0.01" 
                           max="<?php echo $ar_info['outstanding_amount']; ?>"
                           placeholder="0.00" required>
                    <div class="form-hint">最多可收: ¥<?php echo number_format($ar_info['outstanding_amount'], 2); ?></div>
                </div>

                <div class="form-group">
                    <label class="form-label">收款方式 <span class="required">*</span></label>
                    <select name="payment_method" class="form-select" required>
                        <option value="">请选择收款方式</option>
                        <option value="现金">现金</option>
                        <option value="银行转账">银行转账</option>
                        <option value="支票">支票</option>
                        <option value="微信">微信</option>
                        <option value="支付宝">支付宝</option>
                        <option value="其他">其他</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">收款日期 <span class="required">*</span></label>
                    <input type="date" name="payment_date" class="form-input" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">备注说明</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="可选填写收款相关说明..."></textarea>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" 
                            onclick="window.location.href='finance.php'">取消</button>
                    <button type="submit" class="btn btn-primary">✓ 确认收款</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // 实时计算剩余金额
        document.querySelector('input[name="amount"]').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const outstanding = <?php echo $ar_info['outstanding_amount']; ?>;
            const remaining = outstanding - amount;
            
            const hint = this.nextElementSibling;
            if (remaining < 0) {
                hint.style.color = '#dc2626';
                hint.textContent = '⚠️ 超出待收金额！';
            } else {
                hint.style.color = '#10b981';
                hint.textContent = '✓ 收款后剩余: ¥' + remaining.toFixed(2);
            }
        });
    </script>
</body>
</html>