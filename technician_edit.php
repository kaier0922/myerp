<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/log_helper.php';
require_once __DIR__ . '/upload_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';
$id       = intval($_GET['id'] ?? 0);
$error    = '';

$stmt = $conn->prepare("SELECT * FROM technicians WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
$stmt->bind_param("i", $id);
$stmt->execute();
$t = $stmt->get_result()->fetch_assoc();
if (!$t) { header('Location: technicians.php'); exit; }

$cur_specs = json_decode($t['specialties'] ?? '[]', true) ?: [];

function uploadIdCard($file_key) {
    if (empty($_FILES[$file_key]['name'])) return null;
    $f   = $_FILES[$file_key];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) return null;
    $dir = __DIR__ . '/uploads/idcards/';
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

    $new_front = uploadIdCard('id_card_front');
    $new_back  = uploadIdCard('id_card_back');
    $id_card_front = $new_front ?: $t['id_card_front'];
    $id_card_back  = $new_back  ?: $t['id_card_back'];

    if (!$name) { $error = '姓名不能为空'; }
    elseif (!$phone) { $error = '手机号不能为空'; }
    else {
        $stmt2 = $conn->prepare("UPDATE technicians SET
            name=?,gender=?,phone=?,wechat=?,id_card_no=?,id_card_front=?,id_card_back=?,
            specialties=?,skill_level=?,certifications=?,service_area=?,employment_type=?,
            fee_rate=?,status=?,join_date=?,leave_date=?,emergency_contact=?,emergency_phone=?,
            bank_name=?,bank_account=?,notes=?
            WHERE id=? AND user_id=" . intval($_SESSION['user_id']));
        $stmt2->bind_param("ssssssssssssdssssssssi",
            $name,$gender,$phone,$wechat,$id_card_no,$id_card_front,$id_card_back,
            $specialties,$skill_level,$certifications,$service_area,$employment_type,
            $fee_rate,$status,$join_date,$leave_date,$emergency_contact,$emergency_phone,
            $bank_name,$bank_account,$notes,$id);
        if ($stmt2->execute()) {
            writeLog($conn, 'technicians', 'update', '修改技师');
            header('Location: technicians.php?updated=1');
            exit;
        } else {
            $error = '保存失败：' . $conn->error;
        }
    }
}

function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
$conn->close();
?>
<?php $page_title = '编辑工程师 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.menu-sub
.back-btn{padding:8px 16px;background:white;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:14px;text-decoration:none;}
.form-card-head{background:linear-gradient(135deg,#1f6dad,#135286);padding:14px 24px;color:white;font-size:15px;font-weight:600;}
.form-card-
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
.form-grid.cols3{grid-template-columns:1fr 1fr 1fr;}.form-grid.cols4{grid-template-columns:1fr 1fr 1fr 1fr;}
.form-label .req{color:#e53e3e;margin-left:2px;}
.form-control{padding:9px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
textarea.form-control{resize:vertical;min-height:80px;width:100%;rows:3;}
.specialty-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}
.specialty-item{display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;cursor:pointer;transition:all .2s;font-size:13px;}
.specialty-item:hover{border-color:#1f6dad;background:#f0f0ff;}
.specialty-item.checked{border-color:#1f6dad;background:#eef2ff;color:#4338ca;font-weight:500;}
.upload-area{border:2px dashed #e2e8f0;border-radius:8px;padding:16px;text-align:center;cursor:pointer;transition:all .2s;}
.upload-area:hover{border-color:#1f6dad;background:#f0f0ff;}
.id-preview{max-width:200px;max-height:130px;border-radius:6px;margin-top:8px;border:1px solid #e2e8f0;}
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
        <a href="technicians.php" class="back-btn">← 返回</a>
        <h1 class="page-title">✏️ 编辑工程师：<?=e($t['name'])?></h1>
    </div>

    <?php if ($error): ?><div class="alert-error">❌ <?=e($error)?></div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-card">
            <div class="form-card-head">👤 基本信息</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">姓名<span class="req">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?=e($t['name'])?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">性别</label>
                        <select name="gender" class="form-control">
                            <option value="male"   <?=$t['gender']==='male'  ?'selected':''?>>男</option>
                            <option value="female" <?=$t['gender']==='female'?'selected':''?>>女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">手机号<span class="req">*</span></label>
                        <input type="text" name="phone" class="form-control" value="<?=e($t['phone'])?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">微信号</label>
                        <input type="text" name="wechat" class="form-control" value="<?=e($t['wechat'])?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">身份证号</label>
                        <input type="text" name="id_card_no" class="form-control" value="<?=e($t['id_card_no'])?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">负责区域</label>
                        <input type="text" name="service_area" class="form-control" value="<?=e($t['service_area'])?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-head">🪪 身份证扫描</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">身份证正面</label>
                        <?php if ($t['id_card_front']): ?>
                        <img src="<?=e($t['id_card_front'])?>" class="id-preview" onerror="this.style.display='none'">
                        <div style="font-size:12px;color:#a0aec0;margin:4px 0;">重新上传将覆盖原图</div>
                        <?php endif; ?>
                        <div class="upload-area" onclick="document.getElementById('id_card_front').click()">
                            📷 <?=$t['id_card_front']?'重新上传正面':'上传正面照片'?>
                        </div>
                        <input type="file" id="id_card_front" name="id_card_front" accept="image/*" style="display:none" onchange="previewImg(this,'pf')">
                        <img id="pf" style="display:none;max-width:200px;max-height:130px;border-radius:6px;margin-top:6px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">身份证背面</label>
                        <?php if ($t['id_card_back']): ?>
                        <img src="<?=e($t['id_card_back'])?>" class="id-preview" onerror="this.style.display='none'">
                        <div style="font-size:12px;color:#a0aec0;margin:4px 0;">重新上传将覆盖原图</div>
                        <?php endif; ?>
                        <div class="upload-area" onclick="document.getElementById('id_card_back').click()">
                            📷 <?=$t['id_card_back']?'重新上传背面':'上传背面照片'?>
                        </div>
                        <input type="file" id="id_card_back" name="id_card_back" accept="image/*" style="display:none" onchange="previewImg(this,'pb')">
                        <img id="pb" style="display:none;max-width:200px;max-height:130px;border-radius:6px;margin-top:6px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-head">🛠️ 技能信息</div>
            <div class="form-card-body">
                <div class="form-grid" style="margin-bottom:18px;">
                    <div class="form-group">
                        <label class="form-label">技能等级</label>
                        <select name="skill_level" class="form-control">
                            <?php foreach(['junior'=>'初级','mid'=>'中级','senior'=>'高级','expert'=>'专家'] as $k=>$v): ?>
                            <option value="<?=$k?>" <?=$t['skill_level']===$k?'selected':''?>><?=$v?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">持有证书</label>
                        <input type="text" name="certifications" class="form-control" value="<?=e($t['certifications'])?>">
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
                        foreach ($specs as $k => $v):
                            $checked = in_array($k, $cur_specs);
                        ?>
                        <label class="specialty-item <?=$checked?'checked':''?>" id="spec_<?=$k?>">
                            <input type="checkbox" name="specialties[]" value="<?=$k?>" <?=$checked?'checked':''?> onchange="toggleSpec('<?=$k?>')">
                            <?=$v?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-head">💼 工作信息</div>
            <div class="form-card-body">
                <div class="form-grid cols4">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">雇佣类型 & 分成比例
                            <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:4px;">可自定义，难度高的工单可多分</span>
                        </label>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <select name="employment_type" class="form-control" id="employment_type" onchange="autoFillFeeRate(this.value)" style="width:120px;">
                                <?php foreach(['fulltime'=>'全职','parttime'=>'兼职','contractor'=>'外包'] as $k=>$v): ?>
                                <option value="<?=$k?>" <?=$t['employment_type']===$k?'selected':''?>><?=$v?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="fee_rate" id="fee_rate" class="form-control"
                                   step="0.01" min="0" max="100"
                                   value="<?=htmlspecialchars($t['fee_rate']??'5.00')?>"
                                   style="width:100px;font-size:16px;font-weight:600;color:<?=$t['employment_type']==='contractor'?'#e67e22':'#1f6dad'?>;">
                            <span style="color:#4a5568;font-size:14px;">%</span>
                            <span id="fee_rate_hint" style="font-size:12px;color:#a0aec0;">
                                <?=$t['employment_type']==='contractor'?'外包工程师分成基数：总收入（含配件）':'内部工程师分成基数：服务费（不含配件）'?>
                            </span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">在职状态</label>
                        <select name="status" class="form-control">
                            <option value="active"   <?=$t['status']==='active'  ?'selected':''?>>在职</option>
                            <option value="inactive" <?=$t['status']==='inactive'?'selected':''?>>离职</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">入职日期</label>
                        <input type="date" name="join_date" class="form-control" value="<?=e($t['join_date'])?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">离职日期</label>
                        <input type="date" name="leave_date" class="form-control" value="<?=e($t['leave_date'])?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-head">🆘 紧急联系人</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">紧急联系人</label>
                        <input type="text" name="emergency_contact" class="form-control" value="<?=e($t['emergency_contact'])?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">紧急联系电话</label>
                        <input type="text" name="emergency_phone" class="form-control" value="<?=e($t['emergency_phone'])?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-head">🏦 银行账户</div>
            <div class="form-card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">开户行</label>
                        <input type="text" name="bank_name" class="form-control" value="<?=e($t['bank_name'])?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">银行卡号</label>
                        <input type="text" name="bank_account" class="form-control" value="<?=e($t['bank_account'])?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-head">📝 备注</div>
            <div class="form-card-body">
                <textarea name="notes" class="form-control" rows="3" style="width:100%;min-height:72px;"><?=e($t['notes'])?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn-submit">💾 保存修改</button>
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
        hint.textContent = '外包工程师分成基数：总收入（含配件）';
    } else {
        input.value = '5.00';
        input.style.color = '#1f6dad';
        hint.textContent = '内部工程师分成基数：服务费（不含配件）';
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

<!-- SAP Toast -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:260px;max-width:400px;padding:13px 16px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:16px;flex-shrink:0;"></span>
  <span id="sapToastMsg"  style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<script>
function sapToast(msg,type,dur){
    type=type||'info';dur=dur||3000;
    var C={success:'#16a34a',error:'#dc2626',warning:'#d97706',info:'#1f6dad'};
    var I={success:'✅',error:'❌',warning:'⚠️',info:'ℹ️'};
    var el=document.getElementById('sapToast');
    if(!el)return;
    document.getElementById('sapToastIcon').textContent=I[type]||'ℹ️';
    document.getElementById('sapToastMsg').textContent=msg;
    el.style.background=C[type]||'#1f6dad';
    el.style.display='flex';el.style.opacity='1';
    clearTimeout(el._t);
    el._t=setTimeout(function(){el.style.opacity='0';
        setTimeout(function(){el.style.display='none';},300);},dur);
}
<?php if ($error): ?>
document.addEventListener('DOMContentLoaded', function(){
    sapToast('<?=addslashes($error)?>', 'error', 5000);
});
<?php endif; ?>
</script>

</body>
</html>
