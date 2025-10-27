<?php
/**
 * =====================================================
 * 文件名:repair_edit.php
 * 功能:编辑维修单
 * 描述:修改维修单信息,更新状态,添加配件,记录维修进度
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

// 获取维修单ID
$repair_id = $_GET['id'] ?? 0;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    $conn->begin_transaction();
    
    try {
        if ($action === 'update_info') {
            // 更新基本信息
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
            
            $expected_finish_date = !empty($_POST['expected_finish_date']) ? $_POST['expected_finish_date'] : null;
            $technician = $_POST['technician'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            $sql = "UPDATE repair_orders SET 
                    customer_name = ?, contact_phone = ?, contact_address = ?,
                    device_type = ?, device_brand = ?, device_model = ?, device_sn = ?,
                    fault_description = ?, appearance_check = ?, accessories = ?,
                    expected_finish_date = ?, technician = ?, notes = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssssi",
                $customer_name, $contact_phone, $contact_address,
                $device_type, $device_brand, $device_model, $device_sn,
                $fault_description, $appearance_check, $accessories,
                $expected_finish_date, $technician, $notes, $repair_id
            );
            $stmt->execute();
            
            // 记录日志
            $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                        VALUES (?, 'updated', '更新维修单信息', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("is", $repair_id, $nickname);
            $log_stmt->execute();
            
            $_SESSION['message'] = '维修单信息更新成功!';
            
        } elseif ($action === 'update_status') {
            // 更新状态
            $new_status = $_POST['status'];
            $repair_result = $_POST['repair_result'] ?? '';
            $actual_finish_date = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
            
            $sql = "UPDATE repair_orders SET 
                    status = ?, repair_result = ?, actual_finish_date = ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $new_status, $repair_result, $actual_finish_date, $repair_id);
            $stmt->execute();
            
            // 记录日志
            $status_map = [
                'pending' => '待处理',
                'repairing' => '维修中',
                'testing' => '测试中',
                'completed' => '已完成',
                'delivered' => '已交付',
                'cancelled' => '已取消'
            ];
            
            $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                        VALUES (?, ?, ?, ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_description = '状态变更为: ' . $status_map[$new_status];
            $log_stmt->bind_param("isss", $repair_id, $new_status, $log_description, $nickname);
            $log_stmt->execute();
            
            $_SESSION['message'] = '维修状态更新成功!';
            
        } elseif ($action === 'add_part') {
            // 添加配件
            $part_name = $_POST['part_name'];
            $part_model = $_POST['part_model'] ?? '';
            $quantity = $_POST['quantity'];
            $unit_price = $_POST['unit_price'];
            $subtotal = $quantity * $unit_price;
            $supplier = $_POST['supplier'] ?? '';
            $supplier_cost = $_POST['supplier_cost'] ?? 0;
            $part_notes = $_POST['part_notes'] ?? '';
            
            $sql = "INSERT INTO repair_parts 
                    (repair_id, part_name, part_model, quantity, unit_price, subtotal, supplier, supplier_cost, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issiddsds",
                $repair_id, $part_name, $part_model, $quantity, $unit_price, 
                $subtotal, $supplier, $supplier_cost, $part_notes
            );
            $stmt->execute();
            
            // 更新总费用
            $update_sql = "UPDATE repair_orders SET 
                          parts_fee = (SELECT IFNULL(SUM(subtotal), 0) FROM repair_parts WHERE repair_id = ?),
                          total_fee = service_fee + (SELECT IFNULL(SUM(subtotal), 0) FROM repair_parts WHERE repair_id = ?) + other_fee
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iii", $repair_id, $repair_id, $repair_id);
            $update_stmt->execute();
            
            // 记录日志
            $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                        VALUES (?, 'updated', '添加配件: {$part_name}', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("is", $repair_id, $nickname);
            $log_stmt->execute();
            
            $_SESSION['message'] = '配件添加成功!';
            
        } elseif ($action === 'update_fees') {
            // 更新费用
            $service_fee = $_POST['service_fee'];
            $other_fee = $_POST['other_fee'] ?? 0;
            
            $sql = "UPDATE repair_orders SET 
                    service_fee = ?, other_fee = ?,
                    total_fee = ? + (SELECT IFNULL(SUM(subtotal), 0) FROM repair_parts WHERE repair_id = ?) + ?
                    WHERE id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dddidd", $service_fee, $other_fee, $service_fee, $repair_id, $other_fee, $repair_id);
            $stmt->execute();
            
            // 记录日志
            $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                        VALUES (?, 'updated', '更新费用信息', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("is", $repair_id, $nickname);
            $log_stmt->execute();
            
            $_SESSION['message'] = '费用更新成功!';
        }
        
        $conn->commit();
        header("Location: repair_edit.php?id=$repair_id");
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = '操作失败:' . $e->getMessage();
    }
}

// 获取维修单信息
$repair = $conn->query("
    SELECT ro.*, c.company_name 
    FROM repair_orders ro
    LEFT JOIN customers c ON ro.customer_id = c.id
    WHERE ro.id = $repair_id
")->fetch_assoc();

if (!$repair) {
    $_SESSION['message'] = '维修单不存在!';
    header('Location: repair.php');
    exit;
}

// 获取配件列表
$parts = $conn->query("SELECT * FROM repair_parts WHERE repair_id = $repair_id ORDER BY created_at DESC");

// 获取维修日志
$logs = $conn->query("SELECT * FROM repair_logs WHERE repair_id = $repair_id ORDER BY created_at DESC");

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑维修单 - 维修管理系统</title>
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

        .navbar-actions {
            display: flex;
            gap: 12px;
        }

        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1400px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            margin-bottom: 24px;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 8px;
        }

        .order-no {
            font-size: 14px;
            color: #718096;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            margin-left: 12px;
        }

        .status-badge.pending { background: #fef3c7; color: #92400e; }
        .status-badge.repairing { background: #dbeafe; color: #1e3a8a; }
        .status-badge.testing { background: #e0e7ff; color: #3730a3; }
        .status-badge.completed { background: #d1fae5; color: #065f46; }
        .status-badge.delivered { background: #d1fae5; color: #065f46; }
        .status-badge.cancelled { background: #fee2e2; color: #991b1b; }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .required {
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            min-height: 80px;
            resize: vertical;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
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

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 12px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .success-message {
            background: #d1fae5;
            color: #065f46;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }

        .timeline-item:last-child {
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #667eea;
        }

        .timeline-item::after {
            content: '';
            position: absolute;
            left: -19px;
            top: 10px;
            width: 2px;
            height: calc(100% - 10px);
            background: #e2e8f0;
        }

        .timeline-item:last-child::after {
            display: none;
        }

        .timeline-time {
            font-size: 12px;
            color: #a0aec0;
            margin-bottom: 4px;
        }

        .timeline-content {
            font-size: 14px;
            color: #4a5568;
        }

        .timeline-operator {
            font-size: 12px;
            color: #718096;
            margin-top: 4px;
        }

        .info-row {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f7fafc;
        }

        .info-label {
            width: 120px;
            font-size: 14px;
            color: #718096;
            flex-shrink: 0;
        }

        .info-value {
            font-size: 14px;
            color: #2d3748;
            flex: 1;
        }

        .fee-summary {
            background: #f7fafc;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .fee-row.total {
            border-top: 2px solid #e2e8f0;
            margin-top: 8px;
            padding-top: 12px;
            font-weight: 600;
            font-size: 16px;
            color: #667eea;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="navbar">
        <a href="repair.php" class="navbar-brand">
            <span>🔧</span>
            <span>编辑维修单</span>
        </a>
        <div class="navbar-actions">
            <button class="btn btn-secondary" onclick="window.location.href='repair_view.php?id=<?php echo $repair_id; ?>'">
                👁️ 查看详情
            </button>
            <button class="btn btn-secondary" onclick="window.location.href='repair.php'">
                ← 返回列表
            </button>
        </div>
    </nav>

    <!-- 主内容区 -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                维修单 #<?php echo htmlspecialchars($repair['order_no']); ?>
                <?php
                $status_map = [
                    'pending' => '待处理',
                    'repairing' => '维修中',
                    'testing' => '测试中',
                    'completed' => '已完成',
                    'delivered' => '已交付',
                    'cancelled' => '已取消'
                ];
                ?>
                <span class="status-badge <?php echo $repair['status']; ?>">
                    <?php echo $status_map[$repair['status']] ?? $repair['status']; ?>
                </span>
            </h1>
        </div>

        <!-- 成功消息 -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="success-message">
                <span>✓</span>
                <span><?php echo $_SESSION['message']; unset($_SESSION['message']); ?></span>
            </div>
        <?php endif; ?>

        <!-- 错误提示 -->
        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="content-grid">
            <!-- 左侧:编辑表单 -->
            <div>
                <!-- 基本信息 -->
                <div class="form-card">
                    <h3 class="card-title">基本信息</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_info">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">客户姓名 <span class="required">*</span></label>
                                <input type="text" name="customer_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['customer_name']); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">联系电话 <span class="required">*</span></label>
                                <input type="text" name="contact_phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['contact_phone']); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">联系地址</label>
                            <input type="text" name="contact_address" class="form-input" 
                                   value="<?php echo htmlspecialchars($repair['contact_address'] ?? ''); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">设备类型</label>
                                <input type="text" name="device_type" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['device_type'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">品牌</label>
                                <input type="text" name="device_brand" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['device_brand'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">型号</label>
                                <input type="text" name="device_model" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['device_model'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">序列号</label>
                                <input type="text" name="device_sn" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['device_sn'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">故障描述 <span class="required">*</span></label>
                            <textarea name="fault_description" class="form-textarea" required><?php echo htmlspecialchars($repair['fault_description']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">外观检查</label>
                            <textarea name="appearance_check" class="form-textarea"><?php echo htmlspecialchars($repair['appearance_check'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">附带配件</label>
                            <input type="text" name="accessories" class="form-input" 
                                   value="<?php echo htmlspecialchars($repair['accessories'] ?? ''); ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">预计完成日期</label>
                                <input type="date" name="expected_finish_date" class="form-input" 
                                       value="<?php echo $repair['expected_finish_date'] ?? ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">维修技师</label>
                                <input type="text" name="technician" class="form-input" 
                                       value="<?php echo htmlspecialchars($repair['technician'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">备注</label>
                            <textarea name="notes" class="form-textarea"><?php echo htmlspecialchars($repair['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">💾 保存基本信息</button>
                        </div>
                    </form>
                </div>

                <!-- 维修状态 -->
                <div class="form-card">
                    <h3 class="card-title">维修状态</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="form-group">
                            <label class="form-label">当前状态 <span class="required">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="pending" <?php echo $repair['status'] == 'pending' ? 'selected' : ''; ?>>待处理</option>
                                <option value="repairing" <?php echo $repair['status'] == 'repairing' ? 'selected' : ''; ?>>维修中</option>
                                <option value="testing" <?php echo $repair['status'] == 'testing' ? 'selected' : ''; ?>>测试中</option>
                                <option value="completed" <?php echo $repair['status'] == 'completed' ? 'selected' : ''; ?>>已完成</option>
                                <option value="delivered" <?php echo $repair['status'] == 'delivered' ? 'selected' : ''; ?>>已交付</option>
                                <option value="cancelled" <?php echo $repair['status'] == 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">维修结果</label>
                            <textarea name="repair_result" class="form-textarea" 
                                      placeholder="描述维修过程和结果..."><?php echo htmlspecialchars($repair['repair_result'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-success">✓ 更新状态</button>
                        </div>
                    </form>
                </div>

                <!-- 维修配件 -->
                <div class="form-card">
                    <h3 class="card-title">维修配件</h3>
                    
                    <?php if ($parts->num_rows > 0): ?>
                        <table style="margin-bottom: 20px;">
                            <thead>
                                <tr>
                                    <th>配件名称</th>
                                    <th>型号</th>
                                    <th>数量</th>
                                    <th>单价</th>
                                    <th>小计</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($part = $parts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($part['part_name']); ?></td>
                                        <td><?php echo htmlspecialchars($part['part_model'] ?? '-'); ?></td>
                                        <td><?php echo $part['quantity']; ?></td>
                                        <td>¥<?php echo number_format($part['unit_price'], 2); ?></td>
                                        <td><strong>¥<?php echo number_format($part['subtotal'], 2); ?></strong></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_part">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">配件名称 <span class="required">*</span></label>
                                <input type="text" name="part_name" class="form-input" 
                                       placeholder="如:主板、内存条" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">型号</label>
                                <input type="text" name="part_model" class="form-input">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">数量 <span class="required">*</span></label>
                                <input type="number" name="quantity" class="form-input" 
                                       value="1" min="1" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">单价 <span class="required">*</span></label>
                                <input type="number" name="unit_price" class="form-input" 
                                       step="0.01" min="0" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">供应商</label>
                                <input type="text" name="supplier" class="form-input">
                            </div>

                            <div class="form-group">
                                <label class="form-label">采购成本</label>
                                <input type="number" name="supplier_cost" class="form-input" 
                                       step="0.01" min="0" value="0">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">备注</label>
                            <input type="text" name="part_notes" class="form-input">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">+ 添加配件</button>
                        </div>
                    </form>
                </div>

                <!-- 费用管理 -->
                <div class="form-card">
                    <h3 class="card-title">费用管理</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="update_fees">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">服务费用</label>
                                <input type="number" name="service_fee" class="form-input" 
                                       step="0.01" min="0" 
                                       value="<?php echo $repair['service_fee']; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">其他费用</label>
                                <input type="number" name="other_fee" class="form-input" 
                                       step="0.01" min="0" 
                                       value="<?php echo $repair['other_fee']; ?>">
                            </div>
                        </div>

                        <div class="fee-summary">
                            <div class="fee-row">
                                <span>服务费用:</span>
                                <span>¥<?php echo number_format($repair['service_fee'], 2); ?></span>
                            </div>
                            <div class="fee-row">
                                <span>配件费用:</span>
                                <span>¥<?php echo number_format($repair['parts_fee'], 2); ?></span>
                            </div>
                            <div class="fee-row">
                                <span>其他费用:</span>
                                <span>¥<?php echo number_format($repair['other_fee'], 2); ?></span>
                            </div>
                            <div class="fee-row total">
                                <span>总计:</span>
                                <span>¥<?php echo number_format($repair['total_fee'], 2); ?></span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">💰 更新费用</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- 右侧:信息面板 -->
            <div>
                <!-- 快速信息 -->
                <div class="form-card">
                    <h3 class="card-title">快速信息</h3>
                    
                    <div class="info-row">
                        <div class="info-label">维修类型:</div>
                        <div class="info-value">
                            <?php echo $repair['repair_type'] == 'onsite' ? '上门服务' : '带回维修'; ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">接收日期:</div>
                        <div class="info-value">
                            <?php echo date('Y-m-d H:i', strtotime($repair['receive_date'])); ?>
                        </div>
                    </div>

                    <?php if ($repair['expected_finish_date']): ?>
                        <div class="info-row">
                            <div class="info-label">预计完成:</div>
                            <div class="info-value">
                                <?php echo date('Y-m-d', strtotime($repair['expected_finish_date'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($repair['actual_finish_date']): ?>
                        <div class="info-row">
                            <div class="info-label">实际完成:</div>
                            <div class="info-value">
                                <?php echo date('Y-m-d H:i', strtotime($repair['actual_finish_date'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="info-row">
                        <div class="info-label">创建人:</div>
                        <div class="info-value"><?php echo htmlspecialchars($nickname); ?></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">支付状态:</div>
                        <div class="info-value">
                            <?php
                            $payment_status_map = [
                                'unpaid' => '未付款',
                                'partial' => '部分付款',
                                'paid' => '已付款'
                            ];
                            echo $payment_status_map[$repair['payment_status']] ?? $repair['payment_status'];
                            ?>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">已付金额:</div>
                        <div class="info-value">
                            <strong style="color: #10b981;">¥<?php echo number_format($repair['paid_amount'], 2); ?></strong>
                        </div>
                    </div>
                </div>

                <!-- 维修日志 -->
                <div class="form-card">
                    <h3 class="card-title">维修日志</h3>
                    
                    <div class="timeline">
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time">
                                        <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                                    </div>
                                    <div class="timeline-content">
                                        <?php echo htmlspecialchars($log['description']); ?>
                                    </div>
                                    <div class="timeline-operator">
                                        操作人: <?php echo htmlspecialchars($log['operator']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="color: #a0aec0; text-align: center;">暂无日志记录</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>