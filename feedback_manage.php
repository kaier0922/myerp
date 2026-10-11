<?php header('Location: index.php'); exit; ?>
<?php
/**
 * 文件名: feedback_manage.php
 * 功能: 管理员查看全部工单、回复、改状态
 */
session_start();
require_once 'config.php';
require_once 'auth_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$conn     = getDBConnection();
$uid      = intval($_SESSION['user_id']);
$nickname = $_SESSION['nickname'] ?? '管理员';
$role     = $_SESSION['role'] ?? '';

// 仅 admin / super_admin 可访问
if (!in_array($role, ['admin','super_admin'])) {
    header('Location: index.php'); exit;
}
// super_admin 可查所有租户；admin 只看本租户
$is_super   = ($role === 'super_admin');
$my_tenant  = intval($_SESSION['tenant_id'] ?? $uid);

function e($v){ return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

$message = $error = '';

// ── 回复工单 ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'reply') {
    $tid    = intval($_POST['ticket_id']);
    $reply  = trim($_POST['reply'] ?? '');
    $status = $_POST['status'] ?? 'in_progress';
    if ($reply) {
        $tenant_cond = $is_super ? '' : " AND tenant_id=$my_tenant";
        $stmt = $conn->prepare("UPDATE feedback_tickets SET admin_reply=?,replied_by=?,replied_at=NOW(),status=?,updated_at=NOW() WHERE id=?{$tenant_cond}");
        $stmt->bind_param('sssi', $reply, $nickname, $status, $tid);
        $stmt->execute();
        $message = $stmt->affected_rows ? '回复已发送' : '操作失败：工单不存在或无权限';
    } elseif ($_POST['status_only'] ?? '') {
        $tenant_cond = $is_super ? '' : " AND tenant_id=$my_tenant";
        $stmt = $conn->prepare("UPDATE feedback_tickets SET status=?,updated_at=NOW() WHERE id=?{$tenant_cond}");
        $stmt->bind_param('si', $status, $tid);
        $stmt->execute();
        $message = '状态已更新';
    } else {
        $error = '回复内容不能为空';
    }
}

// ── 筛选 ──
$filter_status = $_GET['status'] ?? '';
$filter_cat    = $_GET['category'] ?? '';
$where = ['1=1'];
if ($filter_status) $where[] = "f.status='" . $conn->real_escape_string($filter_status) . "'";
if ($filter_cat)    $where[] = "f.category='" . $conn->real_escape_string($filter_cat) . "'";
$where_sql = implode(' AND ', $where);

// ── 工单详情 ──
$view_ticket = null;
if (isset($_GET['view'])) {
    $tid = intval($_GET['view']);
    $tc = $is_super ? '' : " AND f.tenant_id=$my_tenant";
    $view_ticket = $conn->query("SELECT f.*, u.phone FROM feedback_tickets f LEFT JOIN users u ON f.user_id=u.id WHERE f.id=$tid{$tc}")->fetch_assoc();
}

// ── 工单列表 ──
if (!$is_super) $where[] = "f.tenant_id=$my_tenant";
$where_sql = implode(' AND ', $where);
$res = $conn->query("SELECT f.*, u.phone FROM feedback_tickets f LEFT JOIN users u ON f.user_id=u.id WHERE $where_sql ORDER BY f.created_at DESC LIMIT 200");
if (!$res) { error_log("feedback_manage query error: " . $conn->error); $res_err = $conn->error; }
$tickets = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// ── 统计 ──
$stats = [];
$stats_tenant = $is_super ? '' : " AND tenant_id=$my_tenant";
foreach(['open','in_progress','resolved','closed'] as $s) {
    $r = $conn->query("SELECT COUNT(*) c FROM feedback_tickets WHERE status='$s'{$stats_tenant}");
    $stats[$s] = ($r ? $r->fetch_assoc()['c'] : 0) ?? 0;
}

$conn->close();

$cat_map = ['bug'=>'🐛 BUG','suggestion'=>'💡 建议','question'=>'❓ 咨询','other'=>'📌 其他'];
$pri_map = ['low'=>['低','#a0aec0'],'normal'=>['普通','#1f6dad'],'high'=>['高','#d97706'],'urgent'=>['紧急','#dc2626']];
$sta_map = ['open'=>['待处理','#d97706','#fffbeb'],'in_progress'=>['处理中','#1f6dad','#eff6ff'],'resolved'=>['已解决','#15803d','#f0fdf4'],'closed'=>['已关闭','#a0aec0','#f9fafb']];
?>
<?php $page_title = '工单管理 - 电脑装机工程智能报价系统'; include __DIR__ . '/head.php'; ?>
<style>
.
/* 统计卡片 */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
.stat-card{background:white;border-radius:12px;padding:20px 24px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid;}
.stat-card-num{font-size:28px;font-weight:700;line-height:1;}
.stat-card-label{font-size:13px;color:#718096;margin-top:6px;}
/* 筛选 */
.filter-bar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.filter-btn{padding:7px 16px;border-radius:20px;font-size:13px;border:1px solid #e2e8f0;background:white;color:#4a5568;text-decoration:none;transition:all .2s;cursor:pointer;}
.filter-btn:hover,.filter-btn.active{background:#1f6dad;color:white;border-color:#1f6dad;}
/* 表格 */
.table-card{background:white;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.08);overflow:hidden;}
tr:last-child
tr:hover
.btn-view{padding:6px 14px;background:#1f6dad;color:white;border-radius:6px;font-size:12px;text-decoration:none;font-weight:600;transition:.2s;}
.btn-view:hover{background:#135286;}
/* 详情面板 */
.detail-panel{background:white;border-radius:16px;box-shadow:0 1px 3px rgba(0,0,0,.08);margin-bottom:24px;}
.detail-panel-header{padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;align-items:flex-start;justify-content:space-between;gap:16px;}
.detail-panel-
.desc-box{background:#f7fafc;border-radius:10px;padding:16px;font-size:14px;line-height:1.8;white-space:pre-wrap;color:#2d3748;}
.imgs-row{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;}
.imgs-row img{width:130px;height:100px;object-fit:cover;border-radius:8px;border:1px solid #e2e8f0;cursor:pointer;transition:.2s;}
.imgs-row img:hover{transform:scale(1.05);}
.reply-form{margin-top:24px;border-top:1px solid #e2e8f0;padding-top:20px;}
.reply-form label{display:block;font-size:12px;font-weight:700;color:#4a5568;margin-bottom:8px;}
.reply-form select,.reply-form textarea{width:100%;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;font-size:14px;font-family:inherit;outline:none;transition:.2s;}
.reply-form select:focus,.reply-form textarea:focus{border-color:#1f6dad;box-shadow:0 0 0 3px rgba(31,109,173,.1);}
.reply-form textarea{height:110px;resize:vertical;margin-bottom:12px;}
.form-row2{display:grid;grid-template-columns:200px 1fr;gap:12px;margin-bottom:12px;align-items:end;}
.btn-reply{padding:11px 28px;background:linear-gradient(135deg,#1f6dad,#135286);color:white;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;font-family:inherit;}
.btn-reply:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(31,109,173,.4);}
.existing-reply{background:#f0f9ff;border:1px solid #bae6fd;border-radius:10px;padding:16px;margin-top:16px;}
.existing-reply h4{font-size:13px;font-weight:700;color:#0369a1;margin-bottom:8px;}
.existing-reply p{font-size:14px;color:#0c4a6e;line-height:1.7;white-space:pre-wrap;}
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#f7fafc;border:1px solid #e2e8f0;border-radius:8px;color:#4a5568;font-size:13px;text-decoration:none;margin-bottom:20px;}
.btn-back:hover{background:#edf2f7;}
.alert-error{background:#fff1f2;color:#be123c;border:1px solid #fecdd3;}
#lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:999;align-items:center;justify-content:center;}
#lightbox.show{display:flex;}
#lightbox img{max-width:90vw;max-height:88vh;border-radius:8px;}
#lightbox-close{position:absolute;top:20px;right:20px;color:white;font-size:28px;cursor:pointer;}
</style>
</head>
<body>
<nav class="navbar">
  <div class="navbar-brand"><div class="navbar-brand-icon">🏢</div><span>电脑装机工程智能报价系统</span></div>
  <div class="navbar-user">
    <div class="user-avatar"><?=mb_substr($nickname,0,1)?></div>
    <div><div class="user-name"><?=e($nickname)?></div><div class="user-role"><?=e($role)?></div></div>
    <a href="logout.php" class="btn-logout">退出登录</a>
  </div>
</nav>
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="main">
<div class="page-title">🎫 工单管理</div>
<?php if($message): ?><div class="alert alert-success">✅ <?=e($message)?></div><?php endif; ?>
<?php if($error):   ?><div class="alert alert-error">❌ <?=e($error)?></div><?php endif; ?>

<!-- 统计 -->
<div class="stats-row">
  <div class="stat-card" style="border-color:#d97706;">
    <div class="stat-card-num" style="color:#d97706;"><?=$stats['open']?></div>
    <div class="stat-card-label">待处理</div>
  </div>
  <div class="stat-card" style="border-color:#1f6dad;">
    <div class="stat-card-num" style="color:#1f6dad;"><?=$stats['in_progress']?></div>
    <div class="stat-card-label">处理中</div>
  </div>
  <div class="stat-card" style="border-color:#15803d;">
    <div class="stat-card-num" style="color:#15803d;"><?=$stats['resolved']?></div>
    <div class="stat-card-label">已解决</div>
  </div>
  <div class="stat-card" style="border-color:#a0aec0;">
    <div class="stat-card-num" style="color:#a0aec0;"><?=$stats['closed']?></div>
    <div class="stat-card-label">已关闭</div>
  </div>
</div>

<?php if($view_ticket): ?>
<!-- 详情面板 -->
<a href="feedback_manage.php" class="btn-back">← 返回工单列表</a>
<div class="detail-panel">
  <div class="detail-panel-header">
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
        <span style="font-size:13px;color:#a0aec0;font-family:monospace;"><?=e($view_ticket['ticket_no'])?></span>
        <?php $s=$sta_map[$view_ticket['status']]; ?>
        <span class="badge" style="background:<?=$s[2]?>;color:<?=$s[1]?>;"><?=$s[0]?></span>
        <?php $p=$pri_map[$view_ticket['priority']]; ?>
        <span style="font-size:12px;color:<?=$p[1]?>;font-weight:700;"><?=$p[0]?>优先</span>
        <span style="font-size:12px;color:#a0aec0;"><?=e($cat_map[$view_ticket['category']])?></span>
      </div>
      <h2 style="font-size:18px;font-weight:700;"><?=e($view_ticket['title'])?></h2>
      <p style="font-size:12px;color:#a0aec0;margin-top:6px;">
        提交人：<?=e($view_ticket['user_name'])?> <?php if($view_ticket['phone']): ?>(<?=e($view_ticket['phone'])?>)<?php endif; ?>
        &nbsp;·&nbsp; <?=substr($view_ticket['created_at'],0,16)?>
      </p>
    </div>
  </div>
  <div class="detail-panel-body">
    <?php if($view_ticket['description']): ?>
    <h4 style="font-size:13px;font-weight:700;color:#4a5568;margin-bottom:10px;">问题描述</h4>
    <div class="desc-box"><?=e($view_ticket['description'])?></div>
    <?php endif; ?>
    <?php
      $imgs = json_decode($view_ticket['images'] ?? '[]', true);
      if(!empty($imgs)):
    ?>
    <div class="imgs-row">
      <?php foreach($imgs as $img): ?>
      <img src="<?=e($img)?>" onclick="showLightbox(this.src)" title="点击放大">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if($view_ticket['admin_reply']): ?>
    <div class="existing-reply">
      <h4>当前回复 · <?=e($view_ticket['replied_by'])?> · <?=substr($view_ticket['replied_at']??'',0,16)?></h4>
      <p><?=e($view_ticket['admin_reply'])?></p>
    </div>
    <?php endif; ?>

    <div class="reply-form">
      <form method="POST">
        <input type="hidden" name="act" value="reply">
        <input type="hidden" name="ticket_id" value="<?=$view_ticket['id']?>">
        <label>回复内容</label>
        <textarea name="reply" placeholder="输入回复内容，将显示给用户..."></textarea>
        <div class="form-row2">
          <div>
            <label>更新状态</label>
            <select name="status">
              <option value="open" <?=$view_ticket['status']==='open'?'selected':''?>>待处理</option>
              <option value="in_progress" <?=$view_ticket['status']==='in_progress'?'selected':''?>>处理中</option>
              <option value="resolved" <?=$view_ticket['status']==='resolved'?'selected':''?>>已解决</option>
              <option value="closed" <?=$view_ticket['status']==='closed'?'selected':''?>>已关闭</option>
            </select>
          </div>
          <div style="display:flex;gap:10px;align-items:flex-end;">
            <button type="submit" class="btn-reply">💬 发送回复</button>
            <button type="submit" name="status_only" value="1" class="btn-reply" style="background:linear-gradient(135deg,#4a5568,#2d3748);">仅改状态</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php else: ?>
<!-- 筛选 + 列表 -->
<div class="filter-bar">
  <a href="feedback_manage.php" class="filter-btn <?=!$filter_status?'active':''?>">全部 (<?=array_sum($stats)?>)</a>
  <a href="?status=open" class="filter-btn <?=$filter_status==='open'?'active':''?>">待处理 (<?=$stats['open']?>)</a>
  <a href="?status=in_progress" class="filter-btn <?=$filter_status==='in_progress'?'active':''?>">处理中 (<?=$stats['in_progress']?>)</a>
  <a href="?status=resolved" class="filter-btn <?=$filter_status==='resolved'?'active':''?>">已解决 (<?=$stats['resolved']?>)</a>
  <a href="?status=closed" class="filter-btn <?=$filter_status==='closed'?'active':''?>">已关闭 (<?=$stats['closed']?>)</a>
  <a href="?category=bug" class="filter-btn <?=$filter_cat==='bug'?'active':''?>">🐛 BUG</a>
  <a href="?category=suggestion" class="filter-btn <?=$filter_cat==='suggestion'?'active':''?>">💡 建议</a>
</div>
<div class="table-card">
  <table>
    <thead><tr>
      <th>工单号</th><th>标题</th><th>类型</th><th>优先级</th><th>状态</th><th>提交人</th><th>提交时间</th><th>操作</th>
    </tr></thead>
    <tbody>
    <?php if(empty($tickets)): ?>
    <tr><td colspan="8" style="text-align:center;padding:40px;color:#a0aec0;">暂无工单</td></tr>
    <?php else: foreach($tickets as $t):
      $s=$sta_map[$t['status']]; $p=$pri_map[$t['priority']];
    ?>
    <tr>
      <td style="font-family:monospace;font-size:12px;color:#718096;"><?=e($t['ticket_no'])?></td>
      <td>
        <div style="font-weight:600;color:#1a202c;"><?=e(mb_substr($t['title'],0,30))?><?=mb_strlen($t['title'])>30?'…':''?></div>
        <?php if($t['admin_reply']): ?><div style="font-size:11px;color:#15803d;">💬 已回复</div><?php endif; ?>
      </td>
      <td><?=e($cat_map[$t['category']]??$t['category'])?></td>
      <td><span style="color:<?=$p[1]?>;font-weight:700;font-size:12px;"><?=$p[0]?></span></td>
      <td><span class="badge" style="background:<?=$s[2]?>;color:<?=$s[1]?>;"><?=$s[0]?></span></td>
      <td>
        <div style="font-size:13px;"><?=e($t['user_name'])?></div>
        <?php if($t['phone']): ?><div style="font-size:11px;color:#a0aec0;"><?=e($t['phone'])?></div><?php endif; ?>
      </td>
      <td style="font-size:12px;color:#718096;"><?=substr($t['created_at'],0,16)?></td>
      <td><a href="?view=<?=$t['id']?>" class="btn-view">查看回复</a></td>
    </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
</div>

<div id="lightbox" onclick="document.getElementById('lightbox').classList.remove('show')">
  <span id="lightbox-close">✕</span>
  <img id="lightbox-img" src="" alt="">
</div>
<script>
function showLightbox(src){
  document.getElementById('lightbox-img').src=src;
  document.getElementById('lightbox').classList.add('show');
}
</script>
</body>
</html>
