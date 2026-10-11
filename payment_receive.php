<?php
/**
 * =====================================================
 * 文件名：payment_receive.php
 * 功能：应收款收款记录
 * 描述：用于录入收款信息，更新应收账款状态和金额，支持生成收据
 * 修复：解决 bind_param 参数数量不匹配问题
 * =====================================================
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'finance', 'receive', '收款');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

$ar_id = $_GET['id'] ?? 0;

// 获取应收账款信息
$ar_info = $conn->query("
    SELECT ar.*, COALESCE(c.short_name, c.company_name) AS company_name, c.contact_name
    FROM accounts_receivable ar
    LEFT JOIN customers c ON ar.customer_id = c.id
    WHERE ar.id = $ar_id AND ar.user_id = " . intval($_SESSION['user_id']) . "
")->fetch_assoc();

if (!$ar_info) {
    die('账款不存在');
}

// 处理收款提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);
    $action = $_POST['action'] ?? 'payment';
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $notes = $_POST['notes'] ?? '';
    $generate_receipt = isset($_POST['generate_receipt']) ? 1 : 0;
    
    // 开始数据库事务
    $conn->begin_transaction();
    
    try {
        // 生成收款记录编号
        $record_no = 'RC-' . date('YmdHis') . '-' . str_pad($ar_id, 6, '0', STR_PAD_LEFT);
        
        // 如果需要生成收据，生成收据编号
        $receipt_no = null;
        if ($generate_receipt) {
            $receipt_no = 'SJ-' . date('Ymd') . '-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        }
        
        // 插入收款记录
        if ($action !== 'writeoff') {
        $sql1 = "INSERT INTO payment_records 
                 (user_id, record_no, payment_type, related_type, related_id, amount, payment_method, payment_date, operator_id, notes, generate_receipt, receipt_no) 
                 VALUES (?, ?, '收款', '应收账款', ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("isidssisis", $user_id, $record_no, $ar_id, $amount, $payment_method, $payment_date, $user_id, $notes, $generate_receipt, $receipt_no);
        $stmt1->execute();
        $payment_record_id = $conn->insert_id;
        } // end if action !== writeoff
        
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
        
        $sql2 = "UPDATE accounts_receivable SET paid_amount = ?, outstanding_amount = ?, status = ? WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ddsi", $new_paid, $new_outstanding, $new_status, $ar_id);
        $stmt2->execute();

        // 回写送货单付款状态
        if (($ar_info['related_type'] ?? '') === 'delivery' && intval($ar_info['related_id'] ?? 0) > 0) {
            $did = intval($ar_info['related_id']);
            $dq = $conn->prepare("SELECT total_amount, paid_amount FROM deliveries WHERE id=?");
            $dq->bind_param('i', $did); $dq->execute();
            $dd = $dq->get_result()->fetch_assoc(); $dq->close();
            if ($dd) {
                $new_d_paid = floatval($dd['paid_amount']) + $amount;
                $d_status = $new_d_paid >= floatval($dd['total_amount']) ? 'paid' : 'partial';
                $ud = $conn->prepare("UPDATE deliveries SET paid_amount=?, payment_status=? WHERE id=?");
                $ud->bind_param('dsi', $new_d_paid, $d_status, $did);
                $ud->execute(); $ud->close();
            }
        }

        // 生成收据（核销不生成）
        $receipt_view_id = 0;
        if ($generate_receipt && $action !== 'writeoff') {
            $datePart = date('Ymd');
            $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM receipts WHERE DATE(created_at)=CURDATE()")->fetch_assoc();
            $seq = str_pad((int)($cntRes['cnt'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
            $r_no = "R{$datePart}-{$seq}";
            $cid = $ar_info['customer_id'] ?? 0;
            $wname = $cid ? '' : '散客';
            $rst = $conn->prepare("INSERT INTO receipts (receipt_no, customer_id, walkin_name, related_type, related_id, subtotal, tax_amount, total_amount, paid_amount, payment_method, note, created_by, user_id) VALUES (?,?,?,'receivable',?,?,0,?,?,?,?,?,?)");
            $rst->bind_param('sisidddssii', $r_no, $cid, $wname, $ar_id, $amount, $amount, $amount, $payment_method, $notes, $user_id, $user_id);
            if (!$rst->execute()) {
                error_log('[收据INSERT失败] ' . $rst->error . ' cid=' . $cid . ' ar_id=' . $ar_id . ' amount=' . $amount);
            }
            $receipt_view_id = $conn->insert_id;
            $rst->close();
        }
        // ── 全额收款时自动更新报价单为已成交 ──
        if ($new_status === '已收款') {
            $uid_tmp = intval($_SESSION['user_id']);
            $qs = $conn->prepare("SELECT q.id FROM quotes q JOIN deliveries d ON d.quote_id=q.id JOIN accounts_receivable ar ON ar.related_id=d.id AND ar.related_type='delivery' WHERE ar.id=? AND q.user_id=? AND q.status != '已成交'");
            $qs->bind_param('ii', $ar_id, $uid_tmp);
            $qs->execute();
            $qr = $qs->get_result()->fetch_assoc();
            $qs->close();
            if ($qr) {
                $qu = $conn->prepare("UPDATE quotes SET status='已成交' WHERE id=? AND user_id=?");
                $qu->bind_param('ii', $qr['id'], $uid_tmp);
                $qu->execute();
                $qu->close();
            }
        }

        // ── 写收款流水 + 更新客户累积余额 ──
        $cid2 = intval($ar_info['customer_id'] ?? 0);
        if ($cid2 > 0) {
            $bal_s = $conn->prepare("SELECT balance_delivery, balance_repair, balance_received FROM customers WHERE id=? AND user_id=?");
            $bal_s->bind_param('ii', $cid2, $user_id);
            $bal_s->execute();
            $bal_r = $bal_s->get_result()->fetch_assoc();
            $bal_s->close();
            $cur_d = floatval($bal_r['balance_delivery'] ?? 0);
            $cur_rp = floatval($bal_r['balance_repair']  ?? 0);
            $cur_rc = floatval($bal_r['balance_received'] ?? 0);
            $neg_amt = -floatval($amount);
            $bal_after = $cur_d + $cur_rp - ($cur_rc + floatval($amount));
            $rmk = "收款 ¥" . number_format($amount, 2) . "，方式：{$payment_method}";
            $rel_no = $ar_info['bill_no'] ?? '';
            $led = $conn->prepare("INSERT INTO receivable_ledger (user_id, customer_id, type, ref_id, ref_no, amount, balance_after, remark, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
            $led_type = ($action === 'writeoff') ? 'writeoff' : 'payment';
            $rmk = ($action === 'writeoff')
                ? "核销/优惠 ¥" . number_format($amount, 2) . "，原因：{$notes}"
                : "收款 ¥" . number_format($amount, 2) . "，方式：{$payment_method}";
            $led->bind_param('iisisddsi', $user_id, $cid2, $led_type, $ar_id, $rel_no, $neg_amt, $bal_after, $rmk, $user_id);
            $led->execute();
            $led->close();
            $upd = $conn->prepare("UPDATE customers SET balance_received = balance_received + ? WHERE id=? AND user_id=?");
            $upd->bind_param('dii', $amount, $cid2, $user_id);
            $upd->execute();
            $upd->close();
        }

        // 提交事务
        $conn->commit();
        $_SESSION['message'] = '收款记录成功！';
        
        if ($generate_receipt && $receipt_view_id) {
            header("Location: receipt_view.php?id=$receipt_view_id");
        } else {
            header('Location: finance.php');
        }
        exit;
        
    } catch (Exception $e) {
        // 回滚事务
        $conn->rollback();
        $error = '收款失败：' . $e->getMessage();
    }
}

$conn->close();
?>
<?php $page_title = '收款记录 - 财务管理系统'; include __DIR__ . '/head.php'; ?>
    <style>

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
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* 复选框样式 */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px;
            background: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            font-weight: 500;
            color: #92400e;
            margin: 0;
        }

        .receipt-hint {
            font-size: 13px;
            color: #92400e;
            margin-top: 8px;
            padding-left: 28px;
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
                           step="any" min="0.01" 
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

                <!-- 收据选项 -->
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="generate_receipt" id="generate_receipt" value="1">
                        <label for="generate_receipt">📄 生成收据（适用于需要报销凭证的客户）</label>
                    </div>
                    <div class="receipt-hint">💡 勾选后将自动生成可打印的收据，适用于维修、购买产品等需要报销凭证的场景</div>
                </div>

                <div class="form-group">
                    <label class="form-label">备注说明</label>
                    <textarea name="notes" class="form-textarea" 
                              placeholder="可选填写收款相关说明，如：维修电脑款项、购买配件款项等..."></textarea>
                </div>

                <div class="form-actions">
                    <input type="hidden" name="action" id="form_action" value="payment">
                    <button type="button" class="btn btn-secondary" 
                            onclick="window.location.href='finance.php'">取消</button>
                    <button type="button" class="btn btn-warning" style="background:#f59e0b;color:#fff;border:none;"
                            onclick="if(confirm('确认将此笔款项核销？核销后视为已结清，不再追收。')){document.getElementById('form_action').value='writeoff';var ai=document.querySelector('input[name=amount]');if(!ai.value){ai.value=ai.max;}document.querySelector('form').removeAttribute('onsubmit');document.querySelector('form').submit();}">
                        ✗ 核销/优惠
                    </button>
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
                hint.style.color = '#1f6dad';
                hint.textContent = '✓ 收款后剩余: ¥' + remaining.toFixed(2);
            }
        });
    </script>
</body>
</html>
