<?php
/**
 * 公章管理 AJAX API
 * 功能：获取公章列表、保存公章位置、获取文档公章配置
 */
session_start();
require_once 'config.php';

// 检查登录
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$conn = getDBConnection();

/**
 * 获取公章列表
 */
if ($action === 'get_seals') {
    try {
        // 从数据库获取公章（如果有seals表）
        $sql = "SHOW TABLES LIKE 'seals'";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            // 如果有seals表，从数据库读取
            $sql = "SELECT id, seal_name, seal_type, file_path FROM seals WHERE status = 'active' AND user_id = " . intval($_SESSION['user_id']) . " ORDER BY id DESC";
            $result = $conn->query($sql);
            
            $seals = [];
            while ($row = $result->fetch_assoc()) {
                $seals[] = $row;
            }
        } else {
            // 如果没有seals表，扫描uploads目录下的公章图片
            $seals = [];
            $upload_dir = __DIR__ . '/uploads/seals/';
            
            // 如果目录不存在，尝试当前目录
            if (!is_dir($upload_dir)) {
                $upload_dir = __DIR__ . '/';
            }
            
            $seal_files = glob($upload_dir . 'seal_*.png');
            
            foreach ($seal_files as $index => $file) {
                $filename = basename($file);
                $seals[] = [
                    'id' => $index + 1,
                    'seal_name' => '企业公章 ' . ($index + 1),
                    'seal_type' => '企业公章',
                    'file_path' => 'uploads/seals/' . $filename
                ];
                
                // 如果文件在当前目录，调整路径
                if (!is_dir(__DIR__ . '/uploads/seals/')) {
                    $seals[$index]['file_path'] = $filename;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'seals' => $seals
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

/**
 * 保存文档公章配置
 */
if ($action === 'save_document_seal') {
    try {
        $document_type = $_POST['document_type'] ?? '';
        $document_id = intval($_POST['document_id'] ?? 0);
        $seal_id = intval($_POST['seal_id'] ?? 0);
        $position_x = intval($_POST['position_x'] ?? 0);
        $position_y = intval($_POST['position_y'] ?? 0);
        $seal_size = intval($_POST['seal_size'] ?? 120);
        
        if (!$document_type || !$document_id || !$seal_id) {
            throw new Exception('参数不完整');
        }
        
        // 检查是否已有配置
        $check_sql = "SELECT id FROM document_seals WHERE document_type = ? AND document_id = ?";
        $stmt = $conn->prepare($check_sql);
        $stmt->bind_param("si", $document_type, $document_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        
        if ($existing) {
            // 更新
            $sql = "UPDATE document_seals SET 
                    seal_id = ?, 
                    position_x = ?, 
                    position_y = ?, 
                    seal_size = ?,
                    updated_at = NOW()
                    WHERE document_type = ? AND document_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiiisi", $seal_id, $position_x, $position_y, $seal_size, $document_type, $document_id);
        } else {
            // 插入
            $sql = "INSERT INTO document_seals 
                    (document_type, document_id, seal_id, position_x, position_y, seal_size, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("siiiii", $document_type, $document_id, $seal_id, $position_x, $position_y, $seal_size);
        }
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('保存失败：' . $conn->error);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

/**
 * 获取文档公章配置
 */
if ($action === 'get_document_seal') {
    try {
        $document_type = $_GET['document_type'] ?? '';
        $document_id = intval($_GET['document_id'] ?? 0);
        
        if (!$document_type || !$document_id) {
            throw new Exception('参数不完整');
        }
        
        // 检查表是否存在
        $check_table = "SHOW TABLES LIKE 'document_seals'";
        $result = $conn->query($check_table);
        
        if (!$result || $result->num_rows === 0) {
            // 表不存在，返回空配置
            echo json_encode(['success' => true, 'config' => null]);
            exit;
        }
        
        // 获取配置
        $sql = "SELECT ds.*, s.file_path, s.seal_name 
                FROM document_seals ds
                LEFT JOIN seals s ON ds.seal_id = s.id
                WHERE ds.document_type = ? AND ds.document_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $document_type, $document_id);
        $stmt->execute();
        $config = $stmt->get_result()->fetch_assoc();
        
        // 如果没有seals表，尝试手动获取文件路径
        if ($config && !$config['file_path']) {
            $upload_dir = __DIR__ . '/uploads/seals/';
            if (!is_dir($upload_dir)) {
                $upload_dir = __DIR__ . '/';
            }
            
            $seal_files = glob($upload_dir . 'seal_*.png');
            if (isset($seal_files[$config['seal_id'] - 1])) {
                $filename = basename($seal_files[$config['seal_id'] - 1]);
                $config['file_path'] = is_dir(__DIR__ . '/uploads/seals/') 
                    ? 'uploads/seals/' . $filename 
                    : $filename;
                $config['seal_name'] = '企业公章 ' . $config['seal_id'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'config' => $config
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode([
    'success' => false,
    'error' => '未知操作'
]);
