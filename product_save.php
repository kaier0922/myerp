<?php
/**
 * ============================================================================
 * 文件名: product_save_v4.php
 * 版本: 1.0
 * 说明: 专门处理 product_edit.php 提交的更新请求
 * 功能: 接收表单数据，将其中的兼容性参数封装为 JSON 并存入 platform_data 字段
 * ============================================================================
 */

header('Content-Type: application/json');
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once 'auth_helper.php';

// 1. 权限验证
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => '登录已过期，请重新登录']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效的请求方式']);
    exit;
}

$conn = getDBConnection();

try {
    // 2. 接收并清理基础数据
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) throw new Exception('无效的产品ID');
    assertOwnership($conn, 'products', $id);

    $name = trim($_POST['name'] ?? '');
    $product_type = $_POST['product_type'] ?? 'hardware';
    $category_id = intval($_POST['category_id'] ?? 0);
    $device_subtype = $_POST['device_subtype'] ?? '';
    $sku = trim($_POST['sku'] ?? '');
    $spec = trim($_POST['spec'] ?? '');
    $unit = trim($_POST['unit'] ?? '个');
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $default_price = floatval($_POST['default_price'] ?? 0);
    $stock_quantity = intval($_POST['stock_quantity'] ?? 0);
    $min_stock = intval($_POST['min_stock'] ?? 0);

    if (empty($name)) throw new Exception('产品名称不能为空');

    // 3. 【核心】封装 platform_data JSON 数据
    $platform_data = [];

    // 如果没有勾选“忽略自动校验”
    if (!isset($_POST['skip_compat'])) {
        if ($category_id == 11) { // CPU处理器
            $platform_data['socket'] = strtoupper(trim($_POST['cpu_socket'] ?? ''));
            $gens = trim($_POST['cpu_gens'] ?? '');
            $platform_data['generations'] = $gens ? array_map('trim', explode(',', $gens)) : [];
        } 
        elseif ($category_id == 12) { // 主板
            $platform_data['cpu_socket'] = strtoupper(trim($_POST['mb_socket'] ?? ''));
            $platform_data['memory_type'] = strtoupper(trim($_POST['mb_mem_type'] ?? ''));
            $mb_gens = trim($_POST['mb_gens'] ?? '');
            $platform_data['support_generations'] = $mb_gens ? array_map('trim', explode(',', $mb_gens)) : [];
        } 
        elseif ($category_id == 13) { // 内存
            $platform_data['memory_type'] = strtoupper(trim($_POST['mem_type'] ?? ''));
        }
    } else {
        // 用户主动勾选了忽略校验
        $platform_data['ignore_check'] = true;
    }

    // 将数组转为 JSON 字符串
    $platform_json = !empty($platform_data) ? json_encode($platform_data, JSON_UNESCAPED_UNICODE) : null;

    // 4. 执行数据库更新
    $sql = "UPDATE products SET 
                category_id = ?, 
                product_type = ?, 
                device_subtype = ?, 
                sku = ?, 
                name = ?, 
                spec = ?, 
                unit = ?, 
                cost_price = ?, 
                default_price = ?, 
                stock_quantity = ?, 
                min_stock = ?, 
                platform_data = ?,
                updated_at = NOW()
            WHERE id = ?";

    // 权限：超管可编辑公共产品(user_id=0)和所有产品；普通用户只能编辑自己的私有产品
    $cur_uid  = (int)($_SESSION['user_id'] ?? 0);
    $is_super = (($_SESSION['role'] ?? '') === 'super_admin') || is_admin();
    if (!$is_super) {
        $_pck = $conn->prepare("SELECT id FROM products WHERE id = ? AND user_id = ? AND is_active = 1");
        $_pck->bind_param('ii', $id, $cur_uid);
        $_pck->execute();
        if (!$_pck->get_result()->fetch_assoc()) {
            $_pck->close();
            throw new Exception('权限不足，公共产品库只有超级管理员可以修改');
        }
        $_pck->close();
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception($conn->error);

    // 绑定参数 (i=int, s=string, d=double)
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $stmt->bind_param(
        'issssssdddisi', 
        $category_id, 
        $product_type, 
        $device_subtype, 
        $sku, 
        $name, 
        $spec, 
        $unit, 
        $cost_price, 
        $default_price, 
        $stock_quantity, 
        $min_stock, 
        $platform_json, 
        $id
    );

    if ($stmt->execute()) {
        writeLog($conn, 'products', 'update', '保存产品');
        echo json_encode(['success' => true, 'message' => '更新成功']);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}