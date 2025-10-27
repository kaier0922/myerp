<?php
session_start();
require_once 'config.php';

// 检查登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>新增产品</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f6fa; }
        .card { box-shadow: 0 2px 6px rgba(0,0,0,0.08); }
        .form-label { font-weight: 500; }
    </style>
</head>
<body>

<div class="container mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">➕ 新增产品</h4>
        <a href="products.php" class="btn btn-secondary btn-sm">← 返回产品列表</a>
    </div>

    <form action="product_save.php" method="post">
        <input type="hidden" name="action" value="add">

        <div class="card">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">产品名称 <span class="text-danger">*</span></label>
                    <input type="text" name="product_name" class="form-control" required placeholder="请输入产品名称">
                </div>
                <div class="col-md-3">
                    <label class="form-label">品牌</label>
                    <input type="text" name="brand" class="form-control" placeholder="如：戴尔 / 华硕 / 西部数据">
                </div>
                <div class="col-md-3">
                    <label class="form-label">型号</label>
                    <input type="text" name="model" class="form-control" placeholder="如：XPS13 / SN570 / 3060Ti">
                </div>

                <div class="col-md-12">
                    <label class="form-label">规格参数</label>
                    <textarea name="spec" class="form-control" rows="3" placeholder="如：Intel i7, 16GB, 512GB SSD, 15.6寸屏"></textarea>
                </div>

                <div class="col-md-3">
                    <label class="form-label">单位</label>
                    <input type="text" name="unit" class="form-control" placeholder="如：台 / 个 / 套" value="台">
                </div>

                <div class="col-md-3">
                    <label class="form-label">单价（元）</label>
                    <input type="number" step="0.01" name="price" class="form-control text-end" placeholder="输入单价" value="0.00">
                </div>

                <div class="col-md-3">
                    <label class="form-label">分类</label>
                    <select name="category" class="form-select">
                        <option value="">请选择分类</option>
                        <option value="电脑整机">电脑整机</option>
                        <option value="硬件配件">硬件配件</option>
                        <option value="网络设备">网络设备</option>
                        <option value="外设及配件">外设及配件</option>
                        <option value="系统软件">系统软件</option>
                        <option value="工程服务">工程服务</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">库存数量</label>
                    <input type="number" name="stock" class="form-control text-center" value="0" min="0">
                </div>

                <div class="col-12 mt-3 text-center">
                    <button type="submit" class="btn btn-success px-5">💾 保存产品</button>
                    <a href="products.php" class="btn btn-outline-secondary px-4">取消</a>
                </div>
            </div>
        </div>
    </form>
</div>

</body>
</html>
