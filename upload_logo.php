<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['logo'])) {
    echo json_encode(['success'=>false,'message'=>'没有收到文件']);
    exit;
}

$file = $_FILES['logo'];

// 类型校验
require_once __DIR__ . '/upload_helper.php';
$chk = validate_upload($file, 'image');
if (!$chk['ok']) {
    echo json_encode(['success'=>false,'message'=>$chk['error']]); exit;
}
$allowed_types = ['image/png','image/jpeg','image/gif','image/webp'];
if (false) {
    echo json_encode(['success'=>false,'message'=>'文件类型不支持']);
    exit;
}

// 大小校验
if ($file['size'] > 500 * 1024) {
    echo json_encode(['success'=>false,'message'=>'文件大小超过500KB']);
    exit;
}

// 保存到 uploads/logos/
$dir = __DIR__ . '/uploads/logos/';
if (!is_dir($dir)) mkdir($dir, 0755, true);

$uid = $_SESSION['user_id'];
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'logo_' . $uid . '_' . time() . '.' . strtolower($ext);
$dest = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success'=>false,'message'=>'文件保存失败']);
    exit;
}

$path = 'uploads/logos/' . $filename;
echo json_encode([
    'success' => true,
    'path' => $path,
    'url' => $path
]);
