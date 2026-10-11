<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/upload_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$error    = '';
$success  = '';

// 图片上传处理
function uploadIdCard($file_key, $conn) {
    if (empty($_FILES[$file_key]['name'])) return null;
    $f    = $_FILES[$file_key];
    $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) return null;
    $dir  = __DIR__ . '/uploads/idcards/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $fname = 'idcard_' . uniqid() . '.' . $ext;
    $chk = validate_upload($f, 'image');
    if (!$chk['ok']) return null;
    move_uploaded_file($f['tmp_name'], $dir . $fname);
    return 'uploads/idcards/' . $fname;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $gender        = $_POST['gender'] ?? 'male';
    $phone         = trim($_POST['phone'] ?? '');
    $wechat        = trim($_POST['wechat'] ?? '');
    $id_card_no    = trim($_POST['id_card_no'] ?? '');
    $specialties   = json_encode($_POST['specialties'] ?? []);
    $skill_level   = $_POST['skill_level'] ?? 'junior';
    $certifications= trim($_POST['certifications'] ?? '');
    $service_area  = trim($_POST['service_area'] ?? '');
    $employment_type= $_POST['employment_type'] ?? 'fulltime';
    $fee_rate      = floatval($_POST['fee_rate'] ?? ($employment_type === 'contractor' ? 70.00 : 5.00));
    $status        = $_POST['status'] ?? 'active';
    $join_date     = $_POST['join_date'] ?: null;
    $leave_date    = $_POST['leave_date'] ?: null;
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $emergency_phone   = trim($_POST['emergency_phone'] ?? '');
    $bank_name     = trim($_POST['bank_name'] ?? '');
    $bank_account  = trim($_POST['bank_account'] ?? '');
    $notes         = trim($_POST['notes'] ?? '');

    if (!$name) { $error = '姓名不能为空'; }
    elseif (!$phone) { $error = '手机号不能为空'; }
    else {
        $id_card_front = uploadIdCard('id_card_front', $conn);
        $id_card_back  = uploadIdCard('id_card_back',  $conn);

        $stmt = $conn->prepare("INSERT INTO technicians
            (name,gender,phone,wechat,id_card_no,id_card_front,id_card_back,
             specialties,skill_level,certifications,service_area,employment_type,
             fee_rate,status,join_date,leave_date,emergency_contact,emergency_phone,
             bank_name,bank_account,notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssssssssdssssssss",
            $name,$gender,$phone,$wechat,$id_card_no,$id_card_front,$id_card_back,
            $specialties,$skill_level,$certifications,$service_area,$employment_type,
            $fee_rate,$status,$join_date,$leave_date,$emergency_contact,$emergency_phone,
            $bank_name,$bank_account,$notes);
        if ($stmt->execute()) {
            writeLog($conn, 'technicians', 'create', '新建技师');
            header('Location: technicians.php?added=1');
            exit;
        } else {
            $error = '保存失败：' . $conn->error;
        }
    }
}

function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
$conn->close();
?>
<?php $page_title = '添加工程师 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.menu-sub
.back-btn{padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;font-weight:500;text-decoration:none;display:inline-flex;align-items:center;gap:6px;cursor:pointer;}
.back-btn:hover{background:#edf2f7;}
.form-card-head{background:linear-gradient(135deg,#1f6dad,#135286);padding:14px 24px;color:white;font-size:15px;font-weight:600;}
.form-card-
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.form-grid.cols3{grid-template-columns:1fr 1fr 1fr;}
.form-group.full{grid-column:1/-1;}
.form-label .req{color:#e53e3e;margin-left:2px;}
.form-control{padding:9px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
textarea.form-control{resize:vertical;min-height:80px;}
.specialty-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.specialty-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .2s;font-size:13px;}
.specialty-item:hover{border-color:#1f6dad;background:#f0f0ff;}
.specialty-item input{cursor:pointer;}
.specialty-item.checked{border-color:#1f6dad;background:#eef2ff;color:#4338ca;font-weight:500;}
.upload-area{border:2px dashed #e2e8f0;border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:#fafbff;}
.upload-area:hover{border-color:#1f6dad;background:#f0f0ff;}
.upload-preview{max-width:180px;max-height:120px;border-radius:6px;margin-top:8px;display:none;}
.btn-submit{padding:10px 28px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:15px;font-weight:500;cursor:pointer;}
.btn-submit:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(31,109,173,.4);}
.btn-cancel{padding:10px 24px;background:white;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;color:#4a5568;cursor:pointer;text-decoration:none;}
.alert-error{background:#fff5f5;border:1px solid #feb2b2;color:#c53030;padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:14px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">➕ 添加工程师</h1>
        <a href="technicians.php" class="back-btn">← 返回</a>
    </div>

    <?php if ($error): ?><div class="alert-error">❌ <?=e($error)?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <!-- 基本信息 -->
        <div class="form-card">
            <div class="form-card-head">👤 基本信息</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">姓名<span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="请输入姓名" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">性别</label>
                        <select name="gender" class="form-control">
                            <option value="male">男</option>
                            <option value="female">女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">手机号<span class="req">*</span></label>
                        <input type="text" name="phone" class="form-control" placeholder="请输入手机号" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">微信号</label>
                        <input type="text" name="wechat" class="form-control" placeholder="请输入微信号">
                    </div>
                    <div class="form-group">
                        <label class="form-label">身份证号</label>
                        <input type="text" name="id_card_no" class="form-control" placeholder="选填">
                    </div>
                    <div class="form-group">
                        <label class="form-label">负责区域</label>
                        <input type="text" name="service_area" class="form-control" placeholder="如：深圳南山、宝安">
                    </div>
                </div>
            </div>
        </div>

        <!-- 身份证上传 -->
        <div class="form-card">
            <div class="form-card-head">🪪 身份证扫描（选填）</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">身份证正面</label>
                        <div class="upload-area" onclick="document.getElementById('id_card_front').click()">
                            <div>📷 点击上传正面照片</div>
                            <div style="font-size:12px;color:#a0aec0;margin-top:4px;">JPG/PNG，建议小于2MB</div>
                            <img id="preview_front" class="upload-preview">
                        </div>
                        <input type="file" id="id_card_front" name="id_card_front" accept="image/*" style="display:none" onchange="previewImg(this,'preview_front')">
                    </div>
                    <div class="form-group">
                        <label class="form-label">身份证背面</label>
                        <div class="upload-area" onclick="document.getElementById('id_card_back').click()">
                            <div>📷 点击上传背面照片</div>
                            <div style="font-size:12px;color:#a0aec0;margin-top:4px;">JPG/PNG，建议小于2MB</div>
                            <img id="preview_back" class="upload-preview">
                        </div>
                        <input type="file" id="id_card_back" name="id_card_back" accept="image/*" style="display:none" onchange="previewImg(this,'preview_back')">
                    </div>
                </div>
            </div>
        </div>

        <!-- 技能信息 -->
        <div class="form-card">
            <div class="form-card-head">🛠️ 技能信息</div>
            <div class="form-card-body">
                <div class="form-grid" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label">技能等级</label>
                        <select name="skill_level" class="form-control">
                            <option value="junior">初级</option>
                            <option value="mid">中级</option>
                            <option value="senior">高级</option>
                            <option value="expert">专家</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">持有证书</label>
                        <input type="text" name="certifications" class="form-control" placeholder="如：华为HCIA、微软MCP等">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">擅长方向（可多选）</label>
                    <div class="specialty-grid" style="margin-top:8px;">
                        <?php
                        $specs = [
                            'desktop'       => '🖥️ 台式机/主板',
                            'laptop'        => '💻 笔记本电脑',
                            'monitor'       => '🖥 显示器维修',
                            'printer'       => '🖨️ 打印机维修',
                            'server_hw'     => '🗄️ 服务器硬件',
                            'network'       => '🌐 网络设备/布线',
                            'server_sw'     => '⚙️ 服务器系统/数据库',
                            'data_recovery' => '💾 数据恢复',
                            'system_sw'     => '🪟 系统软件/重装',
                        ];
                        foreach ($specs as $k => $v): ?>
                        <label class="specialty-item" id="spec_<?=$k?>">
                            <input type="checkbox" name="specialties[]" value="<?=$k?>" onchange="toggleSpec('<?=$k?>')">
                            <?=$v?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 工作信息 -->
        <div class="form-card">
            <div class="form-card-head">💼 工作信息</div>
            <div class="form-card-body">
                <div class="form-grid cols3">
                    <div class="form-group">
                        <label class="form-label">雇佣类型</label>
                        <select name="employment_type" class="form-control" id="employment_type" onchange="autoFillFeeRate(this.value)">
                            <option value="fulltime">全职</option>
                            <option value="parttime">兼职</option>
                            <option value="contractor">外包</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">在职状态</label>
                        <select name="status" class="form-control">
                            <option value="active">在职</option>
                            <option value="inactive">离职</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">入职日期</label>
                        <input type="date" name="join_date" class="form-control">
                    </div>
                </div>
                <div class="form-grid" style="margin-top:16px;">
                    <div class="form-group">
                        <label class="form-label">分成比例（%）
                            <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:4px;">可自定义，难度高的工单可多分</span>
                        </label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <input type="number" name="fee_rate" id="fee_rate" class="form-control"
                                   step="0.01" min="0" max="100" value="5.00"
                                   style="width:120px;font-size:16px;font-weight:600;color:#1f6dad;">
                            <span style="color:#4a5568;font-size:14px;">%</span>
                            <span id="fee_rate_hint" style="font-size:12px;color:#a0aec0;">全职/兼职默认 5%，外包默认 70%</span>
                        </div>
                    </div>
                    <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 16px;font-size:12px;color:#92400e;line-height:1.8;align-self:end;margin-bottom:0;">
                        💡 <strong>内部工程师</strong>：分成基数为<strong>服务费</strong>（不含配件）<br>
                        💡 <strong>外包工程师</strong>：分成基数为<strong>总收入</strong>（含配件，技师自负配件成本）
                    </div>
                </div>
            </div>
        </div>

        <!-- 紧急联系人 -->
        <div class="form-card">
            <div class="form-card-head">🆘 紧急联系人</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">紧急联系人</label>
                        <input type="text" name="emergency_contact" class="form-control" placeholder="姓名">
                    </div>
                    <div class="form-group">
                        <label class="form-label">紧急联系电话</label>
                        <input type="text" name="emergency_phone" class="form-control" placeholder="手机号">
                    </div>
                </div>
            </div>
        </div>

        <!-- 银行信息 -->
        <div class="form-card">
            <div class="form-card-head">🏦 银行账户（选填）</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">开户行</label>
                        <input type="text" name="bank_name" class="form-control" placeholder="如：中国工商银行深圳南山支行">
                    </div>
                    <div class="form-group">
                        <label class="form-label">银行卡号</label>
                        <input type="text" name="bank_account" class="form-control" placeholder="银行卡号">
                    </div>
                </div>
            </div>
        </div>

        <!-- 备注 -->
        <div class="form-card">
            <div class="form-card-head">📝 备注</div>
            <div class="form-card-body">
                <textarea name="notes" class="form-control" placeholder="其他备注信息..."></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit">💾 保存工程师信息</button>
            <a href="technicians.php" class="btn-cancel">取消</a>
        </div>
    </form>
</main>
<script>
function autoFillFeeRate(empType) {
    const input = document.getElementById('fee_rate');
    const hint  = document.getElementById('fee_rate_hint');
    if (empType === 'contractor') {
        input.value = '70.00';
        input.style.color = '#e67e22';
        hint.textContent = '外包工程师默认 70%，可修改';
    } else {
        input.value = '5.00';
        input.style.color = '#1f6dad';
        hint.textContent = '全职/兼职默认 5%，可修改';
    }
}

function previewImg(input, previewId) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById(previewId);
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
}
function toggleSpec(key) {
    const el = document.getElementById('spec_' + key);
    const cb = el.querySelector('input');
    el.classList.toggle('checked', cb.checked);
}
</script>
</body>
</html>
