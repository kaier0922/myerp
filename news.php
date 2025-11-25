<?php
// 新闻聚合页：从论坛获取最新帖子并在站点上展示
mb_internal_encoding('UTF-8');
require_once 'config.php';

$bbs_conn = getBBSConnection();
if (!$bbs_conn) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>新闻获取失败</title></head><body>';
    echo '<h1>无法连接到论坛数据库</h1>';
    echo '<p>请检查 BBS 数据库配置或网络连接。</p>';
    echo '</body></html>';
    exit;
}

$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 26;
$limit = isset($_GET['limit']) ? max(1, min((int)$_GET['limit'], 50)) : 20;
$table_prefix = BBS_TABLE_PREFIX;

$threads = [];
$thread_sql = "SELECT tid, subject, author, dateline, lastpost FROM {$table_prefix}forum_thread WHERE fid = ? AND displayorder >= 0 ORDER BY lastpost DESC LIMIT ?";
$thread_stmt = $bbs_conn->prepare($thread_sql);

if ($thread_stmt) {
    $thread_stmt->bind_param('ii', $fid, $limit);
    $thread_stmt->execute();
    $thread_result = $thread_stmt->get_result();

    $post_sql = "SELECT message FROM {$table_prefix}forum_post WHERE tid = ? AND first = 1 LIMIT 1";
    $post_stmt = $bbs_conn->prepare($post_sql);

    while ($thread = $thread_result->fetch_assoc()) {
        $content_preview = '';

        if ($post_stmt) {
            $post_stmt->bind_param('i', $thread['tid']);
            $post_stmt->execute();
            $post_result = $post_stmt->get_result();
            $post = $post_result->fetch_assoc();

            if ($post && isset($post['message'])) {
                $clean_message = preg_replace('/\s+/', ' ', strip_tags($post['message']));
                $content_preview = mb_substr($clean_message, 0, 140);
                if (mb_strlen($clean_message) > 140) {
                    $content_preview .= '...';
                }
            }
        }

        $threads[] = [
            'tid' => $thread['tid'],
            'title' => $thread['subject'],
            'author' => $thread['author'],
            'date' => date('Y-m-d', (int)$thread['dateline']),
            'preview' => $content_preview,
            'link' => "https://www.pc4g.com/bbs/forum.php?mod=viewthread&tid={$thread['tid']}"
        ];
    }

    if ($post_stmt) {
        $post_stmt->close();
    }

    $thread_stmt->close();
}

$bbs_conn->close();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT 行业动态 - pc4g</title>
    <style>
        :root {
            --primary: #ff6b81;
            --secondary: #4158d0;
            --text: #1a2b3c;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #f9fafb;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .hero {
            background: linear-gradient(120deg, rgba(65, 88, 208, 0.85), rgba(255, 107, 129, 0.85)), url('https://www.pc4g.com/assets/images/it-bg.jpg') center/cover no-repeat;
            color: white;
            padding: 64px 24px;
            text-align: center;
        }

        .hero h1 { margin: 0 0 12px; font-size: 28px; }
        .hero p { margin: 0; color: #f3f4f6; }

        .container { max-width: 1100px; margin: -40px auto 48px; padding: 0 16px; }

        .card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 16px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border);
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }

        .card .meta {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .title { font-size: 18px; font-weight: 700; color: var(--text); }
        .excerpt { color: var(--muted); line-height: 1.5; }
        .info { color: var(--muted); font-size: 14px; display: flex; gap: 12px; align-items: center; }

        .tag {
            background: rgba(255, 107, 129, 0.1);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            border: 1px solid rgba(255, 107, 129, 0.3);
        }

        .btn {
            display: inline-block;
            margin-top: 8px;
            padding: 10px 16px;
            border-radius: 10px;
            background: linear-gradient(120deg, var(--secondary), var(--primary));
            color: white;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 10px 20px rgba(255, 107, 129, 0.3);
        }

        @media (max-width: 600px) {
            .card { flex-direction: column; }
        }
    </style>
</head>
<body>
    <section class="hero">
        <h1>IT行业动态</h1>
        <p>关注最新的IT技术发展趋势，企业信息化建设动态，为您提供专业的行业资讯与技术分享</p>
    </section>

    <main class="container">
        <?php if (empty($threads)): ?>
            <div class="card">
                <div class="meta">
                    <div class="title">暂无可展示的帖子</div>
                    <div class="excerpt">暂未获取到 fid=<?php echo htmlspecialchars($fid); ?> 的论坛内容，请稍后再试。</div>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($threads as $thread): ?>
            <article class="card">
                <div class="meta">
                    <div class="title"><?php echo htmlspecialchars($thread['title']); ?></div>
                    <?php if (!empty($thread['preview'])): ?>
                        <div class="excerpt"><?php echo htmlspecialchars($thread['preview']); ?></div>
                    <?php endif; ?>
                    <div class="info">
                        <span class="tag">论坛同步</span>
                        <span><?php echo htmlspecialchars($thread['author']); ?></span>
                        <span><?php echo htmlspecialchars($thread['date']); ?></span>
                    </div>
                    <a class="btn" href="<?php echo htmlspecialchars($thread['link']); ?>" target="_blank" rel="noopener noreferrer">阅读全文</a>
                </div>
            </article>
        <?php endforeach; ?>
    </main>
</body>
</html>
