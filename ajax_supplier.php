<?php
/**
 * =====================================================
 * 文件名：ajax_supplier.php
 * 功能：AJAX处理供应商相关操作
 * 描述：快速新建供应商，返回JSON数据
 * =====================================================
 */

session_start();
require_once 'config.php';

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}

// 新建供应商
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_supplier') {
    
    // 获取表单数据
    $company_name = trim($_POST['company_name']);
    $contact_person = !empty($_POST['contact_person']) ? trim($_POST['contact_person']) : null;
    $contact_phone = !empty($_POST['contact_phone']) ? trim($_POST['contact_phone']) : null;
    $address = !empty($_POST['address']) ? trim($_POST['address']) : null;
    $tax_number = !empty($_POST['tax_number']) ? trim($_POST['tax_number']) : null;
    $payment_terms = !empty($_POST['payment_terms']) ? intval($_POST['payment_terms']) : 30;
    $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
    
    // 验证必填字段
    if (empty($company_name)) {
        echo json_encode(['success' => false, 'error' => '公司名称不能为空']);
        exit;
    }
    
    // 检查公司名称是否已存在
    $check_sql = "SELECT id FROM suppliers WHERE company_name = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $company_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => '该供应商名称已存在']);
        $check_stmt->close();
        $conn->close();
        exit;
    }
    $check_stmt->close();
    
    // 生成供应商编号
    $today = date('Ymd');
    $code_prefix = "S{$today}";
    
    // 查询当天最后一个编号
    $last_code_sql = "SELECT supplier_code FROM suppliers 
                      WHERE supplier_code LIKE '{$code_prefix}%' 
                      ORDER BY id DESC LIMIT 1";
    $last_code_result = $conn->query($last_code_sql);
    
    if ($last_code_result && $last_code_result->num_rows > 0) {
        $last_row = $last_code_result->fetch_assoc();
        $last_num = intval(substr($last_row['supplier_code'], -4));
        $new_num = str_pad($last_num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $new_num = '0001';
    }
    
    $supplier_code = $code_prefix . $new_num;
    
    // 插入新供应商
    $insert_sql = "INSERT INTO suppliers 
                   (supplier_code, company_name, contact_person, contact_phone, 
                    address, tax_number, payment_terms, description, is_active) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("ssssssis", 
        $supplier_code, 
        $company_name, 
        $contact_person, 
        $contact_phone, 
        $address, 
        $tax_number, 
        $payment_terms, 
        $description
    );
    
    if ($insert_stmt->execute()) {
        $new_id = $conn->insert_id;
        
        // 返回新建的供应商信息
        $supplier_data = [
            'id' => $new_id,
            'supplier_code' => $supplier_code,
            'company_name' => $company_name,
            'contact_person' => $contact_person,
            'contact_phone' => $contact_phone,
            'payment_terms' => $payment_terms
        ];
        
        echo json_encode([
            'success' => true,
            'message' => '供应商添加成功',
            'supplier' => $supplier_data
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '添加失败：' . $conn->error
        ]);
    }
    
    $insert_stmt->close();
}

// 搜索供应商（可选，用于自动完成）
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search') {
    
    $search_term = isset($_GET['term']) ? trim($_GET['term']) : '';
    
    if (empty($search_term)) {
        echo json_encode([]);
        exit;
    }
    
    $search_sql = "SELECT id, supplier_code, company_name, contact_person, contact_phone, payment_terms 
                   FROM suppliers 
                   WHERE is_active = 1 
                   AND (company_name LIKE ? OR contact_person LIKE ? OR supplier_code LIKE ?)
                   ORDER BY company_name
                   LIMIT 20";
    
    $search_term_like = "%{$search_term}%";
    $search_stmt = $conn->prepare($search_sql);
    $search_stmt->bind_param("sss", $search_term_like, $search_term_like, $search_term_like);
    $search_stmt->execute();
    $result = $search_stmt->get_result();
    
    $suppliers = [];
    while ($row = $result->fetch_assoc()) {
        $suppliers[] = $row;
    }
    
    echo json_encode($suppliers);
    $search_stmt->close();
}

// 未知操作
else {
    echo json_encode(['success' => false, 'error' => '无效的操作']);
}

$conn->close();
?>