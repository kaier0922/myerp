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
require_once __DIR__ . '/log_helper.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'repair', 'update', '修改维修工单');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$conn = getDBConnection();

// AJAX：搜索在库序列号
if (isset($_GET['action']) && $_GET['action'] === 'search_sn') {
    header('Content-Type: application/json; charset=utf-8');
    if (!isset($_SESSION['user_id'])) { echo '[]'; exit; }
    $kw = '%' . trim($_GET['kw'] ?? '') . '%';
    $stmt_sn = $conn->prepare("
        SELECT ps.sn_code, ps.product_id, p.name as product_name, ps.purchase_price, 
               DATE_FORMAT(ps.in_date,'%Y-%m-%d') as in_date,
               DATE_FORMAT(ps.warranty_until,'%Y-%m-%d') as warranty_until
        FROM product_serials ps
        JOIN products p ON ps.product_id=p.id
        WHERE ps.status='in_stock' AND (p.name LIKE ? OR ps.sn_code LIKE ?)
        ORDER BY ps.in_date DESC LIMIT 30
    ");
    $stmt_sn->bind_param('ss', $kw, $kw);
    $stmt_sn->execute();
    echo json_encode($stmt_sn->get_result()->fetch_all(MYSQLI_ASSOC), JSON_UNESCAPED_UNICODE);
    $conn->close(); exit;
}

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
                    WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
            
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
                    WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
            
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
            
            // 状态变为completed时自动生成应收账款
            if (in_array($new_status, ['completed', 'delivered'])) {
                // 先查维修单费用和客户信息
                $ro = $conn->prepare("SELECT * FROM repair_orders WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
                $ro->bind_param('i', $repair_id);
                $ro->execute();
                $repair_data = $ro->get_result()->fetch_assoc();
                $ro->close();

                $total_fee = floatval($repair_data['total_fee'] ?? 0);
                $customer_id = intval($repair_data['customer_id'] ?? 0);

                if ($total_fee > 0) {
                    // 查重：避免重复生成
                    $bill_no = 'AR-RPR-' . date('Ymd') . '-' . str_pad($repair_id, 4, '0', STR_PAD_LEFT);
                    $dup_chk = $conn->prepare("SELECT id FROM accounts_receivable WHERE bill_no=?");
                    $dup_chk->bind_param('s', $bill_no);
                    $dup_chk->execute();
                    $dup_exists = $dup_chk->get_result()->fetch_assoc();
                    $dup_chk->close();

                    if (!$dup_exists) {
                        $bill_date = date('Y-m-d');
                        $due_date  = date('Y-m-d', strtotime('+30 days'));
                        $notes     = '维修单 ' . $repair_data['order_no'] . ' 完成自动生成';
                        $ar = $conn->prepare("INSERT INTO accounts_receivable
                            (customer_id, related_type, related_id, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status, notes)
                            VALUES (?, 'repair', ?, ?, ?, ?, 0, ?, ?, '未收款', ?)");
                        $ar->bind_param('sissddss',
                            $customer_id, $repair_id, $bill_no, $bill_date,
                            $total_fee, $total_fee, $due_date, $notes);
                        $ar->execute();
                        $ar->close();
                    }
                }
            }

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
            
            $serial_no     = trim($_POST['serial_no'] ?? '');
            $sale_date     = !empty($_POST['sale_date']) ? $_POST['sale_date'] : null;
            $warranty_until= !empty($_POST['warranty_until']) ? $_POST['warranty_until'] : null;

            $sql = "INSERT INTO repair_parts
                    (repair_id, part_name, part_model, serial_no, sale_date, warranty_until,
                     quantity, unit_price, subtotal, supplier, supplier_cost, notes, user_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isssssiddsdsi",
                $repair_id, $part_name, $part_model, $serial_no, $sale_date, $warranty_until,
                $quantity, $unit_price, $subtotal, $supplier, $supplier_cost, $part_notes, $user_id
            );
            $stmt->execute();

            // 如果填了序列号，把 product_serials 状态改为 sold 并同步扣减库存
            if ($serial_no) {
                $ro_tmp = $conn->prepare("SELECT customer_id, order_no FROM repair_orders WHERE id=?");
                $ro_tmp->bind_param('i', $repair_id); $ro_tmp->execute();
                $ro_row = $ro_tmp->get_result()->fetch_assoc(); $ro_tmp->close();

                // 更新序列号状态
                $upd_sn = $conn->prepare("UPDATE product_serials SET 
                    status='sold', sale_date=?, warranty_until=?,
                    customer_id=?, delivery_no=?, out_date=NOW()
                    WHERE sn_code=? AND status='in_stock'");
                $upd_sn->bind_param('ssiss',
                    $sale_date, $warranty_until,
                    $ro_row['customer_id'], $ro_row['order_no'], $serial_no);
                $upd_sn->execute();
                $affected = $upd_sn->affected_rows;
                $upd_sn->close();

                // 扣减库存（只有序列号状态成功变更才扣，防止重复扣）
                if ($affected > 0) {
                    // 找到该序列号对应的产品ID
                    $pid_stmt = $conn->prepare("SELECT product_id FROM product_serials WHERE sn_code=?");
                    $pid_stmt->bind_param('s', $serial_no); $pid_stmt->execute();
                    $pid_row = $pid_stmt->get_result()->fetch_assoc(); $pid_stmt->close();
                    if ($pid_row) {
                        $stk = $conn->prepare("UPDATE products SET stock_quantity=stock_quantity-1 WHERE id=? AND stock_quantity>0");
                        $stk->bind_param('i', $pid_row['product_id']); $stk->execute(); $stk->close();
                    }
                }
            } else {
                // 无序列号：按part_product_id精确扣减库存
                $part_product_id = intval($_POST['part_product_id'] ?? 0);
                if ($part_product_id > 0) {
                    $stk2 = $conn->prepare("UPDATE products SET stock_quantity=stock_quantity-? WHERE id=? AND stock_quantity>=?");
                    $stk2->bind_param('iii', $quantity, $part_product_id, $quantity);
                    $stk2->execute(); $stk2->close();
                }
                // 如果没有选产品ID则不自动扣减，避免数据错误
            }
            
            // 更新总费用
            $update_sql = "UPDATE repair_orders SET 
                          parts_fee = (SELECT IFNULL(SUM(subtotal), 0) FROM repair_parts WHERE repair_id = ?),
                          total_fee = service_fee + (SELECT IFNULL(SUM(subtotal), 0) FROM repair_parts WHERE repair_id = ?) + other_fee
                          WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("iii", $repair_id, $repair_id, $repair_id);
            $update_stmt->execute();
            
            // 记录日志
            $log_desc = '添加配件: ' . $part_name;
            $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                        VALUES (?, 'updated', ?, ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("iss", $repair_id, $log_desc, $nickname);
            $log_stmt->execute();
            
            $_SESSION['message'] = '配件添加成功!';
            
        } elseif ($action === 'update_fees') {
            // 更新费用
            $service_fee = $_POST['service_fee'];
            $other_fee = $_POST['other_fee'] ?? 0;
            
            $sql = "UPDATE repair_orders SET 
                    service_fee = ?, other_fee = ?,
                    total_fee = ? + (SELECT IFNULL(SUM(subtotal), 0) FROM repair_parts WHERE repair_id = ?) + ?
                    WHERE id = ? AND user_id = " . intval($_SESSION['user_id']);
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dddidd", $service_fee, $other_fee, $service_fee, $repair_id, $other_fee, $repair_id);
            $stmt->execute();
            
            // 记录日志
            $log_sql = "INSERT INTO repair_logs (repair_id, status, description, operator, created_at) 
                        VALUES (?, 'updated', '更新费用信息', ?, NOW())";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("is", $repair_id, $nickname);
            $log_stmt->execute();
            
            // 费用更新后，如果状态已是completed/delivered，补生成应收
            $ro2 = $conn->prepare("SELECT * FROM repair_orders WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
            $ro2->bind_param('i', $repair_id); $ro2->execute();
            $repair_now = $ro2->get_result()->fetch_assoc(); $ro2->close();

            if (in_array($repair_now['status'], ['completed','delivered'])) {
                $total_fee2   = floatval($repair_now['total_fee'] ?? 0);
                $customer_id2 = intval($repair_now['customer_id'] ?? 0);
                if ($total_fee2 > 0) {
                    $bill_no2 = 'AR-RPR-' . date('Ymd') . '-' . str_pad($repair_id, 4, '0', STR_PAD_LEFT);
                    $dup2 = $conn->prepare("SELECT id FROM accounts_receivable WHERE bill_no=?");
                    $dup2->bind_param('s', $bill_no2); $dup2->execute();
                    $dup2_exists = $dup2->get_result()->fetch_assoc(); $dup2->close();
                    if (!$dup2_exists) {
                        $bill_date2 = date('Y-m-d');
                        $due_date2  = date('Y-m-d', strtotime('+30 days'));
                        $notes2     = '维修单 ' . $repair_now['order_no'] . ' 费用更新后自动生成';
                        $ar2 = $conn->prepare("INSERT INTO accounts_receivable
                            (customer_id, bill_no, bill_date, total_amount, paid_amount, outstanding_amount, due_date, status, notes)
                            VALUES (?, ?, ?, ?, 0, ?, ?, '未收款', ?)");
                        $ar2->bind_param('issddsss',
                            $customer_id2, $bill_no2, $bill_date2,
                            $total_fee2, $total_fee2, $due_date2, $notes2);
                        $ar2->execute(); $ar2->close();
                    } else {
                        // AR已存在则更新金额
                        $upd_ar = $conn->prepare("UPDATE accounts_receivable SET total_amount=?, outstanding_amount=? WHERE bill_no=? AND status='未收款'");
                        $upd_ar->bind_param('dds', $total_fee2, $total_fee2, $bill_no2);
                        $upd_ar->execute(); $upd_ar->close();
                    }
                }
            }

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
$stmt_r=$conn->prepare("SELECT ro.*, COALESCE(c.short_name, c.company_name) AS company_name FROM repair_orders ro LEFT JOIN customers c ON ro.customer_id=c.id WHERE ro.id=?");
$stmt_r->bind_param('i',$repair_id); $stmt_r->execute();
$repair=$stmt_r->get_result()->fetch_assoc(); $stmt_r->close();

if (!$repair) {
    $_SESSION['message'] = '维修单不存在!';
    header('Location: repair.php');
    exit;
}

// 获取配件列表
$stmt_pts=$conn->prepare("SELECT * FROM repair_parts WHERE repair_id=? ORDER BY created_at DESC"); $stmt_pts->bind_param("i",$repair_id); $stmt_pts->execute(); $parts=$stmt_pts->get_result(); $stmt_pts->close();

// 获取维修日志
$stmt_lg=$conn->prepare("SELECT * FROM repair_logs WHERE repair_id=? ORDER BY created_at DESC"); $stmt_lg->bind_param("i",$repair_id); $stmt_lg->execute(); $logs=$stmt_lg->get_result(); $stmt_lg->close();

$conn->close();
?>
<?php $page_title = '编辑维修单 - 维修管理系统'; include __DIR__ . '/head.php'; ?>
    <style>
    .brand-icon{width:36px;height:36px;background:linear-gradient(135deg,#1f6dad,#135286);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:17px;}
    .navbar-actions{display:flex;gap:8px;}
    .status-badge{display:inline-flex;align-items:center;padding:2px 10px;border-radius:10px;font-size:11px;font-weight:600;}
    .status-badge.pending{background:#fef3c7;color:#92400e;}.status-badge.repairing{background:#dbeafe;color:#1e3a8a;}
    .status-badge.testing{background:#e0e7ff;color:#3730a3;}.status-badge.completed,.status-badge.delivered{background:#d1fae5;color:#065f46;}
    .status-badge.cancelled{background:#fee2e2;color:#991b1b;}
    .content-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start;}
    .card-head{background:linear-gradient(135deg,#1f6dad,#135286);padding:9px 16px;color:white;font-size:13px;font-weight:600;}
    .card-body{padding:14px 16px;}
    .g2{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
    .g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;}
    .g4{display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;}
    .fg{margin-bottom:10px;}.fg:last-child{margin-bottom:0;}
    .fl{display:block;margin-bottom:3px;font-weight:500;color:#4a5568;font-size:12px;}
    .req{color:#e53e3e;}
    .fi,.fs,.ft{width:100%;padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:13px;transition:border-color .15s;background:white;}
    .fi:focus,.fs:focus,.ft:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 2px rgba(31,109,173,.1);}
    .ft{min-height:56px;resize:vertical;}
    .fa{display:flex;gap:8px;justify-content:flex-end;margin-top:10px;padding-top:10px;border-top:1px solid #f0f0f0;}
    table{width:100%;border-collapse:collapse;}
    th{padding:6px 8px;font-size:11px;font-weight:600;color:#718096;text-transform:uppercase;border-bottom:2px solid #e2e8f0;text-align:left;}
    td{padding:7px 8px;border-bottom:1px solid #f0f0f0;font-size:13px;}tr:last-child td{border-bottom:none;}
    .info-row{display:flex;padding:6px 0;border-bottom:1px solid #f7fafc;}
    .info-label{width:90px;font-size:12px;color:#718096;flex-shrink:0;}
    .info-value{font-size:13px;color:#2d3748;flex:1;}
    .fee-box{background:#f7fafc;border-radius:6px;padding:10px 12px;margin-top:8px;}
    .fee-row{display:flex;justify-content:space-between;padding:4px 0;font-size:13px;}
    .fee-row.total{border-top:2px solid #e2e8f0;margin-top:6px;padding-top:7px;font-weight:700;font-size:15px;color:#1f6dad;}
    .timeline{position:relative;padding-left:22px;}
    .timeline-item{position:relative;padding-bottom:12px;}
    .timeline-item:last-child{padding-bottom:0;}
    .timeline-item::before{content:'';position:absolute;left:-16px;top:3px;width:7px;height:7px;border-radius:50%;background:#1f6dad;}
    .timeline-item::after{content:'';position:absolute;left:-13px;top:10px;width:2px;height:calc(100% - 8px);background:#e2e8f0;}
    .timeline-item:last-child::after{display:none;}
    .timeline-time{font-size:11px;color:#a0aec0;margin-bottom:1px;}
    .timeline-content{font-size:13px;color:#4a5568;}
    .timeline-op{font-size:11px;color:#a0aec0;margin-top:1px;}
    .msg-ok{background:#d1fae5;color:#065f46;padding:8px 14px;border-radius:6px;margin-bottom:12px;font-size:13px;}
    .msg-err{background:#fed7d7;color:#c53030;padding:8px 14px;border-radius:6px;margin-bottom:12px;font-size:13px;}
    details>summary{padding:8px 14px;background:#f7fafc;cursor:pointer;font-size:13px;font-weight:500;color:#1f6dad;user-select:none;list-style:none;}
    details>summary::-webkit-details-marker{display:none;}
    details[open]>summary{border-bottom:1px solid #e2e8f0;}
    details>form{padding:12px 14px;}
    @media(max-width:1024px){.content-grid{grid-template-columns:1fr;}.g4{grid-template-columns:1fr 1fr;}}
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
<div class="page-header">
  <h1 class="page-title">🔧 编辑维修单</h1>
  <div style="display:flex;gap:10px;">
    <a href="repair.php" class="btn btn-secondary">← 返回列表</a>
    <a href="repair_view.php?id=<?=$repair_id?>" class="btn btn-secondary">👁️ 查看</a>
  </div>
</div>
<?php
$status_map=['pending'=>'待处理','repairing'=>'维修中','testing'=>'测试中',
             'completed'=>'已完成','delivered'=>'已交付','cancelled'=>'已取消'];
?>
  <div class="page-header">
    <h1 class="page-title">维修单 #<?=htmlspecialchars($repair['order_no'])?></h1>
    <span class="status-badge <?=$repair['status']?>"><?=$status_map[$repair['status']]??$repair['status']?></span>
  </div>

  <?php if(isset($_SESSION['message'])): ?>
    <div class="msg-ok">✅ <?=$_SESSION['message']; unset($_SESSION['message']);?></div>
  <?php endif; ?>
  <?php if(isset($error)): ?>
    <div class="msg-err">❌ <?=$error?></div>
  <?php endif; ?>

  <div class="content-grid">
    <!-- 左列 -->
    <div>

      <!-- 基本信息 & 设备 -->
      <div class="form-card">
        <div class="card-head">📋 基本信息 & 设备</div>
        <div class="card-body">
          <form method="POST">
            <input type="hidden" name="action" value="update_info">
            <div class="g3" style="margin-bottom:10px;">
              <div class="fg"><label class="fl">客户姓名 <span class="req">*</span></label>
                <input type="text" name="customer_name" class="fi" value="<?=htmlspecialchars($repair['customer_name'])?>" required></div>
              <div class="fg"><label class="fl">联系电话 <span class="req">*</span></label>
                <input type="text" name="contact_phone" class="fi" value="<?=htmlspecialchars($repair['contact_phone'])?>" required></div>
              <div class="fg"><label class="fl">联系地址</label>
                <input type="text" name="contact_address" class="fi" value="<?=htmlspecialchars($repair['contact_address']??'')?>"></div>
            </div>
            <div class="g4" style="margin-bottom:10px;">
              <div class="fg"><label class="fl">设备类型</label>
                <input type="text" name="device_type" class="fi" value="<?=htmlspecialchars($repair['device_type']??'')?>"></div>
              <div class="fg"><label class="fl">品牌</label>
                <input type="text" name="device_brand" class="fi" value="<?=htmlspecialchars($repair['device_brand']??'')?>"></div>
              <div class="fg"><label class="fl">型号</label>
                <input type="text" name="device_model" class="fi" value="<?=htmlspecialchars($repair['device_model']??'')?>"></div>
              <div class="fg"><label class="fl">序列号</label>
                <input type="text" name="device_sn" class="fi" value="<?=htmlspecialchars($repair['device_sn']??'')?>"></div>
            </div>
            <div class="fg"><label class="fl">故障描述 <span class="req">*</span></label>
              <textarea name="fault_description" class="ft" style="min-height:52px;" required><?=htmlspecialchars($repair['fault_description'])?></textarea></div>
            <div class="g3" style="margin-bottom:10px;">
              <div class="fg"><label class="fl">外观检查</label>
                <textarea name="appearance_check" class="ft" style="min-height:44px;"><?=htmlspecialchars($repair['appearance_check']??'')?></textarea></div>
              <div class="fg"><label class="fl">附带配件</label>
                <input type="text" name="accessories" class="fi" value="<?=htmlspecialchars($repair['accessories']??'')?>"></div>
              <div class="fg"><label class="fl">备注</label>
                <textarea name="notes" class="ft" style="min-height:44px;"><?=htmlspecialchars($repair['notes']??'')?></textarea></div>
            </div>
            <div class="g2">
              <div class="fg"><label class="fl">预计完成日期</label>
                <input type="date" name="expected_finish_date" class="fi" value="<?=$repair['expected_finish_date']??''?>"></div>
              <div class="fg"><label class="fl">维修技师</label>
                <input type="text" name="technician" class="fi" value="<?=htmlspecialchars($repair['technician']??'')?>"></div>
            </div>
            <div class="fa"><button type="submit" class="btn btn-primary">💾 保存基本信息</button></div>
          </form>
        </div>
      </div>

      <!-- 维修状态 & 费用（并排） -->
      <div class="form-card">
        <div class="card-head">⚙️ 维修状态 & 费用</div>
        <div class="card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <form method="POST">
              <input type="hidden" name="action" value="update_status">
              <div class="fg"><label class="fl">当前状态 <span class="req">*</span></label>
                <select name="status" class="fs" required>
                  <option value="pending"   <?=$repair['status']=='pending'  ?'selected':''?>>待处理</option>
                  <option value="repairing" <?=$repair['status']=='repairing'?'selected':''?>>维修中</option>
                  <option value="testing"   <?=$repair['status']=='testing'  ?'selected':''?>>测试中</option>
                  <option value="completed" <?=$repair['status']=='completed'?'selected':''?>>已完成</option>
                  <option value="delivered" <?=$repair['status']=='delivered'?'selected':''?>>已交付</option>
                  <option value="cancelled" <?=$repair['status']=='cancelled'?'selected':''?>>已取消</option>
                </select>
              </div>
              <div class="fg"><label class="fl">维修结果</label>
                <textarea name="repair_result" class="ft" style="min-height:68px;" placeholder="描述维修过程和结果..."><?=htmlspecialchars($repair['repair_result']??'')?></textarea></div>
              <div class="fa"><button type="submit" class="btn btn-primary">✓ 更新状态</button></div>
            </form>
            <form method="POST">
              <input type="hidden" name="action" value="update_fees">
              <div class="g2" style="margin-bottom:6px;">
                <div class="fg"><label class="fl">服务费用 (¥)</label>
                  <input type="number" name="service_fee" class="fi" step="any" min="0" value="<?=$repair['service_fee']?>"></div>
                <div class="fg"><label class="fl">其他费用 (¥)</label>
                  <input type="number" name="other_fee" class="fi" step="any" min="0" value="<?=$repair['other_fee']?>"></div>
              </div>
              <div class="fee-box">
                <div class="fee-row"><span>服务费:</span><span>¥<?=number_format($repair['service_fee'],2)?></span></div>
                <div class="fee-row"><span>配件费:</span><span>¥<?=number_format($repair['parts_fee'],2)?></span></div>
                <div class="fee-row"><span>其他费:</span><span>¥<?=number_format($repair['other_fee'],2)?></span></div>
                <div class="fee-row total"><span>合计:</span><span>¥<?=number_format($repair['total_fee'],2)?></span></div>
              </div>
              <div class="fa"><button type="submit" class="btn btn-primary">💰 更新费用</button></div>
            </form>
          </div>
        </div>
      </div>

      <!-- 维修配件 -->
      <div class="form-card">
        <div class="card-head">🔩 维修配件</div>
        <div class="card-body">
          <?php if($parts->num_rows>0): ?>
          <table style="margin-bottom:12px;">
            <thead><tr>
              <th>配件名称</th><th>型号</th><th>序列号</th>
              <th>数量</th><th>单价</th><th>小计</th><th>保修截止</th>
            </tr></thead>
            <tbody>
            <?php while($part=$parts->fetch_assoc()): ?>
            <tr>
              <td><?=htmlspecialchars($part['part_name'])?></td>
              <td><?=htmlspecialchars($part['part_model']??'-')?></td>
              <td><code style="font-size:11px;color:#1f6dad;"><?=htmlspecialchars($part['serial_no']??'-')?></code></td>
              <td><?=$part['quantity']?></td>
              <td>¥<?=number_format($part['unit_price'],2)?></td>
              <td><strong>¥<?=number_format($part['subtotal'],2)?></strong></td>
              <td style="font-size:12px;"><?=$part['warranty_until']??'-'?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
          <?php endif; ?>
          <details>
            <summary>＋ 添加配件</summary>
            <form method="POST">
              <input type="hidden" name="action" value="add_part">
              <div class="g2" style="margin-bottom:8px;">
                <div class="fg"><label class="fl">配件名称 <span class="req">*</span></label>
                  <input type="text" name="part_name" id="part_name_input" class="fi" placeholder="如:主板、内存条" required>
                  <input type="hidden" name="part_product_id" id="part_product_id" value=""></div>
                <div class="fg"><label class="fl">型号</label>
                  <input type="text" name="part_model" class="fi"></div>
              </div>
              <div class="g4" style="margin-bottom:8px;">
                <div class="fg"><label class="fl">数量 <span class="req">*</span></label>
                  <input type="number" name="quantity" class="fi" value="1" min="1" required></div>
                <div class="fg"><label class="fl">单价 <span class="req">*</span></label>
                  <input type="number" name="unit_price" class="fi" step="any" min="0" required></div>
                <div class="fg"><label class="fl">采购成本</label>
                  <input type="number" name="supplier_cost" class="fi" step="any" value="0"></div>
                <div class="fg"><label class="fl">供应商</label>
                  <input type="text" name="supplier" class="fi"></div>
              </div>
              <div class="g3" style="margin-bottom:8px;">
                <div class="fg"><label class="fl">序列号</label>
                  <div style="display:flex;gap:5px;">
                    <input type="text" name="serial_no" id="serial_no_input" class="fi" placeholder="手动输入或从库存选" style="flex:1;">
                    <button type="button" onclick="openSnPicker()"
                      style="padding:5px 8px;background:#eef2ff;color:#1f6dad;border:1px solid #c7d2fe;border-radius:5px;font-size:12px;cursor:pointer;white-space:nowrap;">📦</button>
                  </div>
                  <div id="sn_product_hint" style="font-size:11px;color:#1f6dad;margin-top:2px;display:none;"></div>
                </div>
                <div class="fg"><label class="fl">销售日期</label>
                  <input type="date" name="sale_date" id="sale_date_input" class="fi" value="<?=date('Y-m-d')?>"></div>
                <div class="fg"><label class="fl">保修截止 / 保修期</label>
                  <div style="display:flex;gap:5px;">
                    <input type="date" name="warranty_until" id="warranty_until_input" class="fi" style="flex:1;">
                    <select onchange="calcWarranty(this.value)" class="fi" style="width:72px;cursor:pointer;">
                      <option value="">手动</option>
                      <option value="1">1月</option><option value="3">3月</option>
                      <option value="6">6月</option><option value="12">1年</option>
                      <option value="24">2年</option><option value="36">3年</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="fg"><label class="fl">备注</label>
                <input type="text" name="part_notes" class="fi"></div>
              <div class="fa"><button type="submit" class="btn btn-primary btn-sm">＋ 添加配件</button></div>
            </form>
          </details>
        </div>
      </div>
    </div>

    <!-- 右列 -->
    <div>
      <div class="form-card">
        <div class="card-head">ℹ️ 单据信息</div>
        <div class="card-body" style="padding:10px 14px;">
          <div class="info-row"><div class="info-label">维修类型</div>
            <div class="info-value"><?=$repair['repair_type']=='onsite'?'上门服务':'带回维修'?></div></div>
          <div class="info-row"><div class="info-label">接收日期</div>
            <div class="info-value"><?=date('Y-m-d H:i',strtotime($repair['receive_date']))?></div></div>
          <?php if($repair['expected_finish_date']): ?>
          <div class="info-row"><div class="info-label">预计完成</div>
            <div class="info-value"><?=date('Y-m-d',strtotime($repair['expected_finish_date']))?></div></div>
          <?php endif; ?>
          <?php if($repair['actual_finish_date']): ?>
          <div class="info-row"><div class="info-label">实际完成</div>
            <div class="info-value"><?=date('Y-m-d H:i',strtotime($repair['actual_finish_date']))?></div></div>
          <?php endif; ?>
          <div class="info-row"><div class="info-label">创建人</div>
            <div class="info-value"><?=htmlspecialchars($nickname)?></div></div>
          <div class="info-row"><div class="info-label">支付状态</div>
            <div class="info-value"><?php $pm=['unpaid'=>'未付款','partial'=>'部分付款','paid'=>'已付款']; echo $pm[$repair['payment_status']]??$repair['payment_status'];?></div></div>
          <div class="info-row" style="border-bottom:none;"><div class="info-label">已付金额</div>
            <div class="info-value"><strong style="color:#1f6dad;">¥<?=number_format($repair['paid_amount'],2)?></strong></div></div>
        </div>
      </div>
      <div class="form-card">
        <div class="card-head">📝 维修日志</div>
        <div class="card-body">
          <div class="timeline">
            <?php if($logs->num_rows>0): ?>
              <?php while($log=$logs->fetch_assoc()): ?>
              <div class="timeline-item">
                <div class="timeline-time"><?=date('Y-m-d H:i',strtotime($log['created_at']))?></div>
                <div class="timeline-content"><?=htmlspecialchars($log['description'])?></div>
                <div class="timeline-op">操作人: <?=htmlspecialchars($log['operator'])?></div>
              </div>
              <?php endwhile; ?>
            <?php else: ?>
              <p style="color:#a0aec0;text-align:center;font-size:13px;">暂无日志</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<div id="snPickerModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:500;display:none;">
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;border-radius:14px;width:560px;max-width:95vw;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:15px;font-weight:600;color:white;">📦 从库存选择序列号</span>
            <button onclick="closeSnPicker()" style="background:rgba(255,255,255,0.2);border:none;color:white;width:28px;height:28px;border-radius:6px;font-size:16px;cursor:pointer;">×</button>
        </div>
        <div style="padding:12px 16px;border-bottom:1px solid #e2e8f0;">
            <input type="text" id="snSearchInput" placeholder="搜索产品名称或SN..." 
                   style="width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;"
                   oninput="searchSn(this.value)">
        </div>
        <div id="snList" style="overflow-y:auto;flex:1;padding:8px 0;"></div>
    </div>
</div>

<script>
function openSnPicker() {
    document.getElementById('snPickerModal').style.display = 'block';
    searchSn('');
}
function closeSnPicker() {
    document.getElementById('snPickerModal').style.display = 'none';
}
function searchSn(kw) {
    fetch('repair_edit.php?action=search_sn&kw=' + encodeURIComponent(kw) + '&id=<?=$repair_id?>')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('snList');
            if (!data.length) {
                list.innerHTML = '<div style="text-align:center;padding:32px;color:#a0aec0;">暂无在库序列号</div>';
                return;
            }
            list.innerHTML = data.map(item => `
                <div onclick=\"selectSn('${item.sn_code}','${item.product_name}','${item.warranty_until??''}','${item.product_id}')\" 
                     style="padding:10px 16px;cursor:pointer;border-bottom:1px solid #f0f0f0;transition:background .15s;"
                     onmouseover="this.style.background='#f7fafc'" onmouseout="this.style.background='white'">
                    <div style="font-weight:600;font-size:13px;color:#1a202c;">${item.sn_code}</div>
                    <div style="font-size:12px;color:#718096;margin-top:2px;">${item.product_name} · 进价：¥${item.purchase_price??'-'} · 入库：${item.in_date??'-'}</div>
                </div>
            `).join('');
        });
}
function selectSn(sn, productName, warrantyUntil, productId) {
    document.getElementById('serial_no_input').value = sn;
    document.getElementById('part_name_input').value = productName;
    document.getElementById('part_product_id').value = productId || '';
    const hint = document.getElementById('sn_product_hint');
    hint.style.display = 'block';
    hint.textContent = '✓ 已选：' + productName;
    if (warrantyUntil) document.getElementById('warranty_until_input').value = warrantyUntil;
    closeSnPicker();
}
function calcWarranty(months) {
    if (!months) return;
    const saleDate = document.getElementById('sale_date_input').value || new Date().toISOString().slice(0,10);
    const d = new Date(saleDate);
    d.setMonth(d.getMonth() + parseInt(months));
    document.getElementById('warranty_until_input').value = d.toISOString().slice(0,10);
}
</script>
</body>
</html>