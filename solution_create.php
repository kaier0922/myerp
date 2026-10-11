<?php
/**
 * 文件名: solution_create.php
 * 修改：去除Quill富文本编辑器，技术方案内容改为普通textarea；
 *       项目背景 200px，备注说明 150px，技术方案内容 300px。
 */
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/upload_helper.php';
require_once 'auth_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id  = $_SESSION['user_id'];
$nickname = $_SESSION['nickname'];
$role     = $_SESSION['role'];

$conn = getDBConnection();

$year_month  = date('Ym');
$count_query = $conn->query("SELECT COUNT(*) as count FROM solutions WHERE solution_no LIKE 'FA{$year_month}%'");
$count       = $count_query ? $count_query->fetch_assoc()['count'] : 0;
$solution_no = 'FA' . $year_month . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

$customers = $conn->query("SELECT id, COALESCE(short_name, company_name) AS company_name FROM customers WHERE " . tenantWhere() . " ORDER BY company_name");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $solution_name      = $_POST['solution_name'];
    $customer_id        = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $customer_name      = $_POST['customer_name'];
    $project_background = $_POST['project_background'];
    $solution_content   = $_POST['solution_content'];
    $status             = $_POST['status'];
    $remarks            = $_POST['remarks'];

    $stmt = $conn->prepare("INSERT INTO solutions (solution_no,solution_name,customer_id,customer_name,project_background,solution_content,status,remarks,created_by,user_id) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssisssssii", $solution_no,$solution_name,$customer_id,$customer_name,$project_background,$solution_content,$status,$remarks,$user_id,$user_id);

    if ($stmt->execute()) {
        $solution_id = $conn->insert_id;
        if (isset($_FILES['attachments'])) {
            $upload_dir = 'attachments/solutions/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] === 0) {
                    $file_name     = $_FILES['attachments']['name'][$key];
                    $new_file_name = time().'_'.rand(1000,9999).'.'.pathinfo($file_name, PATHINFO_EXTENSION);
                    $file_path     = $upload_dir.$new_file_name;
                    $allowed_ext = ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx'];
                    $upload_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (!in_array($upload_ext, $allowed_ext)) {
                        continue;
                    }
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt2 = $conn->prepare("INSERT INTO solution_attachments (solution_id,file_name,file_path,file_size,file_type) VALUES (?,?,?,?,?)");
                        $stmt2->bind_param("issis",$solution_id,$file_name,$file_path,$_FILES['attachments']['size'][$key],$_FILES['attachments']['type'][$key]);
                        $stmt2->execute();
                    }
                }
            }
        }
        header('Location: solution_view.php?id='.$solution_id);
        exit;
    }
}
$conn->close();
?>
<?php $page_title = '新建方案 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.content-container{width:100%;max-width:1000px;margin:0 auto;}
.form-section{margin-bottom:32px;padding-bottom:24px;border-bottom:1px solid #f1f5f9;}
.form-section-title{font-size:18px;font-weight:700;color:#1e293b;margin-bottom:20px;display:flex;align-items:center;gap:8px;}
.form-section-title::before{content:"";width:4px;height:18px;background:#1f6dad;border-radius:2px;}
.form-row.full{grid-template-columns:1fr;}
.required{color:#ef4444;margin-left:4px;}
.form-input,.form-select,.form-textarea{width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;background:#f8fafc;outline:none;transition:border-color 0.2s;font-family:inherit;}
.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,0.1);background:white;}
.form-textarea{resize:vertical;line-height:1.7;}
.file-upload-area{border:2px dashed #e2e8f0;border-radius:12px;padding:32px;text-align:center;}
.file-list{margin-top:15px;text-align:left;list-style:none;display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.file-list li{background:#f1f5f9;padding:8px;border-radius:6px;font-size:12px;}
.btn-save{padding:12px 32px;background:linear-gradient(135deg,#1f6dad 0%,#135286 100%);color:white;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:14px;transition:all 0.2s;}
.btn-save:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(31,109,173,0.4);}
.btn-cancel{padding:12px 24px;background:#f1f5f9;color:#475569;border-radius:8px;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;}
@media(max-width:1024px){}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<?php include __DIR__ . '/sidebar.php'; ?>

<main>
<div class="content-container">
    <div style="margin-bottom:24px;">
        <h1 style="font-size:28px;font-weight:600;color:#1a202c;">新建技术方案</h1>
        <p style="color:#64748b;font-size:14px;">请详细填写方案内容、附件及备注</p>
    </div>

    <form method="POST" enctype="multipart/form-data" class="form-card">

        <!-- 基本信息 -->
        <div class="form-section">
            <h3 class="form-section-title">基本信息</h3>
            <!-- 一行：方案编号 + 方案名称(宽) + 关联客户 + 客户名称 + 方案状态(窄) -->
            <div style="display:grid;grid-template-columns:160px 2fr 1fr 1fr 140px;gap:16px;align-items:end;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">方案编号</label>
                    <input type="text" class="form-input" value="<?php echo $solution_no; ?>" readonly style="background:#f1f5f9;color:#64748b;font-weight:600;">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">方案名称<span class="required">*</span></label>
                    <input type="text" name="solution_name" class="form-input" required placeholder="请输入方案完整标题">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">关联客户</label>
                    <select name="customer_id" class="form-select" onchange="updateCustomerName(this)">
                        <option value="">-- 选择客户 --</option>
                        <?php while($customer=$customers->fetch_assoc()): ?>
                        <option value="<?php echo $customer['id']; ?>" data-name="<?php echo htmlspecialchars($customer['company_name']); ?>">
                            <?php echo htmlspecialchars($customer['company_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">客户名称确认</label>
                    <input type="text" name="customer_name" id="customer_name" class="form-input" placeholder="自动填充或手动输入">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">状态<span class="required">*</span></label>
                    <select name="status" class="form-select" required>
                        <option value="草稿">存为草稿</option>
                        <option value="审核中">提交审核</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 项目背景 -->
        <div class="form-section">
            <h3 class="form-section-title">项目背景</h3>
            <textarea name="project_background" class="form-textarea" style="height:200px;"
                placeholder="描述项目背景、客户需求、现有问题等..."></textarea>
        </div>

        <!-- 技术方案内容（普通文本框） -->
        <div class="form-section">
            <h3 class="form-section-title">技术方案内容</h3>
            <textarea name="solution_content" id="solution_content" class="form-textarea" style="height:300px;"
                placeholder="请输入技术方案内容，可描述工程方案、设备选型、实施步骤、网络拓扑说明等..."></textarea>
        </div>

        <!-- 相关附件 -->
        <div class="form-section">
            <h3 class="form-section-title">相关附件</h3>
            <div class="file-upload-area">
                <p style="font-size:14px;color:#64748b;margin-bottom:12px;">📎 点击浏览本地文件并上传</p>
                <input type="file" name="attachments[]" multiple id="file-input" style="display:none;" onchange="showFileList(this)">
                <button type="button" class="btn-logout-link" style="padding:10px 20px;" onclick="document.getElementById('file-input').click()">选择文件</button>
                <ul id="file-list" class="file-list"></ul>
            </div>
        </div>

        <!-- 备注说明 -->
        <div class="form-section">
            <h3 class="form-section-title">备注说明</h3>
            <textarea name="remarks" class="form-textarea" style="height:150px;"
                placeholder="其他补充信息、特殊说明等..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-save">💾 保存方案</button>
            <a href="solutions.php" class="btn-cancel">取消</a>
        </div>
    </form>
</div>
</main>

<script>
function updateCustomerName(select) {
    var opt = select.options[select.selectedIndex];
    document.getElementById('customer_name').value = opt.getAttribute('data-name') || '';
}
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
