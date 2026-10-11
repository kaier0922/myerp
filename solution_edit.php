<?php
/**
 * 文件名: solution_edit.php
 * 优化细节: 
 * 1. 修复左侧菜单遮挡 (margin-left: 260px)。
 * 2. 调大左侧菜单字体至 16px。
 * 3. 修复右侧导航用户信息区重叠。
 * 4. 保持富文本、附件上传、备注处理等逻辑 100% 完整。
 */
session_start();
require_once __DIR__ . '/auth_helper.php';
require_login();

require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/upload_helper.php';

if (!isset($_SESSION['user_id'])) {
    writeLog($conn, 'solutions', 'update', '修改方案');
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role = $_SESSION['role'];

$conn = getDBConnection();

$solution_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 获取方案信息
$solution = $conn->query("SELECT * FROM solutions WHERE id = $solution_id AND user_id = " . intval($_SESSION['user_id']) . "")->fetch_assoc();
if (!$solution) {
    header('Location: solutions.php');
    exit;
}

// 获取客户列表
$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name");

// 获取现有附件
$attachments = $conn->query("SELECT * FROM solution_attachments WHERE solution_id = $solution_id ORDER BY uploaded_at");

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $solution_name = $_POST['solution_name'];
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $customer_name = $_POST['customer_name'];
    $project_background = $_POST['project_background'];
    $solution_content = $_POST['solution_content'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];
    
    $stmt = $conn->prepare("UPDATE solutions SET solution_name = ?, customer_id = ?, customer_name = ?, project_background = ?, solution_content = ?, status = ?, remarks = ? WHERE id = ? AND user_id = " . intval($_SESSION['user_id']) . "");
    $stmt->bind_param("sisssssi", $solution_name, $customer_id, $customer_name, $project_background, $solution_content, $status, $remarks, $solution_id);
    
    if ($stmt->execute()) {
        // 处理附件上传
        if (isset($_FILES['attachments'])) {
            $upload_dir = 'attachments/solutions/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] === 0) {
                    $file_name = $_FILES['attachments']['name'][$key];
                    $file_size = $_FILES['attachments']['size'][$key];
                    $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                    $new_file_name = time() . '_' . rand(1000, 9999) . '.' . $file_extension;
                    $file_path = $upload_dir . $new_file_name;
                    
                    $allowed_ext = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx'];
                    $upload_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($upload_ext, $allowed_ext)) {
                        continue;
                    }
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $file_type = $_FILES['attachments']['type'][$key];
                        $stmt2 = $conn->prepare("INSERT INTO solution_attachments (solution_id, file_name, file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?)");
                        $stmt2->bind_param("issis", $solution_id, $file_name, $file_path, $file_size, $file_type);
                        $stmt2->execute();
                    }
                }
            }
        }
        
        // 处理删除附件
        if (isset($_POST['delete_attachments'])) {
            foreach ($_POST['delete_attachments'] as $att_id) {
                $att = $conn->query("SELECT file_path FROM solution_attachments WHERE id = " . intval($att_id))->fetch_assoc();
                if ($att && file_exists($att['file_path'])) {
                    unlink($att['file_path']);
                }
                $conn->query("DELETE FROM solution_attachments WHERE id = " . intval($att_id));
            }
        }
        
        header('Location: solution_view.php?id=' . $solution_id);
        exit;
    }
}

$conn->close();
?>
<?php $page_title = '编辑方案 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>

        /* --- 导航栏 --- */

        /* --- 侧边栏 (16px 字体 & 补全) --- */

        /* --- 内容区域修复 --- */
        .content-container { width: 100%; max-width: 1100px; margin: 0 auto; }

        .form-section { margin-bottom: 32px; padding-bottom: 24px; border-bottom: 1px solid #f1f5f9; }
        .form-section-title { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .form-section-title::before { content: ""; width: 4px; height: 18px; background: #1f6dad; border-radius: 2px; }

        .form-row.full { grid-template-columns: 1fr; }
        .required { color: #ef4444; margin-left: 4px; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; background: #f8fafc; outline: none; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #1f6dad; background: white; box-shadow: 0 0 0 3px rgba(31,109,173,0.1); }

        #editor { height: 400px; }
        .ql-toolbar { background: #f8fafc; border-radius: 8px 8px 0 0; }
        .ql-container { border-radius: 0 0 8px 8px; }

        /* 附件展示 */
        .existing-attachments { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .attachment-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
        .attachment-icon { font-size: 24px; }
        .attachment-info { flex: 1; min-width: 0; }
        .attachment-name { font-size: 13px; font-weight: 600; color: #1e293b; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .attachment-meta { font-size: 11px; color: #94a3b8; }

        .file-upload-area { border: 2px dashed #e2e8f0; border-radius: 12px; padding: 30px; text-align: center; }
        .file-list { margin-top: 15px; list-style: none; display: grid; grid-template-columns: 1fr 1fr; gap: 10px; text-align: left; }
        .file-list li { background: #f1f5f9; padding: 8px; border-radius: 6px; font-size: 12px; }

        .btn-save { padding: 12px 32px; background: linear-gradient(135deg, #1f6dad 0%, #135286 100%); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-cancel { padding: 12px 24px; background: #f1f5f9; color: #475569; border-radius: 8px; text-decoration: none; font-size: 14px; }

        @media (max-width: 1024px) { }
    </style>
</head>
<body>

<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="content-container">
        <div style="margin-bottom: 24px;">
            <h1 style="font-size: 28px; font-weight: 600; color: #1a202c;">编辑技术方案</h1>
            <p style="color: #64748b; font-size: 14px;">修改并完善当前方案的详细内容</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="form-card" onsubmit="return saveContent()">
            <div class="form-section">
                <h3 class="form-section-title">基本信息</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">方案编号</label>
                        <input type="text" class="form-input" value="<?php echo htmlspecialchars($solution['solution_no']); ?>" readonly style="background:#f1f5f9; color:#64748b;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">关联客户</label>
                        <select name="customer_id" class="form-select" onchange="updateCustomerName(this)">
                            <option value="">-- 选择客户 --</option>
                            <?php while($customer = $customers->fetch_assoc()): ?>
                            <option value="<?php echo $customer['id']; ?>" data-name="<?php echo htmlspecialchars($customer['company_name']); ?>" <?php echo $solution['customer_id'] == $customer['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($customer['company_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">方案状态<span class="required">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="草稿" <?php echo $solution['status'] === '草稿' ? 'selected' : ''; ?>>草稿</option>
                            <option value="审核中" <?php echo $solution['status'] === '审核中' ? 'selected' : ''; ?>>提交审核</option>
                            <option value="已通过" <?php echo $solution['status'] === '已通过' ? 'selected' : ''; ?>>已通过</option>
                            <option value="已驳回" <?php echo $solution['status'] === '已驳回' ? 'selected' : ''; ?>>已驳回</option>
                        </select>
                    </div>
                </div>
                <div class="form-row full">
                    <div class="form-group">
                        <label class="form-label">方案名称<span class="required">*</span></label>
                        <input type="text" name="solution_name" class="form-input" required value="<?php echo htmlspecialchars($solution['solution_name']); ?>">
                        <input type="hidden" name="customer_name" id="customer_name" value="<?php echo htmlspecialchars($solution['customer_name'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">项目背景</h3>
                <textarea name="project_background" class="form-textarea" placeholder="描述项目背景及客户需求..."><?php echo htmlspecialchars($solution['project_background'] ?? ''); ?></textarea>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">技术方案内容</h3>
                <div id="editor"></div>
                <input type="hidden" name="solution_content" id="solution_content">
            </div>

            <div class="form-section">
                <h3 class="form-section-title">附件管理</h3>
                <?php if ($attachments->num_rows > 0): ?>
                <label class="form-label">现有附件（勾选以删除）</label>
                <div class="existing-attachments">
                    <?php while($att = $attachments->fetch_assoc()): ?>
                    <div class="attachment-item">
                        <div class="attachment-icon">📎</div>
                        <div class="attachment-info">
                            <div class="attachment-name"><?php echo htmlspecialchars($att['file_name']); ?></div>
                            <div class="attachment-meta"><?php echo number_format($att['file_size'] / 1024, 2); ?> KB</div>
                        </div>
                        <input type="checkbox" name="delete_attachments[]" value="<?php echo $att['id']; ?>" style="width:18px; height:18px;">
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>

                <label class="form-label">上传新附件</label>
                <div class="file-upload-area">
                    <p style="font-size: 14px; color:#64748b; margin-bottom: 12px;">📎 点击浏览或拖拽文件上传</p>
                    <input type="file" name="attachments[]" multiple id="file-input" style="display:none;" onchange="showFileList(this)">
                    <button type="button" class="btn-logout-new" onclick="document.getElementById('file-input').click()">选择本地文件</button>
                    <ul id="file-list" class="file-list"></ul>
                </div>
            </div>

            <div class="form-section">
                <h3 class="form-section-title">备注说明</h3>
                <textarea name="remarks" class="form-textarea" style="height: 80px;"><?php echo htmlspecialchars($solution['remarks'] ?? ''); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">💾 保存并修改</button>
                <a href="solution_view.php?id=<?php echo $solution_id; ?>" class="btn-cancel">取消返回</a>
            </div>
        </form>
    </div>
</main>

<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script>
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: { toolbar: [[{ 'header': [1, 2, false] }],['bold', 'italic', 'underline'],[{ 'list': 'ordered'}, { 'list': 'bullet' }],['link', 'image'],['clean']] }
});
quill.root.innerHTML = <?php echo json_encode($solution['solution_content']); ?>;
function saveContent() { document.getElementById('solution_content').value = quill.root.innerHTML; return true; }
function updateCustomerName(select) { var opt = select.options[select.selectedIndex]; document.getElementById('customer_name').value = opt.getAttribute('data-name') || ''; }
function showFileList(input) {
    var list = document.getElementById('file-list');
    list.innerHTML = '';
    for (var i = 0; i < input.files.length; i++) {
        var li = document.createElement('li');
        li.textContent = '📄 ' + input.files[i].name;
        list.appendChild(li);
    }
}
</script>
</body>
</html>