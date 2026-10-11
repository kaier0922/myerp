<?php
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role']     ?? '';

// 保存默认分成设置
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_fee_rates'])) {
    $rate_internal   = floatval($_POST['tech_fee_rate_internal']   ?? 5);
    $rate_contractor = floatval($_POST['tech_fee_rate_contractor'] ?? 70);
    $stmt_r = $conn->prepare("INSERT INTO system_settings (user_id, setting_key, setting_value)
        VALUES (?, 'tech_fee_rate_internal', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt_r->bind_param("is", $_SESSION['user_id'], $rate_internal);
    $stmt_r->execute();
    $stmt_r = $conn->prepare("INSERT INTO system_settings (user_id, setting_key, setting_value)
        VALUES (?, 'tech_fee_rate_contractor', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt_r->bind_param("is", $_SESSION['user_id'], $rate_contractor);
    $stmt_r->execute();
    $fee_saved = true;
}

// 读取分成设置
$fee_settings = [];
$fs = $conn->query("SELECT setting_key, setting_value FROM system_settings
    WHERE user_id = {$_SESSION['user_id']}
    AND setting_key IN ('tech_fee_rate_internal','tech_fee_rate_contractor')");
if ($fs) { while ($fr = $fs->fetch_assoc()) $fee_settings[$fr['setting_key']] = $fr['setting_value']; }
$rate_internal   = $fee_settings['tech_fee_rate_internal']   ?? '5.00';
$rate_contractor = $fee_settings['tech_fee_rate_contractor'] ?? '70.00';

$kw     = trim($_GET['kw'] ?? '');
$status = $_GET['status'] ?? '';
$skill  = $_GET['skill_level'] ?? '';

$where = "WHERE " . tenantWhere();
$params = [];
$types  = "";

if ($kw) {
    $where .= " AND (name LIKE ? OR phone LIKE ? OR wechat LIKE ?)";
    $kw2 = "%$kw%";
    $params = array_merge($params, [$kw2, $kw2, $kw2]);
    $types .= "sss";
}
if ($status) { $where .= " AND status=?"; $params[]=$status; $types.="s"; }
if ($skill)  { $where .= " AND skill_level=?"; $params[]=$skill; $types.="s"; }

$stmt = $conn->prepare("SELECT * FROM technicians $where ORDER BY status ASC, created_at DESC");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total  = count($list);
$active = count(array_filter($list, fn($t) => $t['status']==='active'));

$skill_map = [
    'junior' => ['label'=>'初级','color'=>'#6b7280','bg'=>'#f3f4f6'],
    'mid'    => ['label'=>'中级','color'=>'#3b82f6','bg'=>'#eff6ff'],
    'senior' => ['label'=>'高级','color'=>'#8b5cf6','bg'=>'#f5f3ff'],
    'expert' => ['label'=>'专家','color'=>'#d97706','bg'=>'#fffbeb'],
];
$emp_map = ['fulltime'=>'全职','parttime'=>'兼职','contractor'=>'外包'];
$specialty_map = [
    'desktop'       => '🖥️ 台式机/主板',
    'laptop'        => '💻 笔记本',
    'monitor'       => '🖥 显示器',
    'printer'       => '🖨️ 打印机',
    'server_hw'     => '🗄️ 服务器硬件',
    'network'       => '🌐 网络设备',
    'server_sw'     => '⚙️ 服务器系统',
    'data_recovery' => '💾 数据恢复',
    'system_sw'     => '🪟 系统软件',
];
function e($v){ return htmlspecialchars($v??'',ENT_QUOTES,'UTF-8'); }
$conn->close();
?>
<?php $page_title = '工程师管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
    <link rel="stylesheet" href="common.css">
<style>
.menu-item:hover,
.menu-sub{background:#fafbff;}
.menu-sub
.menu-sub
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:24px;}
.stat-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
.stat-label{font-size:13px;color:#718096;margin-bottom:8px;}
.stat-value{font-size:28px;font-weight:700;color:#1a202c;}
.stat-value.green{color:#135286;}
.stat-value.gray{color:#6b7280;}
.filter-row{display:flex;align-items:center;gap:10px;background:white;border-radius:12px;padding:14px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:20px;flex-wrap:wrap;}
.filter-row input,.filter-row select{padding:8px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;}
.filter-row input{width:240px;}
.filter-row input:focus,.filter-row select:focus{outline:none;border-color:#1f6dad;}
.btn-search{padding:8px 18px;background:#1f6dad;color:white;border:none;border-radius:8px;font-size:14px;cursor:pointer;font-family:inherit;}
.btn-clear{padding:8px 14px;color:#4a5568;text-decoration:none;font-size:14px;}
thead{background:#f7fafc;}
tbody
tbody tr:last-child
.name-cell{display:flex;align-items:center;gap:12px;}
.avatar{width:40px;height:40px;background:linear-gradient(135deg,#1f6dad,#135286);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:600;font-size:16px;flex-shrink:0;}
.avatar.inactive{background:linear-gradient(135deg,#a0aec0,#718096);}
.tech-name{font-weight:600;color:#1a202c;margin-bottom:2px;}
.tech-sub{font-size:12px;color:#718096;}
.badge-green{background:#d1fae5;color:#065f46;}
.badge-gray{background:#f3f4f6;color:#374151;}
.spec-tags{display:flex;flex-wrap:wrap;gap:4px;}
.spec-tag{display:inline-block;padding:2px 8px;background:#eef2ff;color:#4338ca;border-radius:10px;font-size:11px;}
.status-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:5px;}
.btn-sm-red{padding:5px 12px;background:#fff5f5;color:#c53030;border:1px solid #feb2b2;border-radius:6px;font-size:13px;cursor:pointer;}
.btn-sm-red:hover{background:#fed7d7;}
.btn-group{display:flex;gap:6px;}
.empty-icon{font-size:48px;margin-bottom:12px;}
</style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title">👨‍🔧 工程师管理</h1>
    </div>

    <?php if (isset($fee_saved)): ?>
    <div style="background:#d1fae5;border:1px solid #1f6dad;color:#065f46;padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;">✅ 默认分成比例已保存</div>
    <?php endif; ?>

    <!-- 默认分成设置 -->
    <div style="background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:24px;overflow:hidden;">
        <div style="padding:16px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:center;gap:10px;">
            <span style="font-size:18px;">💰</span>
            <div>
                <div style="font-size:15px;font-weight:600;color:#1a202c;">默认分成比例设置</div>
                <div style="font-size:12px;color:#a0aec0;margin-top:2px;">新增工程师时自动填入，可在工程师档案中单独修改</div>
            </div>
        </div>
        <div style="padding:20px 24px;">
            <form method="POST" style="display:flex;align-items:flex-end;gap:24px;flex-wrap:wrap;">
                <input type="hidden" name="save_fee_rates" value="1">
                <div>
                    <div style="font-size:13px;font-weight:600;color:#4a5568;margin-bottom:6px;">
                        🏢 内部工程师（全职/兼职）
                        <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:4px;">分成基数：服务费</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" name="tech_fee_rate_internal" value="<?=htmlspecialchars($rate_internal)?>"
                               step="0.01" min="0" max="100"
                               style="width:100px;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:15px;font-weight:600;color:#1f6dad;">
                        <span style="font-size:15px;color:#4a5568;">%</span>
                    </div>
                </div>
                <div style="width:1px;height:50px;background:#e2e8f0;"></div>
                <div>
                    <div style="font-size:13px;font-weight:600;color:#4a5568;margin-bottom:6px;">
                        🔧 外包工程师（外包/支援）
                        <span style="font-size:11px;color:#a0aec0;font-weight:400;margin-left:4px;">分成基数：总收入（含配件）</span>
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <input type="number" name="tech_fee_rate_contractor" value="<?=htmlspecialchars($rate_contractor)?>"
                               step="0.01" min="0" max="100"
                               style="width:100px;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:15px;font-weight:600;color:#e67e22;">
                        <span style="font-size:15px;color:#4a5568;">%</span>
                    </div>
                </div>
                <div style="width:1px;height:50px;background:#e2e8f0;"></div>
                <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:12px;color:#92400e;line-height:1.6;max-width:220px;">
                    💡 内部工程师建议 <strong>3%~10%</strong><br>
                    外包工程师建议 <strong>60%~80%</strong>
                </div>
                <button type="submit"
                        style="padding:10px 24px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;">
                    💾 保存设置
                </button>
            </form>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label">工程师总数</div><div class="stat-value"><?=$total?></div></div>
        <div class="stat-card"><div class="stat-label">在职工程师</div><div class="stat-value green"><?=$active?></div></div>
        <div class="stat-card"><div class="stat-label">离职/停用</div><div class="stat-value gray"><?=$total-$active?></div></div>
    </div>

    <form class="filter-row" method="GET">
        <input type="text" name="kw" placeholder="🔍 姓名、手机号、微信号..." value="<?=e($kw)?>">
        <select name="status">
            <option value="">全部状态</option>
            <option value="active"   <?=$status==='active'  ?'selected':''?>>在职</option>
            <option value="inactive" <?=$status==='inactive'?'selected':''?>>离职</option>
        </select>
        <select name="skill_level">
            <option value="">全部等级</option>
            <option value="junior" <?=$skill==='junior'?'selected':''?>>初级</option>
            <option value="mid"    <?=$skill==='mid'   ?'selected':''?>>中级</option>
            <option value="senior" <?=$skill==='senior'?'selected':''?>>高级</option>
            <option value="expert" <?=$skill==='expert'?'selected':''?>>专家</option>
        </select>
        <button type="submit" class="btn-search">搜索</button>
        <?php if ($kw||$status||$skill): ?>
        <a href="technicians.php" class="btn-clear">✕ 清除</a>
        <?php endif; ?>
        <a href="technician_add.php" class="btn-primary">+ 添加工程师</a>
    </form>

    <div class="content-card">
        <?php if (!empty($list)): ?>
        <table>
            <thead>
                <tr>
                    <th>工程师信息</th>
                    <th>联系方式</th>
                    <th>技能等级</th>
                    <th>擅长方向</th>
                    <th>类型</th>
                    <th>入职日期</th>
                    <th>状态</th>
                    <th style="text-align:center;">操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($list as $t):
                $sp = json_decode($t['specialties']??'[]', true) ?: [];
                $sk = $skill_map[$t['skill_level']] ?? ['label'=>$t['skill_level'],'color'=>'#6b7280','bg'=>'#f3f4f6'];
            ?>
            <tr>
                <td>
                    <div class="name-cell">
                        <div class="avatar <?=$t['status']==='inactive'?'inactive':''?>"><?=mb_substr($t['name'],0,1)?></div>
                        <div>
                            <div class="tech-name"><?=e($t['name'])?></div>
                            <div class="tech-sub"><?=$t['gender']==='male'?'男':'女'?> <?=e($t['service_area']??'')?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div style="font-size:13px;">📱 <?=e($t['phone'])?></div>
                    <?php if ($t['wechat']): ?><div style="font-size:12px;color:#718096;">💬 <?=e($t['wechat'])?></div><?php endif; ?>
                </td>
                <td>
                    <span class="badge" style="background:<?=$sk['bg']?>;color:<?=$sk['color']?>;"><?=$sk['label']?></span>
                </td>
                <td>
                    <div class="spec-tags">
                        <?php foreach ($sp as $s): ?>
                        <span class="spec-tag"><?=$specialty_map[$s]??$s?></span>
                        <?php endforeach; ?>
                        <?php if (empty($sp)): ?><span style="color:#a0aec0;font-size:12px;">未填写</span><?php endif; ?>
                    </div>
                </td>
                <td><?=$emp_map[$t['employment_type']]??'-'?></td>
                <td style="font-size:13px;color:#718096;"><?=e($t['join_date']??'-')?></td>
                <td>
                    <?php if ($t['status']==='active'): ?>
                    <span class="badge badge-green"><span class="status-dot" style="background:#135286;"></span>在职</span>
                    <?php else: ?>
                    <span class="badge badge-gray"><span class="status-dot" style="background:#9ca3af;"></span>离职</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="btn-group" style="justify-content:center;">
                        <a href="technician_edit.php?id=<?=$t['id']?>" class="btn-sm">✏️ 编辑</a>
                        <button class="btn-sm-red" onclick="deleteTech(<?=$t['id']?>,'<?=e($t['name'])?>')">🗑️ 删除</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-state">
            <div class="empty-icon">👨‍🔧</div>
            <div>暂无工程师数据，<a href="technician_add.php" style="color:#1f6dad;">立即添加</a></div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
function deleteTech(id, name) {
    const btn = event.currentTarget || event.target;
    const orig = btn.textContent;
    sapConfirm('确定删除工程师「<strong>' + name + '</strong>」？<br><br>此操作不可恢复！',
    function() {
        btn.disabled = true; btn.textContent = '⏳';
        fetch('technician_delete.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'id=' + id
        }).then(r => r.json()).then(d => {
            if (d.success) { sapToast('工程师已删除', 'success'); setTimeout(()=>location.reload(), 1200); }
            else { sapToast('删除失败：' + (d.error || '未知错误'), 'error'); btn.disabled=false; btn.textContent=orig; }
        }).catch(e => { sapToast('删除出错：' + e.message, 'error'); btn.disabled=false; btn.textContent=orig; });
    }, {danger:true, title:'删除工程师', okText:'确认删除'});
}
</script>

<!-- SAP Toast + Confirm -->
<div id="sapToast" style="display:none;position:fixed;top:24px;right:24px;z-index:9999;
  min-width:260px;max-width:400px;padding:13px 16px;border-radius:8px;
  box-shadow:0 4px 20px rgba(0,0,0,0.18);font-size:14px;color:white;
  align-items:flex-start;gap:10px;transition:opacity .3s;">
  <span id="sapToastIcon" style="font-size:16px;flex-shrink:0;"></span>
  <span id="sapToastMsg"  style="line-height:1.5;flex:1;word-break:break-all;"></span>
</div>
<div id="sapConfirmOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:9000;"></div>
<div id="sapConfirmBox" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);
  width:400px;max-width:92vw;background:white;border-radius:10px;
  box-shadow:0 8px 32px rgba(0,0,0,0.18);z-index:9001;overflow:hidden;">
  <div style="background:linear-gradient(135deg,#1f6dad,#135286);padding:15px 20px;display:flex;align-items:center;gap:10px;">
    <span id="sapConfirmIcon"  style="font-size:18px;">⚠️</span>
    <span id="sapConfirmTitle" style="font-size:15px;font-weight:600;color:white;">确认操作</span>
  </div>
  <div style="padding:22px 20px;">
    <p id="sapConfirmMsg" style="font-size:14px;color:#2d3748;line-height:1.7;margin:0;"></p>
  </div>
  <div style="padding:0 20px 18px;display:flex;justify-content:flex-end;gap:10px;">
    <button id="sapConfirmCancel"
      style="padding:8px 20px;border:1px solid #e2e8f0;border-radius:7px;background:#f7fafc;
             color:#4a5568;font-size:14px;cursor:pointer;"
      onmouseover="this.style.background='#edf2f7'" onmouseout="this.style.background='#f7fafc'">取消</button>
    <button id="sapConfirmOk"
      style="padding:8px 20px;border:none;border-radius:7px;
             background:linear-gradient(135deg,#1f6dad,#135286);
             color:white;font-size:14px;cursor:pointer;"
      onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">确认</button>
  </div>
</div>
<script>
// 检测 URL 参数显示操作结果
(function(){
    const p = new URLSearchParams(location.search);
    if (p.get('updated') === '1') {
        document.addEventListener('DOMContentLoaded', function(){
            sapToast('工程师信息已保存', 'success');
            history.replaceState({}, '', 'technicians.php');
        });
    }
    if (p.get('added') === '1') {
        document.addEventListener('DOMContentLoaded', function(){
            sapToast('工程师已添加成功', 'success');
            history.replaceState({}, '', 'technicians.php');
        });
    }
})();

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
function sapConfirm(msg,onOk,opts){
    opts=opts||{};
    document.getElementById('sapConfirmMsg').innerHTML=msg.replace(/\n/g,'<br>');
    document.getElementById('sapConfirmTitle').textContent=opts.title||'确认操作';
    document.getElementById('sapConfirmIcon').textContent=opts.icon||'⚠️';
    document.getElementById('sapConfirmOk').textContent=opts.okText||'确认';
    document.getElementById('sapConfirmOk').style.background=
        opts.danger?'linear-gradient(135deg,#ef4444,#dc2626)':'linear-gradient(135deg,#1f6dad,#135286)';
    document.getElementById('sapConfirmOverlay').style.display='block';
    document.getElementById('sapConfirmBox').style.display='block';
    var ok=document.getElementById('sapConfirmOk');
    var cancel=document.getElementById('sapConfirmCancel');
    var close=function(){
        document.getElementById('sapConfirmOverlay').style.display='none';
        document.getElementById('sapConfirmBox').style.display='none';
        ok.onclick=null;cancel.onclick=null;};
    ok.onclick=function(){close();onOk();};
    cancel.onclick=function(){close();};
}
</script>

</body>
</html>
