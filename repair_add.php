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

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
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
    
    $service_fee = $_POST['service_fee'] ?? 0;
    $notes = $_POST['notes'] ?? '';
    
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
                 service_fee, total_fee, notes, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssissssssssssssddsi", 
            $order_no, $repair_type, $customer_id, $customer_name, $contact_phone, $contact_address,
            $device_type, $device_brand, $device_model, $device_sn, $fault_description,
            $appearance_check, $accessories, $receive_date, $expected_finish_date,
            $service_fee, $service_fee, $notes, $user_id
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
$customers = $conn->query("SELECT id, company_name, contact_name, phone FROM customers ORDER BY company_name");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>新增维修单 - 维修管理系统</title>
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
            max-width: 1000px;
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

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
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

        /* 统一按钮样式 */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            line-height: 1.5;
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
    <nav class="navbar">
        <a href="repair.php" class="navbar-brand">
            <span>🔧</span>
            <span>新增维修单</span>
        </a>
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
                                <input type="radio" id="inshop" name="repair_type" value="inshop" checked>
                                <label for="inshop">带回维修</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="onsite" name="repair_type" value="onsite">
                                <label for="onsite">上门服务</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 客户信息 -->
                <div class="form-section">
                    <h3 class="section-title">客户信息</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">选择客户</label>
                            <select name="customer_id" class="form-select" onchange="fillCustomerInfo(this)">
                                <option value="">-- 手动输入或选择 --</option>
                                <?php while ($customer = $customers->fetch_assoc()): ?>
                                    <option value="<?php echo $customer['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($customer['company_name']); ?>"
                                            data-phone="<?php echo htmlspecialchars($customer['phone']); ?>">
                                        <?php echo htmlspecialchars($customer['company_name']); ?>
                                        (<?php echo htmlspecialchars($customer['contact_name']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">客户姓名 <span class="required">*</span></label>
                            <input type="text" name="customer_name" id="customer_name" class="form-input" 
                                   placeholder="请输入客户姓名" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">联系电话 <span class="required">*</span></label>
                            <input type="text" name="contact_phone" id="contact_phone" class="form-input" 
                                   placeholder="请输入联系电话" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">联系地址</label>
                        <input type="text" name="contact_address" class="form-input" 
                               placeholder="请输入联系地址（上门服务必填）">
                    </div>
                </div>

                <!-- 设备信息 -->
                <div class="form-section">
                    <h3 class="section-title">设备信息</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">设备类型</label>
                            <input type="text" name="device_type" class="form-input" 
                                   placeholder="如：电脑、打印机">
                        </div>

                        <div class="form-group">
                            <label class="form-label">品牌</label>
                            <input type="text" name="device_brand" class="form-input" 
                                   placeholder="如：联想、惠普">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">型号</label>
                            <input type="text" name="device_model" class="form-input" 
                                   placeholder="设备型号">
                        </div>

                        <div class="form-group">
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
                            <input type="number" name="service_fee" class="form-input" 
                                   step="0.01" min="0" value="0" placeholder="0.00">
                            <div class="form-hint">维修完成后可修改</div>
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
        /**
         * 填充客户信息
         */
        function fillCustomerInfo(select) {
            const option = select.options[select.selectedIndex];
            if (option.value) {
                document.getElementById('customer_name').value = option.dataset.name || '';
                document.getElementById('contact_phone').value = option.dataset.phone || '';
            }
        }
    </script>
</body>
</html>