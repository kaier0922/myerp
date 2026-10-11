<?php
session_start();

// 生成4位随机验证码
$chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
$code  = '';
for ($i = 0; $i < 4; $i++) {
    $code .= $chars[random_int(0, strlen($chars) - 1)];
}
$_SESSION['captcha_code'] = strtolower($code);
$_SESSION['captcha_time'] = time();

// 生成图片
$width = 100; $height = 36;
$img = imagecreatetruecolor($width, $height);

// 背景色
$bg = imagecolorallocate($img, 240, 244, 248);
imagefill($img, 0, 0, $bg);

// 干扰线
for ($i = 0; $i < 4; $i++) {
    $lc = imagecolorallocate($img, rand(180,220), rand(180,220), rand(180,220));
    imageline($img, rand(0,$width), rand(0,$height), rand(0,$width), rand(0,$height), $lc);
}

// 干扰点
for ($i = 0; $i < 30; $i++) {
    $pc = imagecolorallocate($img, rand(150,200), rand(150,200), rand(150,200));
    imagesetpixel($img, rand(0,$width), rand(0,$height), $pc);
}

// 文字
$colors = [
    imagecolorallocate($img, 31, 109, 173),
    imagecolorallocate($img, 19, 82, 134),
    imagecolorallocate($img, 50, 130, 190),
    imagecolorallocate($img, 0, 80, 150),
];
$x = 8;
for ($i = 0; $i < strlen($code); $i++) {
    $angle = rand(-15, 15);
    $fc = $colors[array_rand($colors)];
    $font = 5;
    imagestring($img, $font, $x + rand(-2,2), rand(8, 14), $code[$i], $fc);
    $x += 22;
}

// 输出
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
imagepng($img);
imagedestroy($img);
?>
