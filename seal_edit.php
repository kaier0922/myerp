<?php

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/upload_helper.php';
require_admin();


$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// 获取公章ID
$seal_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seal_name = $_POST['seal_name'];
    $seal_type = $_POST['seal_type'];
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // 如果设为默认，取消其他默认公章
    if ($is_default) {
        $conn->query("UPDATE seals SET is_default = 0");
    }
    
    // 处理文件上传（如果有新文件）
    if (isset($_FILES['seal_image']) && $_FILES['seal_image']['error'] === 0) {
        $upload_dir = 'uploads/seals/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['seal_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'seal_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        $allowed_ext = ['jpg','jpeg','png','gif','webp'];
        $upload_ext = strtolower(pathinfo($_FILES['seal_image']['name'], PATHINFO_EXTENSION));
        if (!in_array($upload_ext, $allowed_ext)) {
            $error = '只允许上传图片文件（jpg/jpeg/png/gif/webp）';
        } elseif (move_uploaded_file($_FILES['seal_image']['tmp_name'], $file_path)) {
            // 删除旧文件
            $old_seal = $conn->query("SELECT file_path FROM seals WHERE id = $seal_id AND user_id = " . intval($_SESSION['user_id']) . "")->fetch_assoc();
            if ($old_seal && file_exists($old_seal['file_path'])) {
                unlink($old_seal['file_path']);
            }
            
            // 更新数据库（包含新图片）
            $stmt = $conn->prepare("UPDATE seals SET seal_name = ?, seal_type = ?, file_path = ?, is_default = ? WHERE id = ?");
            $stmt->bind_param("sssii", $seal_name, $seal_type, $file_path, $is_default, $seal_id);
        }
    } else {
        // 更新数据库（不更新图片）
        $stmt = $conn->prepare("UPDATE seals SET seal_name = ?, seal_type = ?, is_default = ? WHERE id = ?");
        $stmt->bind_param("ssii", $seal_name, $seal_type, $is_default, $seal_id);
    }
    
    if ($stmt->execute()) {
        writeLog($conn, 'seals', 'update', '修改公章');
        header('Location: seals.php');
        exit;
    }
}

// 获取公章信息
$seal = $conn->query("SELECT * FROM seals WHERE id = $seal_id AND user_id = " . intval($_SESSION['user_id']) . "")->fetch_assoc();
if (!$seal) {
    header('Location: seals.php');
    exit;
}

$conn->close();
?>
<?php $page_title = '编辑公章 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

        /* 顶部导航栏 */

        /* 侧边栏 */

        /* 主内容区 */
        .main-inner {
            max-width: 760px;
            margin: 0 auto;
        }

        /* 页面头部 */

        .page-subtitle {
            color: #718096;
            font-size: 14px;
        }

        /* 表单卡片 */

        .form-label .required {
            color: #e53e3e;
            margin-left: 4px;
        }

        .form-input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .form-select:focus {
            outline: none;
            border-color: #1f6dad;
            box-shadow: 0 0 0 3px rgba(31, 109, 173, 0.1);
        }

        .form-help {
            font-size: 13px;
            color: #718096;
            margin-top: 6px;
        }

        /* 当前图片预览 */
        .current-image {
            margin-top: 12px;
            padding: 16px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .current-image-label {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 8px;
            display: block;
        }

        .current-image img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* 文件上传 */
        .file-upload {
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
            padding: 32px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }

        .file-upload:hover {
            border-color: #1f6dad;
            background: #f7fafc;
        }

        .file-upload-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .file-upload-text {
            font-size: 14px;
            color: #4a5568;
            margin-bottom: 4px;
        }

        .file-upload-hint {
            font-size: 12px;
            color: #a0aec0;
        }

        input[type="file"] {
            display: none;
        }

        /* 新图片预览 */
        .preview-container {
            margin-top: 16px;
            display: none;
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* 复选框 */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-label {
            font-size: 14px;
            color: #2d3748;
            cursor: pointer;
        }

        /* 按钮 */

        /* 响应式 */
        @media (max-width: 768px) {
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
<div class="main-inner">
    <div class="page-header">
        <h1 class="page-title">编辑公章</h1>
        <p class="page-subtitle">修改公章信息或更换公章图片</p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="form-card">
        <div class="form-group">
            <label class="form-label">
                公章名称<span class="required">*</span>
            </label>
            <input type="text" name="seal_name" class="form-input" required 
                   value="<?php echo htmlspecialchars($seal['seal_name']); ?>" 
                   placeholder="例如：公司公章、合同专用章等">
        </div>

        <div class="form-group">
            <label class="form-label">
                公章类型<span class="required">*</span>
            </label>
            <select name="seal_type" class="form-select" required>
                <option value="">请选择</option>
                <option value="企业公章" <?php echo $seal['seal_type'] === '企业公章' ? 'selected' : ''; ?>>企业公章</option>
                <option value="合同专用章" <?php echo $seal['seal_type'] === '合同专用章' ? 'selected' : ''; ?>>合同专用章</option>
                <option value="财务专用章" <?php echo $seal['seal_type'] === '财务专用章' ? 'selected' : ''; ?>>财务专用章</option>
                <option value="发票专用章" <?php echo $seal['seal_type'] === '发票专用章' ? 'selected' : ''; ?>>发票专用章</option>
                <option value="其他" <?php echo $seal['seal_type'] === '其他' ? 'selected' : ''; ?>>其他</option>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label">当前公章图片</label>
            <div class="current-image">
                <img src="<?php echo htmlspecialchars($seal['file_path']); ?>" alt="当前公章">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">更换公章图片（可选）</label>
            <div class="file-upload" onclick="document.getElementById('seal_image').click()">
                <div class="file-upload-icon">📤</div>
                <div class="file-upload-text">点击上传新的公章图片</div>
                <div class="file-upload-hint">支持 PNG、JPG 格式，建议背景透明。留空则不更换图片</div>
            </div>
            <input type="file" id="seal_image" name="seal_image" accept="image/*" onchange="previewImage(this)">
            <div class="preview-container" id="preview-container">
                <span class="current-image-label">新图片预览：</span>
                <img id="preview-image" class="preview-image" alt="预览">
            </div>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <input type="checkbox" id="is_default" name="is_default" class="checkbox-input" 
                       <?php echo $seal['is_default'] ? 'checked' : ''; ?>>
                <label for="is_default" class="checkbox-label">设为默认公章</label>
            </div>
            <div class="form-help">默认公章将在新建报价单和合同时自动选中</div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存修改</button>
            <a href="seals.php" class="btn btn-secondary">取消</a>
        </div>
    </form>
</div><!-- /.main-inner -->
</main>

<script>
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('preview-image').src = e.target.result;
            document.getElementById('preview-container').style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>
