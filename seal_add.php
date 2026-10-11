<?php
/**
 * 文件名: seal_add.php
 * 优化内容: 
 * 1. 修复左侧遮挡 (margin-left: 260px)
 * 2. 修复右侧导航用户信息区重叠
 * 3. 调大左侧菜单字体至 16px，补全所有菜单项
 */

session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/auth_helper.php';
require_admin();


$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seal_name = $_POST['seal_name'];
    $seal_type = $_POST['seal_type'];
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    // 处理文件上传
    if (isset($_FILES['seal_image']) && $_FILES['seal_image']['error'] === 0) {
        $upload_dir = 'uploads/seals/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['seal_image']['name'], PATHINFO_EXTENSION);
        $file_name = 'seal_' . time() . '_' . rand(1000, 9999) . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        
        require_once __DIR__ . '/upload_helper.php';
        $chk = validate_upload($_FILES['seal_image'], 'image');
        if (!$chk['ok']) {
            $error = $chk['error'];
        } elseif (!is_writable($upload_dir) || !is_writable(dirname($file_path))) {
            $error = '上传目录不可写，请检查目录权限：' . realpath($upload_dir);
        } elseif (move_uploaded_file($_FILES['seal_image']['tmp_name'], $file_path)) {
            // 如果设为默认，取消其他默认公章
            if ($is_default) {
                $conn->query("UPDATE seals SET is_default = 0");
            }
            
            // 插入数据库
            $stmt = $conn->prepare("INSERT INTO seals (user_id, seal_name, seal_type, file_path, is_default, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssii", $user_id, $seal_name, $seal_type, $file_path, $is_default, $user_id);
            
            if ($stmt->execute()) {
                writeLog($conn, 'seals', 'create', '新建公章');
                header('Location: seals.php');
                exit;
            } else {
                $error = '数据库写入失败: ' . $stmt->error;
            }
        }
    }
}

$conn->close();
?>
<?php $page_title = '添加公章 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <style>

        /* --- 顶部导航栏 --- */

        /* --- 侧边栏 (菜单字体 16px & 补全) --- */

        /* --- 主内容区 --- */
        .content-container { width: 100%; max-width: 800px; margin: 0 auto; }

        .page-subtitle { color: #64748b; font-size: 14px; }

        /* 表单卡片 */
        .required { color: #ef4444; margin-left: 4px; }

        .form-input, .form-select {
            width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #f8fafc; outline: none; transition: 0.2s;
        }
        .form-input:focus, .form-select:focus { border-color: #1f6dad; background: white; box-shadow: 0 0 0 3px rgba(31,109,173,0.1); }

        /* 文件上传区 */
        .file-upload {
            border: 2px dashed #e2e8f0; border-radius: 12px; padding: 40px; text-align: center; cursor: pointer; transition: 0.2s;
        }
        .file-upload:hover { border-color: #1f6dad; background: #f8fafc; }
        .file-upload-icon { font-size: 40px; margin-bottom: 12px; }
        .file-upload-text { font-size: 15px; font-weight: 500; color: #1e293b; }
        .file-upload-hint { font-size: 13px; color: #94a3b8; margin-top: 4px; }

        .preview-container {
            margin-top: 20px; text-align: center; padding: 15px; background: #f8fafc; border-radius: 12px; display: none;
        }
        .preview-image { max-width: 180px; max-height: 180px; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        .checkbox-group { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
        .checkbox-input { width: 18px; height: 18px; cursor: pointer; accent-color: #1f6dad; }
        .checkbox-label { font-size: 14px; color: #1e293b; font-weight: 500; cursor: pointer; }

        @media (max-width: 1024px) { }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="content-container">
        <div class="page-header">
            <h1 class="page-title">添加公章</h1>
            <p class="page-subtitle">上传企业印章，支持一键在文档中加盖电子印章</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="form-card">
            <div class="form-group">
                <label class="form-label">公章显示名称<span class="required">*</span></label>
                <input type="text" name="seal_name" class="form-input" required placeholder="例如：公司公章、合同专用章等">
            </div>

            <div class="form-group">
                <label class="form-label">公章类型<span class="required">*</span></label>
                <select name="seal_type" class="form-select" required>
                    <option value="">-- 请选择类型 --</option>
                    <option value="企业公章">企业公章</option>
                    <option value="合同专用章">合同专用章</option>
                    <option value="财务专用章">财务专用章</option>
                    <option value="发票专用章">发票专用章</option>
                    <option value="其他">其他</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">上传公章图片<span class="required">*</span></label>
                <div class="file-upload" onclick="document.getElementById('seal_image').click()">
                    <div class="file-upload-icon">🖋️</div>
                    <div class="file-upload-text">点击选择印章图片</div>
                    <div class="file-upload-hint">支持 PNG、JPG，推荐使用透明背景 PNG</div>
                </div>
                <input type="file" id="seal_image" name="seal_image" accept="image/*" required onchange="previewImage(this)" style="display:none;">
                
                <div class="preview-container" id="preview-container">
                    <img id="preview-image" class="preview-image" alt="预览">
                    <p style="margin-top:10px; font-size:12px; color:#64748b;">印章预览</p>
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_default" name="is_default" class="checkbox-input">
                    <label for="is_default" class="checkbox-label">设为默认公章</label>
                </div>
                <p style="font-size:12px; color:#94a3b8; margin-top:6px; margin-left:28px;">设为默认后，系统将优先在报价单和合同中使用此公章</p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存公章</button>
                <a href="seals.php" class="btn btn-secondary">取消</a>
            </div>
        </form>
    </div>
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