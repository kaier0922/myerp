<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$conn = getDBConnection();

$solution_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// 获取方案详情
$solution = $conn->query("
    SELECT s.*, u.nickname as creator_name
    FROM solutions s
    LEFT JOIN users u ON s.created_by = u.id
    WHERE s.id = $solution_id
")->fetch_assoc();

if (!$solution) {
    die('方案不存在');
}

// 获取附件
$attachments = $conn->query("SELECT * FROM solution_attachments WHERE solution_id = $solution_id");

$conn->close();

// 根据格式导出
switch ($format) {
    case 'word':
        exportAsWord($solution, $attachments);
        break;
    case 'excel':
        exportAsExcel($solution, $attachments);
        break;
    case 'html':
    default:
        exportAsHTML($solution, $attachments);
        break;
}

// ================ HTML导出（可用浏览器打印为PDF）================
function exportAsHTML($solution, $attachments) {
    $filename = $solution['solution_no'] . '_' . date('Ymd');
    ?>
<?php $page_title = htmlspecialchars($solution['solution_name']); include __DIR__ . '/head.php'; ?>
    <style>
        h1 {
            font-size: 28px;
            text-align: center;
            margin-bottom: 30px;
            color: #1a202c;
            border-bottom: 3px solid #1f6dad;
            padding-bottom: 15px;
        }
        h2 {
            font-size: 20px;
            margin: 25px 0 15px;
            color: #2d3748;
            border-left: 4px solid #1f6dad;
            padding-left: 12px;
        }
        .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .info-table td { padding: 10px 15px; border: 1px solid #e2e8f0; }
        .info-table td:first-child {
            background: #f7fafc;
            font-weight: bold;
            width: 150px;
        }
        .content {
            margin: 20px 0;
            text-indent: 2em;
        }
        .content p {
            margin-bottom: 12px;
        }
        .attachment-list {
            list-style: none;
            padding: 0;
        }
        .attachment-list li {
            padding: 8px 12px;
            background: #f7fafc;
            border-left: 3px solid #1f6dad;
            margin-bottom: 8px;
        }
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn btn-primary">🖨️ 打印/保存PDF</button>
    <button onclick="saveAsWord()" class="btn btn-secondary">💾 另存为Word</button>
    <button onclick="window.close()" class="btn btn-secondary">❌ 关闭</button>
</div>

<h1><?php echo htmlspecialchars($solution['solution_name']); ?></h1>

<h2>基本信息</h2>
<table class="info-table">
    <tr>
        <td>方案编号</td>
        <td><?php echo htmlspecialchars($solution['solution_no']); ?></td>
    </tr>
    <tr>
        <td>客户名称</td>
        <td><?php echo htmlspecialchars($solution['customer_name'] ?? '-'); ?></td>
    </tr>
    <tr>
        <td>方案状态</td>
        <td><?php echo htmlspecialchars($solution['status']); ?></td>
    </tr>
    <tr>
        <td>创建人</td>
        <td><?php echo htmlspecialchars($solution['creator_name'] ?? '-'); ?></td>
    </tr>
    <tr>
        <td>创建时间</td>
        <td><?php echo date('Y-m-d H:i', strtotime($solution['created_at'])); ?></td>
    </tr>
</table>

<?php if (!empty($solution['project_background'])): ?>
<h2>项目背景</h2>
<div class="content">
    <?php echo nl2br(htmlspecialchars($solution['project_background'])); ?>
</div>
<?php endif; ?>

<h2>技术方案内容</h2>
<div class="content">
    <?php echo $solution['solution_content']; ?>
</div>

<?php if ($attachments->num_rows > 0): ?>
<h2>相关附件</h2>
<ul class="attachment-list">
    <?php while($att = $attachments->fetch_assoc()): ?>
    <li>
        📎 <?php echo htmlspecialchars($att['file_name']); ?>
        (<?php echo number_format($att['file_size'] / 1024, 2); ?> KB)
    </li>
    <?php endwhile; ?>
</ul>
<?php endif; ?>

<?php if (!empty($solution['remarks'])): ?>
<h2>备注信息</h2>
<div class="content">
    <?php echo nl2br(htmlspecialchars($solution['remarks'])); ?>
</div>
<?php endif; ?>

<script>
function saveAsWord() {
    // 另存为Word文档（实际是HTML格式，但Word可以打开）
    var content = document.documentElement.outerHTML;
    var blob = new Blob(['\ufeff', content], {
        type: 'application/msword'
    });
    var url = URL.createObjectURL(blob);
    var link = document.createElement('a');
    link.href = url;
    link.download = '<?php echo $filename; ?>.doc';
    link.click();
}
</script>

</body>
</html>
    <?php
}

// ================ Word格式导出（HTML格式，Word可打开）================
function exportAsWord($solution, $attachments) {
    $filename = $solution['solution_no'] . '_' . date('Ymd') . '.doc';
    
    header('Content-Type: application/msword');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">';
    echo '<head><meta charset="utf-8"><title>' . htmlspecialchars($solution['solution_name']) . '</title></head>';
    echo '<body>';
    
    echo '<h1 align="center">' . htmlspecialchars($solution['solution_name']) . '</h1>';
    
    echo '<h2>基本信息</h2>';
    echo '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse;width:100%;">';
    echo '<tr><td width="150" style="background:#f0f0f0;"><b>方案编号</b></td><td>' . htmlspecialchars($solution['solution_no']) . '</td></tr>';
    echo '<tr><td style="background:#f0f0f0;"><b>客户名称</b></td><td>' . htmlspecialchars($solution['customer_name'] ?? '-') . '</td></tr>';
    echo '<tr><td style="background:#f0f0f0;"><b>方案状态</b></td><td>' . htmlspecialchars($solution['status']) . '</td></tr>';
    echo '<tr><td style="background:#f0f0f0;"><b>创建人</b></td><td>' . htmlspecialchars($solution['creator_name'] ?? '-') . '</td></tr>';
    echo '<tr><td style="background:#f0f0f0;"><b>创建时间</b></td><td>' . date('Y-m-d H:i', strtotime($solution['created_at'])) . '</td></tr>';
    echo '</table><br>';
    
    if (!empty($solution['project_background'])) {
        echo '<h2>项目背景</h2>';
        echo '<p>' . nl2br(htmlspecialchars($solution['project_background'])) . '</p><br>';
    }
    
    echo '<h2>技术方案内容</h2>';
    echo '<div>' . $solution['solution_content'] . '</div><br>';
    
    if ($attachments->num_rows > 0) {
        echo '<h2>相关附件</h2><ul>';
        while ($att = $attachments->fetch_assoc()) {
            $size = number_format($att['file_size'] / 1024, 2);
            echo '<li>' . htmlspecialchars($att['file_name']) . ' (' . $size . ' KB)</li>';
        }
        echo '</ul><br>';
    }
    
    if (!empty($solution['remarks'])) {
        echo '<h2>备注信息</h2>';
        echo '<p>' . nl2br(htmlspecialchars($solution['remarks'])) . '</p>';
    }
    
    echo '</body></html>';
}

// ================ Excel格式导出（HTML表格，Excel可打开）================
function exportAsExcel($solution, $attachments) {
    $filename = $solution['solution_no'] . '_' . date('Ymd') . '.xls';
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"></head><body>';
    echo '<table border="1">';
    
    // 标题
    echo '<tr><td colspan="2" style="font-size:18px;font-weight:bold;text-align:center;height:40px;">' 
         . htmlspecialchars($solution['solution_name']) . '</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';
    
    // 基本信息
    echo '<tr><td colspan="2" style="font-weight:bold;background:#f0f0f0;">基本信息</td></tr>';
    echo '<tr><td style="background:#f8f8f8;width:150px;">方案编号</td><td>' . htmlspecialchars($solution['solution_no']) . '</td></tr>';
    echo '<tr><td style="background:#f8f8f8;">客户名称</td><td>' . htmlspecialchars($solution['customer_name'] ?? '-') . '</td></tr>';
    echo '<tr><td style="background:#f8f8f8;">方案状态</td><td>' . htmlspecialchars($solution['status']) . '</td></tr>';
    echo '<tr><td style="background:#f8f8f8;">创建人</td><td>' . htmlspecialchars($solution['creator_name'] ?? '-') . '</td></tr>';
    echo '<tr><td style="background:#f8f8f8;">创建时间</td><td>' . date('Y-m-d H:i', strtotime($solution['created_at'])) . '</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';
    
    // 项目背景
    if (!empty($solution['project_background'])) {
        echo '<tr><td colspan="2" style="font-weight:bold;background:#f0f0f0;">项目背景</td></tr>';
        echo '<tr><td colspan="2">' . nl2br(htmlspecialchars($solution['project_background'])) . '</td></tr>';
        echo '<tr><td colspan="2"></td></tr>';
    }
    
    // 技术方案内容
    echo '<tr><td colspan="2" style="font-weight:bold;background:#f0f0f0;">技术方案内容</td></tr>';
    $content = strip_tags($solution['solution_content']);
    $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
    echo '<tr><td colspan="2">' . nl2br($content) . '</td></tr>';
    echo '<tr><td colspan="2"></td></tr>';
    
    // 附件列表
    if ($attachments->num_rows > 0) {
        echo '<tr><td colspan="2" style="font-weight:bold;background:#f0f0f0;">相关附件</td></tr>';
        while ($att = $attachments->fetch_assoc()) {
            $size = number_format($att['file_size'] / 1024, 2);
            echo '<tr><td>' . htmlspecialchars($att['file_name']) . '</td><td>' . $size . ' KB</td></tr>';
        }
        echo '<tr><td colspan="2"></td></tr>';
    }
    
    // 备注
    if (!empty($solution['remarks'])) {
        echo '<tr><td colspan="2" style="font-weight:bold;background:#f0f0f0;">备注信息</td></tr>';
        echo '<tr><td colspan="2">' . nl2br(htmlspecialchars($solution['remarks'])) . '</td></tr>';
    }
    
    echo '</table></body></html>';
}
?>
