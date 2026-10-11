<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$nickname = $_SESSION['nickname'] ?? ''; $role = $_SESSION['role'] ?? '';
$conn = getDBConnection();
function e($v){return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8');}

// 保存
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $ct = $_POST['contract_type'] ?? '';
    $allowed = ['sales','construction','outsource','rental','software','loan'];
    if (in_array($ct, $allowed)) {
        $upd = $conn->prepare("UPDATE contract_templates SET
            payment_terms=?, delivery_terms=?, warranty_terms=?,
            breach_terms=?, dispute_terms=?,
            acceptance_terms=?, sla_terms=?
            WHERE contract_type=?");
        $pt = trim($_POST['payment_terms']  ?? '');
        $dt = trim($_POST['delivery_terms'] ?? '');
        $wt = trim($_POST['warranty_terms'] ?? '');
        $bt = trim($_POST['breach_terms']   ?? '');
        $xt = trim($_POST['dispute_terms']  ?? '');
        $at = trim($_POST['acceptance_terms'] ?? '') ?: null;
        $st = trim($_POST['sla_terms']       ?? '') ?: null;
        $upd->bind_param("ssssssss", $pt,$dt,$wt,$bt,$xt,$at,$st,$ct);
        $upd->execute();
        $msg = '✅ 模板已保存';
    }
}

// 读取所有模板
$tpls = [];
$res = $conn->query("SELECT * FROM contract_templates WHERE user_id={$_SESSION['user_id']} ORDER BY id");
while ($r=$res->fetch_assoc()) $tpls[$r['contract_type']] = $r;

$conn->close();
$type_meta = [
    'sales'        => ['💼 销售合同',    ['payment_terms'=>'付款条款','delivery_terms'=>'交付条款','warranty_terms'=>'质保条款','breach_terms'=>'违约责任','dispute_terms'=>'争议解决']],
    'construction' => ['🏗️ 施工合同',   ['payment_terms'=>'付款方式','acceptance_terms'=>'验收标准','warranty_terms'=>'质保条款','breach_terms'=>'违约责任','dispute_terms'=>'争议解决']],
    'outsource'    => ['💻 IT外包合同',  ['payment_terms'=>'付款方式','sla_terms'=>'SLA响应时间','warranty_terms'=>'保密条款','breach_terms'=>'违约责任','dispute_terms'=>'争议解决']],
    'rental'       => ['📋 租赁合同',    ['payment_terms'=>'租金付款方式','delivery_terms'=>'交付与归还','warranty_terms'=>'损坏赔偿条款','breach_terms'=>'违约责任','dispute_terms'=>'争议解决']],
    'software'     => ['💾 软件开发合同', ['payment_terms'=>'付款方式','acceptance_terms'=>'验收标准','warranty_terms'=>'维护与培训条款','breach_terms'=>'违约责任','dispute_terms'=>'争议解决']],
    'loan'         => ['📦 设备借用合同', ['delivery_terms'=>'归还条款','warranty_terms'=>'损坏赔偿条款','breach_terms'=>'违约责任','dispute_terms'=>'争议解决']],
];
?>
<?php $page_title = '合同模板管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.tabs{display:flex;gap:8px;margin-bottom:24px;border-bottom:2px solid #e2e8f0;padding-bottom:0;}
.tab{padding:10px 20px;border:none;background:none;font-size:14px;color:#718096;cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s;}
.tab.active{color:#1f6dad;border-bottom-color:#1f6dad;font-weight:600;}
.tab:hover{color:#1f6dad;}
.tab-panel{display:none;}.tab-panel.active{display:block;}
.card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;margin-bottom:20px;}
.card-head{padding:16px 24px;border-bottom:1px solid #e2e8f0;font-size:15px;font-weight:600;color:#1a202c;}
.card-
.form-label .hint{font-weight:400;color:#a0aec0;font-size:12px;margin-left:6px;}
.form-control{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;transition:border .2s;}
.form-control:focus{outline:none;border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
textarea.form-control{min-height:120px;resize:vertical;line-height:1.6;}
.tip-box{background:#fffbeb;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;font-size:13px;color:#92400e;margin-bottom:20px;line-height:1.6;}
</style></head><body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main-content">
<div style="max-width:1100px;margin:0 auto;">
    <div class="page-header">
        <h1 class="page-title">📄 合同条款模板管理</h1>
        <a href="contracts.php" class="btn-secondary">← 返回合同管理</a>
    </div>

    <?php if(!empty($msg)): ?>
    <div class="alert alert-success"><?=$msg?></div>
    <?php endif; ?>

    <div class="tip-box">
        💡 此处设置的条款为<strong>新建合同时的默认值</strong>，选择合同类型后会自动填入。每份合同创建后可以单独修改，不影响其他合同。
    </div>

    <div class="tabs">
        <?php foreach($type_meta as $ct=>[$label,$fields]): ?>
        <button class="tab <?=$ct==='sales'?'active':''?>" onclick="switchTab('<?=$ct?>')"><?=$label?></button>
        <?php endforeach; ?>
    </div>

    <?php foreach($type_meta as $ct=>[$label,$fields]): ?>
    <div class="tab-panel <?=$ct==='sales'?'active':''?>" id="panel-<?=$ct?>">
        <form method="POST">
            <input type="hidden" name="contract_type" value="<?=$ct?>">
            <div class="card">
                <div class="card-head"><?=$label?> — 默认条款</div>
                <div class="card-body">
                    <?php foreach($fields as $field=>$field_label): ?>
                    <div class="form-group">
                        <label class="form-label"><?=$field_label?>
                            <?php if($field==='acceptance_terms'): ?>
                            <span class="hint">（用于合同第四条验收标准）</span>
                            <?php elseif($field==='sla_terms'): ?>
                            <span class="hint">（用于合同SLA服务级别协议）</span>
                            <?php endif; ?>
                        </label>
                        <textarea name="<?=$field?>" class="form-control"><?=e($tpls[$ct][$field]??'')?></textarea>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">💾 保存<?=$label?>默认条款</button>
                    <span style="font-size:13px;color:#a0aec0;margin-left:8px;">
                        最后更新：<?=e($tpls[$ct]['updated_at']??'从未')?>
                    </span>
                </div>
            </div>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<script>
function switchTab(ct) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelector(`button[onclick="switchTab('${ct}')"]`).classList.add('active');
    document.getElementById('panel-'+ct).classList.add('active');
}
</script>
</div>
</body></html>
