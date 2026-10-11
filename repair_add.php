<?php
/**
 * =====================================================
 * 文件名：repair_add.php
 * 功能：新增维修单
 * 描述：创建新的维修单，包括客户信息、设备信息、故障描述等
 * =====================================================
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'repair', 'create', '新建维修工单');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$conn = getDBConnection();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $repair_type = $_POST['repair_type'];
    $customer_id = !empty($_POST['customer_id']) ? $_POST['customer_id'] : null;
    $customer_name = $_POST['customer_name'];
    $contact_phone = $_POST['contact_phone'];
    $contact_address = $_POST['contact_address'] ?? '';
    
    $device_type = $_POST['device_type'] ?? '';
    $device_brand = $_POST['device_brand'] ?? '';
    $device_model = $_POST['device_model'] ?? '';
    $device_sn = $_POST['device_sn'] ?? '';
    
    $fault_description = $_POST['fault_description'];
    $appearance_check = $_POST['appearance_check'] ?? '';
    $accessories = $_POST['accessories'] ?? '';
    
    $receive_date = date('Y-m-d H:i:s');
    $expected_finish_date = !empty($_POST['expected_finish_date']) ? $_POST['expected_finish_date'] : null;
    
    $service_fee    = floatval($_POST['service_fee'] ?? 0);
    $notes          = $_POST['notes'] ?? '';
    $technician_id  = intval($_POST['technician_id'] ?? 0) ?: null;
    $tech_fee       = floatval($_POST['tech_fee'] ?? 0);

    // 获取工程师姓名
    $technician_name = '';
    if ($technician_id) {
        $t_stmt = $conn->prepare("SELECT name FROM technicians WHERE id=?");
        $t_stmt->bind_param("i", $technician_id);
        $t_stmt->execute();
        $t_row = $t_stmt->get_result()->fetch_assoc();
        $technician_name = $t_row['name'] ?? '';
    }
    
    // 生成维修单号
    $order_no = 'WX' . date('YmdHis');
    
    // 开始事务
    $conn->begin_transaction();
    
    try {
        // 插入维修单
        $sql = "INSERT INTO repair_orders 
                (order_no, repair_type, customer_id, customer_name, contact_phone, contact_address,
                 device_type, device_brand, device_model, device_sn, fault_description, 
                 appearance_check, accessories, receive_date, expected_finish_date, 
                 service_fee, total_fee, notes, created_by, user_id,
                 technician_id, technician, tech_fee, tech_fee_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissssssssssssddsiiiss", 
            $order_no, $repair_type, $customer_id, $customer_name, $contact_phone, $contact_address,
            $device_type, $device_brand, $device_model, $device_sn, $fault_description,
            $appearance_check, $accessories, $receive_date, $expected_finish_date,
            $service_fee, $service_fee, $notes, $user_id, $user_id,
            $technician_id, $technician_name, $tech_fee
        );
        $stmt->execute();
        $repair_id = $conn->insert_id;
        
        // 插入维修日志
        $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                    VALUES (?, 'pending', '创建维修单', ?, NOW())";
        $log_stmt = $conn->prepare($log_sql);
        $log_stmt->bind_param("is", $repair_id, $nickname);
        $log_stmt->execute();
        
        // 如果需要收费，创建应收款记录
        if ($service_fee > 0) {
            $payment_sql = "INSERT INTO repair_payments 
                           (repair_id, payment_type, amount, payment_date, notes, created_by) 
                           VALUES (?, 'receivable', ?, ?, '维修费用应收', ?)";
            $payment_stmt = $conn->prepare($payment_sql);
            $payment_stmt->bind_param("idsi", $repair_id, $service_fee, $receive_date, $user_id);
            $payment_stmt->execute();
        }
        
        $conn->commit();
        $_SESSION['message'] = '维修单创建成功！';
        header('Location: repair.php');
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = '创建失败：' . $e->getMessage();
    }
}

// 获取客户列表
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name, contact_name, phone FROM customers WHERE " . tenantWhere() . " ORDER BY company_name");

// 提前查好工程师列表（conn close之前）
$tech_list_data = [];
$tech_res = $conn->query("SELECT id, name, employment_type, fee_rate FROM technicians WHERE status='active' AND " . tenantWhere() . " ORDER BY name");
if ($tech_res) {
    while ($t = $tech_res->fetch_assoc()) {
        $tech_list_data[] = $t;
    }
}
$conn->close();
?>
<?php $page_title = '新增维修单 - 维修管理系统'; include __DIR__ . '/head.php'; ?>
    <style>

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

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }

        .required {
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
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

        /* 统一按钮样式 */

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

        .radio-group {
            display: flex;
            gap: 20px;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .radio-item label {
            cursor: pointer;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar" style="justify-content:space-between;">
        <a href="repair.php" class="navbar-brand">
            <span>🔧</span>
            <span>新增维修单</span>
        </a>
        <a href="repair.php" style="padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;">← 返回列表</a>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="form-card">
            <div class="form-header">
                <h1 class="form-title">新增维修单</h1>
            </div>

            <!-- 错误提示 -->
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- 维修单表单 -->
            <form method="POST" action="">
                <!-- 基本信息 -->
                <div class="form-section">
                    <h3 class="section-title">基本信息</h3>
                    
                    <div class="form-group">
                        <label class="form-label">维修类型 <span class="required">*</span></label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="onsite" name="repair_type" value="onsite" checked>
                                <label for="onsite">上门服务</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="inshop" name="repair_type" value="inshop">
                                <label for="inshop">带回维修</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 客户信息 -->
                <div class="form-section">
                    <h3 class="section-title">客户信息</h3>
                    
                    <!-- 一行四列：选择客户 + 客户姓名 + 联系电话 + 联系地址 -->
                    <div style="display:grid;grid-template-columns:2fr 1fr 1fr 2fr;gap:14px;align-items:end;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">选择客户</label>
                            <select name="customer_id" class="form-select" onchange="fillCustomerInfo(this)">
                                <option value="">-- 手动输入或选择 --</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($customer['company_name']); ?>"
                                            data-contact="<?php echo htmlspecialchars($customer['contact_name']); ?>"
                                            data-phone="<?php echo htmlspecialchars($customer['phone']); ?>">
                                        <?php echo htmlspecialchars($customer['company_name']); ?>
                                        (<?php echo htmlspecialchars($customer['contact_name']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">客户姓名 <span class="required">*</span></label>
                            <input type="text" name="customer_name" id="customer_name" class="form-input"
                                   placeholder="请输入客户姓名" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">联系电话 <span class="required">*</span></label>
                            <input type="text" name="contact_phone" id="contact_phone" class="form-input"
                                   placeholder="请输入联系电话" required>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">联系地址</label>
                            <input type="text" name="contact_address" class="form-input"
                                   placeholder="请输入联系地址（上门服务必填）">
                        </div>
                    </div>
                </div>

                <!-- 设备信息 -->
                <div class="form-section">
                    <h3 class="section-title">设备信息</h3>
                    
                    <div class="form-row" style="grid-template-columns: repeat(4, 1fr);">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">设备类型</label>
                            <input type="text" name="device_type" class="form-input" 
                                   placeholder="如：电脑、打印机">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">品牌</label>
                            <input type="text" name="device_brand" class="form-input" 
                                   placeholder="如：联想、惠普">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">型号</label>
                            <input type="text" name="device_model" class="form-input" 
                                   placeholder="设备型号">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">序列号</label>
                            <input type="text" name="device_sn" class="form-input" 
                                   placeholder="设备序列号/SN">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">故障描述 <span class="required">*</span></label>
                        <textarea name="fault_description" class="form-textarea" 
                                  placeholder="请详细描述设备故障现象..." required></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">外观检查</label>
                        <textarea name="appearance_check" class="form-textarea" 
                                  placeholder="记录设备外观状况，如：有划痕、屏幕完好等..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">附带配件</label>
                        <input type="text" name="accessories" class="form-input" 
                               placeholder="如：电源适配器、鼠标、数据线等">
                    </div>
                </div>

                <!-- 维修信息 -->
                <div class="form-section">
                    <h3 class="section-title">维修信息</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">预计完成日期</label>
                            <input type="date" name="expected_finish_date" class="form-input">
                        </div>

                        <div class="form-group">
                            <label class="form-label">服务费用</label>
                            <input type="number" name="service_fee" id="service_fee" class="form-input" 
                                   step="any" min="0" value="0" placeholder="0.00"
                                   oninput="calcTechFee()">
                            <div class="form-hint">维修完成后可修改</div>
                        </div>
                    </div>

                    <!-- 工程师选择 -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">指派工程师（可选）</label>
                            <select name="technician_id" id="technician_id" class="form-select"
                                    onchange="onTechChange(this)">
                                <option value="">-- 暂不指派 --</option>
                                <?php
                                foreach ($tech_list_data as $t):
                                    $emp = ['fulltime'=>'全职','parttime'=>'兼职','contractor'=>'外包'][$t['employment_type']] ?? '';
                                ?>
                                <option value="<?=$t['id']?>"
                                    data-emp="<?=$t['employment_type']?>"
                                    data-rate="<?=$t['fee_rate']?>">
                                    <?=htmlspecialchars($t['name'])?> (<?=$emp?> · 默认<?=$t['fee_rate']?>%)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">工程师分成金额（元）</label>
                            <input type="number" name="tech_fee" id="tech_fee" class="form-input"
                                   step="any" min="0" value="0" placeholder="0.00">
                            <div class="form-hint" id="tech_fee_hint" style="color:#1f6dad;">
                                选择工程师后自动计算，可手动修改
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">备注</label>
                        <textarea name="notes" class="form-textarea" 
                                  placeholder="其他需要说明的信息..."></textarea>
                    </div>
                </div>

                <!-- 操作按钮 -->
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" 
                            onclick="window.location.href='repair.php'">取消</button>
                    <button type="submit" class="btn btn-primary">💾 创建维修单</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // 工程师分成自动计算
        function onTechChange(sel) {
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) {
                document.getElementById('tech_fee').value = '0';
                document.getElementById('tech_fee_hint').textContent = '选择工程师后自动计算，可手动修改';
                return;
            }
            const emp  = opt.dataset.emp;
            const rate = parseFloat(opt.dataset.rate) || 0;
            calcTechFee(emp, rate);
        }

        function calcTechFee(emp, rate) {
            const sel = document.getElementById('technician_id');
            if (!emp || !rate) {
                const opt = sel.options[sel.selectedIndex];
                if (!opt || !opt.value) return;
                emp  = opt.dataset.emp;
                rate = parseFloat(opt.dataset.rate) || 0;
            }
            const serviceFee = parseFloat(document.getElementById('service_fee').value) || 0;
            let base = 0, hint = '';

            if (emp === 'contractor') {
                // 外包：总收入 × 比例（工程师自负配件）
                base = serviceFee;
                hint = `外包工程师：总服务费 ¥${serviceFee.toFixed(2)} × ${rate}% = ¥${(base * rate / 100).toFixed(2)}`;
            } else {
                // 内部：服务费 × 比例（配件公司承担，不计入分成基数）
                base = serviceFee;
                hint = `内部工程师：服务费 ¥${serviceFee.toFixed(2)} × ${rate}% = ¥${(base * rate / 100).toFixed(2)}`;
            }

            const fee = parseFloat((base * rate / 100).toFixed(2));
            document.getElementById('tech_fee').value = fee;
            document.getElementById('tech_fee_hint').textContent = hint;
        }

        /**
         * 填充客户信息
         */
        function fillCustomerInfo(select) {
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('customer_name').value = option.dataset.contact || '';
                document.getElementById('contact_phone').value = option.dataset.phone || '';
            }
        }
    </script>
</body>
</html>