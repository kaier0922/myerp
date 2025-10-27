<?php
/**
 * =====================================================
 * 文件名：seal_management.php
 * 功能：公章管理系统
 * 描述：上传、管理、预览公章，用于报价单和送货单
 * =====================================================
 */

session_start();
require_once 'config.php';

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// 创建公章目录
$seal_dir = __DIR__ . '/uploads/seals';
if (!file_exists($seal_dir)) {
    mkdir($seal_dir, 0755, true);
}

// 处理上传公章
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_seal') {
    $seal_name = trim($_POST['seal_name']);
    $seal_type = $_POST['seal_type'];
    $description = $_POST['description'] ?? '';
    
    if (isset($_FILES['seal_image']) && $_FILES['seal_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['seal_image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // 验证文件类型
        if ($ext !== 'png') {
            $error = '只支持PNG格式的公章图片（需要透明背景）';
        } else {
            // 生成唯一文件名
            $filename = 'seal_' . time() . '_' . uniqid() . '.png';
            $filepath = $seal_dir . '/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // 插入数据库
                $sql = "INSERT INTO seals (seal_name, seal_type, file_path, description, uploaded_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssi", $seal_name, $seal_type, $filename, $description, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['message'] = '公章上传成功！';
                    $_SESSION['message_type'] = 'success';
                    header('Location: seal_management.php');
                    exit;
                } else {
                    $error = '数据库保存失败：' . $conn->error;
                    unlink($filepath); // 删除已上传的文件
                }
            } else {
                $error = '文件上传失败';
            }
        }
    } else {
        $error = '请选择要上传的公章图片';
    }
}

// 处理删除公章
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $seal_id = intval($_GET['id']);
    
    // 获取文件路径
    $sql = "SELECT file_path FROM seals WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seal_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $filepath = $seal_dir . '/' . $row['file_path'];
        
        // 删除文件
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        // 删除数据库记录
        $sql = "DELETE FROM seals WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $seal_id);
        $stmt->execute();
        
        $_SESSION['message'] = '公章删除成功！';
        $_SESSION['message_type'] = 'success';
    }
    
    header('Location: seal_management.php');
    exit;
}

// 处理设置默认公章
if (isset($_GET['action']) && $_GET['action'] === 'set_default' && isset($_GET['id'])) {
    $seal_id = intval($_GET['id']);
    
    // 先取消所有默认
    $conn->query("UPDATE seals SET is_default = 0");
    
    // 设置新的默认
    $sql = "UPDATE seals SET is_default = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seal_id);
    $stmt->execute();
    
    $_SESSION['message'] = '默认公章设置成功！';
    $_SESSION['message_type'] = 'success';
    header('Location: seal_management.php');
    exit;
}

// 获取所有公章
$seals = [];
$sql = "SELECT s.*, u.username as uploader_name 
        FROM seals s 
        LEFT JOIN users u ON s.uploaded_by = u.id 
        ORDER BY s.is_default DESC, s.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $seals[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公章管理 - 财务管理系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }

        /* 顶部导航 */
        .navbar {
            background: white;
            height: 64px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 20px;
            font-weight: 600;
            color: #667eea;
            text-decoration: none;
        }

        .btn-back {
            padding: 8px 16px;
            background: #f7fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #4a5568;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .btn-back:hover {
            background: #edf2f7;
        }

        /* 主内容 */
        .main-content {
            margin-top: 64px;
            padding: 32px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 600;
            color: #1a202c;
        }

        .btn-upload {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* 提示信息 */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
        }

        /* 公章网格 */
        .seal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .seal-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
            position: relative;
        }

        .seal-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }

        .seal-default-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .seal-preview {
            width: 150px;
            height: 150px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            border-radius: 8px;
            overflow: hidden;
        }

        .seal-preview img {
            max-width: 120px;
            max-height: 120px;
            object-fit: contain;
        }

        .seal-name {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            text-align: center;
        }

        .seal-type {
            display: inline-block;
            padding: 4px 12px;
            background: #e6fffa;
            color: #234e52;
            border-radius: 12px;
            font-size: 12px;
            margin-bottom: 12px;
        }

        .seal-info {
            font-size: 13px;
            color: #718096;
            margin-bottom: 16px;
        }

        .seal-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-action {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-default {
            background: #fef5e7;
            color: #d69e2e;
        }

        .btn-default:hover {
            background: #fbd38d;
        }

        .btn-delete {
            background: #fed7d7;
            color: #c53030;
        }

        .btn-delete:hover {
            background: #fc8181;
            color: white;
        }

        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .empty-text {
            color: #718096;
            margin-bottom: 24px;
        }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 24px 32px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #1a202c;
        }

        .modal-close {
            width: 32px;
            height: 32px;
            border: none;
            background: #f7fafc;
            border-radius: 6px;
            cursor: pointer;
            font-size: 20px;
            color: #718096;
        }

        .modal-body {
            padding: 24px 32px;
        }

        .modal-footer {
            padding: 16px 32px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* 表单 */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .required {
            color: #e53e3e;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .file-upload {
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            padding: 32px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .file-upload:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .upload-icon {
            font-size: 48px;
            margin-bottom: 12px;
        }

        .upload-text {
            color: #4a5568;
            margin-bottom: 8px;
        }

        .upload-hint {
            font-size: 13px;
            color: #a0aec0;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #f7fafc;
            color: #4a5568;
            border: 1px solid #e2e8f0;
        }

        /* 使用说明 */
        .usage-tips {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }

        .tips-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #2d3748;
        }

        .tips-list {
            list-style: none;
            padding: 0;
        }

        .tips-list li {
            padding: 8px 0;
            color: #4a5568;
            font-size: 14px;
        }

        .tips-list li::before {
            content: "💡 ";
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <span>🖊️</span>
            <span>公章管理</span>
        </a>
        <a href="index.php" class="btn-back">← 返回首页</a>
    </nav>

    <!-- 主内容 -->
    <main class="main-content">
        <!-- 页面标题 -->
        <div class="page-header">
            <h1 class="page-title">📝 公章管理</h1>
            <button class="btn-upload" onclick="openUploadModal()">
                ➕ 上传新公章
            </button>
        </div>

        <!-- 成功/错误提示 -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'success'; ?>">
                <span><?php echo $_SESSION['message_type'] === 'success' ? '✅' : '❌'; ?></span>
                <span><?php echo htmlspecialchars($_SESSION['message']); ?></span>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <span>❌</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- 公章列表 -->
        <?php if (count($seals) > 0): ?>
            <div class="seal-grid">
                <?php foreach ($seals as $seal): ?>
                    <div class="seal-card">
                        <?php if ($seal['is_default']): ?>
                            <div class="seal-default-badge">⭐ 默认</div>
                        <?php endif; ?>
                        
                        <div class="seal-preview">
                            <img src="uploads/seals/<?php echo htmlspecialchars($seal['file_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($seal['seal_name']); ?>">
                        </div>
                        
                        <div class="seal-name"><?php echo htmlspecialchars($seal['seal_name']); ?></div>
                        
                        <div style="text-align: center; margin-bottom: 12px;">
                            <span class="seal-type"><?php echo htmlspecialchars($seal['seal_type']); ?></span>
                        </div>
                        
                        <?php if ($seal['description']): ?>
                            <div class="seal-info">
                                <?php echo htmlspecialchars($seal['description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="seal-info" style="text-align: center;">
                            上传于：<?php echo date('Y-m-d', strtotime($seal['created_at'])); ?>
                        </div>
                        
                        <div class="seal-actions">
                            <?php if (!$seal['is_default']): ?>
                                <a href="?action=set_default&id=<?php echo $seal['id']; ?>" 
                                   class="btn-action btn-default"
                                   onclick="return confirm('确定设为默认公章？')">
                                    设为默认
                                </a>
                            <?php endif; ?>
                            <a href="?action=delete&id=<?php echo $seal['id']; ?>" 
                               class="btn-action btn-delete"
                               onclick="return confirm('确定删除这个公章？')">
                                删除
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">🖊️</div>
                <div class="empty-title">还没有上传公章</div>
                <div class="empty-text">点击上方"上传新公章"按钮开始添加</div>
                <button class="btn-upload" onclick="openUploadModal()">
                    ➕ 上传第一个公章
                </button>
            </div>
        <?php endif; ?>

        <!-- 使用说明 -->
        <div class="usage-tips">
            <h3 class="tips-title">💡 使用说明</h3>
            <ul class="tips-list">
                <li>支持PNG格式的公章图片，建议使用透明背景</li>
                <li>公章图片建议尺寸：800x800像素以上，保证清晰度</li>
                <li>可以上传多个公章（公章、财务章、合同章、法人章等）</li>
                <li>设置默认公章后，在报价单和送货单中自动使用</li>
                <li>在报价单和送货单页面可以选择不同的公章进行盖章</li>
                <li>公章会自动保存在 uploads/seals/ 目录中</li>
            </ul>
        </div>
    </main>

    <!-- 上传公章模态框 -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">➕ 上传新公章</h2>
                <button type="button" class="modal-close" onclick="closeUploadModal()">×</button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload_seal">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">公章名称 <span class="required">*</span></label>
                        <input type="text" name="seal_name" class="form-input" 
                               placeholder="如：深圳市凯尔耐特科技有限公司公章" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">公章类型 <span class="required">*</span></label>
                        <select name="seal_type" class="form-select" required>
                            <option value="">请选择类型</option>
                            <option value="公章">公章</option>
                            <option value="财务章">财务章</option>
                            <option value="合同章">合同章</option>
                            <option value="法人章">法人章</option>
                            <option value="其他">其他</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">公章图片 <span class="required">*</span></label>
                        <div class="file-upload" onclick="document.getElementById('sealFile').click()">
                            <input type="file" id="sealFile" name="seal_image" 
                                   accept=".png" required onchange="previewSeal(this)">
                            <div class="upload-icon">📤</div>
                            <div class="upload-text">点击选择公章图片</div>
                            <div class="upload-hint">支持PNG格式，建议800x800px以上</div>
                            <div id="previewArea" style="margin-top: 16px;"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">备注说明</label>
                        <textarea name="description" class="form-textarea" 
                                  placeholder="可选填写备注..." rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">取消</button>
                    <button type="submit" class="btn btn-primary">💾 上传公章</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('show');
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('show');
            document.getElementById('uploadForm').reset();
            document.getElementById('previewArea').innerHTML = '';
        }

        function previewSeal(input) {
            const previewArea = document.getElementById('previewArea');
            previewArea.innerHTML = '';

            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.style.maxWidth = '150px';
                    img.style.maxHeight = '150px';
                    img.style.border = '1px solid #e2e8f0';
                    img.style.borderRadius = '8px';
                    img.style.padding = '8px';
                    img.style.background = 'white';
                    previewArea.appendChild(img);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // ESC关闭模态框
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeUploadModal();
            }
        });
    </script>
</body>
</html>