<?php
/**
 * =====================================================
 * 文件名：ajax_seals.php
 * 功能：公章相关AJAX操作
 * =====================================================
 */

session_start();
require_once 'config.php';

// 设置JSON响应头
header('Content-Type: application/json; charset=utf-8');

// 检查登录状态
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

$conn = getDBConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'error' => '数据库连接失败']);
    exit;
}

// 获取公章列表
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_seals') {
    $sql = "SELECT id, seal_name, seal_type, file_path, is_default 
            FROM seals 
            ORDER BY is_default DESC, created_at DESC";
    
    $result = $conn->query($sql);
    $seals = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $seals[] = $row;
        }
        echo json_encode([
            'success' => true,
            'seals' => $seals
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '查询失败'
        ]);
    }
}

// 获取默认公章
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_default_seal') {
    $sql = "SELECT id, seal_name, seal_type, file_path 
            FROM seals 
            WHERE is_default = 1 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'seal' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '未设置默认公章'
        ]);
    }
}

// 保存文档公章配置
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_document_seal') {
    $document_type = $_POST['document_type']; // quotation 或 delivery
    $document_id = intval($_POST['document_id']);
    $seal_id = intval($_POST['seal_id']);
    $position_x = intval($_POST['position_x']);
    $position_y = intval($_POST['position_y']);
    $seal_size = intval($_POST['seal_size']);
    
    // 先删除旧配置
    $sql = "DELETE FROM document_seals WHERE document_type = ? AND document_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $document_type, $document_id);
    $stmt->execute();
    
    // 插入新配置
    $sql = "INSERT INTO document_seals 
            (document_type, document_id, seal_id, position_x, position_y, seal_size) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siiiii", $document_type, $document_id, $seal_id, $position_x, $position_y, $seal_size);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => '公章配置已保存'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '保存失败'
        ]);
    }
}

// 获取文档公章配置
elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_document_seal') {
    $document_type = $_GET['document_type'];
    $document_id = intval($_GET['document_id']);
    
    $sql = "SELECT ds.*, s.seal_name, s.file_path 
            FROM document_seals ds 
            LEFT JOIN seals s ON ds.seal_id = s.id 
            WHERE ds.document_type = ? AND ds.document_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $document_type, $document_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'config' => $row
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => '未找到公章配置'
        ]);
    }
}

// 未知操作
else {
    echo json_encode([
        'success' => false,
        'error' => '无效的操作'
    ]);
}

$conn->close();
?>