<?php
/**
 * log_helper.php — 操作日志记录
 * 使用方法：writeLog($conn, 'quotes', 'create', '新建报价单 Q202604010001');
 */

function writeLog($conn, $module, $action, $detail = '') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $ip      = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip      = explode(',', $ip)[0]; // 取第一个IP

    // detail 加上IP
    $full_detail = $detail . ($ip ? "　[IP: $ip]" : '');

    $stmt = $conn->prepare("INSERT INTO system_logs (module, action, detail, user_id, created_at) VALUES (?,?,?,?,NOW())");
    if ($stmt) {
        $stmt->bind_param("sssi", $module, $action, $full_detail, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// 模块中文名映射
function getModuleName($module) {
    $map = [
        'quotes'      => '报价管理',
        'contracts'   => '合同管理',
        'deliveries'  => '送货管理',
        'customers'   => '客户管理',
        'suppliers'   => '供应商管理',
        'products'    => '产品管理',
        'receipts'    => '收据管理',
        'repair'      => '维修管理',
        'rentals'     => '租借管理',
        'finance'     => '财务管理',
        'users'       => '用户管理',
        'settings'    => '系统设置',
        'seals'       => '公章管理',
        'solutions'   => '方案管理',
        'auth'        => '登录认证',
    ];
    return $map[$module] ?? $module;
}

// 操作中文名映射
function getActionName($action) {
    $map = [
        'create'   => '新建',
        'update'   => '修改',
        'delete'   => '删除',
        'approve'  => '审核',
        'unapprove'=> '取消审核',
        'login'    => '登录',
        'logout'   => '退出',
        'export'   => '导出',
        'import'   => '导入',
        'pay'      => '付款',
        'receive'  => '收款',
        'status'   => '更新状态',
    ];
    return $map[$action] ?? $action;
}
?>
