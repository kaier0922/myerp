<?php
/**
 * 文件上传安全验证
 * validate_upload($file, $type='image') : ['ok'=>bool, 'ext'=>string, 'error'=>string]
 * $type: 'image' | 'document'
 */
function validate_upload(array $file, string $type = 'image'): array {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['ok'=>false, 'error'=>'文件上传失败，错误码：'.($file['error']??'?')];
    }

    // 允许的扩展名和对应真实MIME
    $image_rules = [
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
    ];
    $doc_rules = array_merge($image_rules, [
        'pdf'  => ['application/pdf'],
        'zip'  => ['application/zip','application/x-zip-compressed'],
        'rar'  => ['application/x-rar-compressed','application/vnd.rar'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ]);
    $rules = ($type === 'document') ? $doc_rules : $image_rules;

    // 检查文件大小（图片5MB，文档20MB）
    $max = ($type === 'document') ? 20*1024*1024 : 5*1024*1024;
    if ($file['size'] > $max) {
        return ['ok'=>false, 'error'=>'文件过大，最大允许'.($max/1024/1024).'MB'];
    }

    // 检查扩展名
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!isset($rules[$ext])) {
        $allowed = implode('/', array_keys($rules));
        return ['ok'=>false, 'error'=>"不支持的文件类型，仅允许：{$allowed}"];
    }

    // 用 finfo 检查真实MIME（防止改名绕过）
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);
    if (!in_array($real_mime, $rules[$ext])) {
        return ['ok'=>false, 'error'=>"文件内容与扩展名不符（实际类型：{$real_mime}）"];
    }

    // 图片类额外用 getimagesize 验证
    if (isset($image_rules[$ext])) {
        if (@getimagesize($file['tmp_name']) === false) {
            return ['ok'=>false, 'error'=>'文件不是有效的图片'];
        }
    }

    return ['ok'=>true, 'ext'=>$ext, 'error'=>''];
}
