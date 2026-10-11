<?php
/**
 * auth_helper.php
 * 统一权限验证辅助函数
 * 在需要权限控制的页面顶部 require_once 此文件
 */

/**
 * 要求已登录，否则跳转登录页
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * 要求 admin 或 super_admin 角色
 * @param string $redirect 无权限时跳转地址
 */
function require_admin($redirect = 'index.php') {
    require_login();
    $role = $_SESSION['role'] ?? '';
    if (!in_array($role, ['admin', 'super_admin'])) {
        $_SESSION['flash_error'] = '您没有权限执行此操作，需要管理员权限。';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * 要求 super_admin 角色
 * @param string $redirect 无权限时跳转地址
 */
function require_super_admin($redirect = 'index.php') {
    require_login();
    $role = $_SESSION['role'] ?? '';
    if ($role !== 'super_admin') {
        $_SESSION['flash_error'] = '您没有权限执行此操作，需要超级管理员权限。';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * 判断当前用户是否为 admin 或以上
 */
function is_admin() {
    return in_array($_SESSION['role'] ?? '', ['admin', 'super_admin']);
}

/**
 * 判断当前用户是否为 super_admin
 */
function is_super_admin() {
    return ($_SESSION['role'] ?? '') === 'super_admin';
}


// ════════════════════════════════════════════════════════════════
// 多租户工具函数（基于 MULTI_TENANT 开关，config.php 控制）
// ════════════════════════════════════════════════════════════════

/**
 * 获取当前登录用户ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * 生成 WHERE 过滤条件字符串（直接嵌入 SQL）
 * 用法：$sql = "SELECT * FROM quotes WHERE " . tenantWhere() . " ORDER BY id DESC";
 * 带别名：tenantWhere('q') => "q.user_id = 123"
 */
function tenantWhere(string $alias = ''): string {
    if (!defined('MULTI_TENANT') || !MULTI_TENANT) return '1=1';
    if (false) return '1=1'; // 超级管理员不再看全部
    $uid = (int)getCurrentUserId();
    $col = $alias ? "{$alias}.user_id" : 'user_id';
    return "{$col} = {$uid}";
}

/**
 * 写入时获取 user_id 值
 * 用法：$uid = tenantId();  然后 bind_param("i...", $uid, ...)
 */
function tenantId(): int {
    if (!defined('MULTI_TENANT') || !MULTI_TENANT) return defined('ADMIN_USER_ID') ? (int)ADMIN_USER_ID : 1;
    return (int)getCurrentUserId();
}

/**
 * 操作前验证记录归属，防止越权编辑/删除
 * 用法：assertOwnership($conn, 'quotes', $quote_id);
 * 无权限时直接 die JSON 403
 */
function assertOwnership(mysqli $conn, string $table, int $id): void {
    if (!defined('MULTI_TENANT') || !MULTI_TENANT) return;
    if (($_SESSION['role'] ?? '') === 'super_admin') return; // 超级管理员可操作所有
    $uid  = (int)getCurrentUserId();
    $stmt = $conn->prepare("SELECT id FROM `{$table}` WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $found = $stmt->get_result()->num_rows;
    $stmt->close();
    if ($found === 0) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => '无权操作此记录'], JSON_UNESCAPED_UNICODE));
    }
}

/**
 * AND 版本（用于已有 WHERE 子句的 SQL 拼接）
 * 用法：$sql = "SELECT * FROM quotes WHERE status=1" . tenantAnd();
 */
function tenantAnd(string $alias = ''): string {
    if (!defined('MULTI_TENANT') || !MULTI_TENANT) return '';
    $uid = (int)getCurrentUserId();
    $col = $alias ? "{$alias}.user_id" : 'user_id';
    return " AND {$col} = {$uid}";
}
