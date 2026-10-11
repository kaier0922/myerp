<?php
/**
 * 文件名: customer_save.php
 * 说明: 客户保存/更新处理（已加入 tax_number 字段）
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success'=>false,'message'=>'请先登录'], JSON_UNESCAPED_UNICODE));
}

$user_id      = $_SESSION['user_id'];
$action       = isset($_POST['action'])       ? $_POST['action']                  : '';
$customer_id  = isset($_POST['id'])           ? intval($_POST['id'])              : 0;
$company_name = isset($_POST['company_name']) ? trim($_POST['company_name'])      : '';
$short_name   = isset($_POST['short_name'])   ? trim($_POST['short_name'])        : '';
$contact_name = isset($_POST['contact_name']) ? trim($_POST['contact_name'])      : '';
$phone        = isset($_POST['phone'])        ? trim($_POST['phone'])             : '';
$email        = isset($_POST['email'])        ? trim($_POST['email'])             : '';
$tax_number   = isset($_POST['tax_number'])   ? trim($_POST['tax_number'])        : '';
$address      = isset($_POST['address'])      ? trim($_POST['address'])           : '';
$notes        = isset($_POST['notes'])        ? trim($_POST['notes'])             : '';

if (empty($company_name)) die(json_encode(['success'=>false,'message'=>'公司名称不能为空'], JSON_UNESCAPED_UNICODE));
if (empty($contact_name)) die(json_encode(['success'=>false,'message'=>'联系人不能为空'],   JSON_UNESCAPED_UNICODE));
if (empty($phone))        die(json_encode(['success'=>false,'message'=>'联系电话不能为空'], JSON_UNESCAPED_UNICODE));

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die(json_encode(['success'=>false,'message'=>'邮箱格式不正确'], JSON_UNESCAPED_UNICODE));
}

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    error_log('数据库连接失败: ' . $e->getMessage());
    die(json_encode(['success'=>false,'message'=>'数据库连接失败'], JSON_UNESCAPED_UNICODE));
}

$conn->begin_transaction();

try {
    if ($action === 'add') {

        // 检查公司名称是否重复
        $ck = $conn->prepare("SELECT id FROM customers WHERE company_name = ? AND user_id = ?");
        $ck->bind_param('si', $company_name, $user_id);
        $ck->execute();
        if ($ck->get_result()->num_rows > 0) {
            $ck->close(); $conn->close();
            die(json_encode(['success'=>false,'message'=>'公司名称已存在，请使用其他名称'], JSON_UNESCAPED_UNICODE));
        }
        $ck->close();

        $sql  = "INSERT INTO customers
                    (company_name, short_name, contact_name, phone, email, tax_number, address, notes, user_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssssi',
            $company_name, $short_name, $contact_name, $phone,
            $email, $tax_number, $address, $notes, $user_id
        );

        if (!$stmt->execute()) throw new Exception('添加客户失败: ' . $stmt->error);

        $new_id = $stmt->insert_id;
        $stmt->close();

        error_log("[客户管理] 用户 {$user_id} 添加客户: {$company_name} (ID: {$new_id})");
        $conn->commit();

        writeLog($conn, 'customers', 'create', '新建/修改客户');
        echo json_encode([
            'success'     => true,
            'message'     => "客户 {$company_name} 添加成功",
            'customer_id' => $new_id
        ], JSON_UNESCAPED_UNICODE);

    } elseif ($action === 'update') {

        if ($customer_id <= 0) throw new Exception('无效的客户ID');

        // 验证客户存在
        $ck = $conn->prepare("SELECT id FROM customers WHERE id = ? AND user_id = ?");
        $ck->bind_param('ii', $customer_id, $user_id);
        $ck->execute();
        if ($ck->get_result()->num_rows === 0) {
            $ck->close(); $conn->close();
            die(json_encode(['success'=>false,'message'=>'客户不存在'], JSON_UNESCAPED_UNICODE));
        }
        $ck->close();

        // 检查名称是否与其他客户重复
        $ck2 = $conn->prepare("SELECT id FROM customers WHERE company_name = ? AND id != ? AND user_id = ?");
        $ck2->bind_param('sii', $company_name, $customer_id, $user_id);
        $ck2->execute();
        if ($ck2->get_result()->num_rows > 0) {
            $ck2->close(); $conn->close();
            die(json_encode(['success'=>false,'message'=>'公司名称已被其他客户使用'], JSON_UNESCAPED_UNICODE));
        }
        $ck2->close();

        $sql  = "UPDATE customers SET
                    company_name = ?, short_name = ?, contact_name = ?, phone = ?,
                    email = ?, tax_number = ?, address = ?, notes = ?
                 WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssssii',
            $company_name, $short_name, $contact_name, $phone,
            $email, $tax_number, $address, $notes,
            $customer_id, $user_id
        );

        if (!$stmt->execute()) throw new Exception('更新客户失败: ' . $stmt->error);
        $stmt->close();

        error_log("[客户管理] 用户 {$user_id} 更新客户: {$company_name} (ID: {$customer_id})");
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "客户 {$company_name} 更新成功"
        ], JSON_UNESCAPED_UNICODE);

    } else {
        throw new Exception('无效的操作类型');
    }

} catch (Exception $e) {
    $conn->rollback();
    error_log("[客户管理错误] " . $e->getMessage());
    echo json_encode(['success'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>