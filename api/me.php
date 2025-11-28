<?php
session_start();
header('Content-Type: application/json');
try {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error'=>'Not authenticated']);
        exit;
    }
    $db = require __DIR__ . '/db.php';
    $stmt = $db->prepare('SELECT id,name,email,age,role,joined_at FROM users WHERE id = :id');
    $stmt->execute([':id'=>$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error'=>'User not found']);
        exit;
    }
    echo json_encode(['user'=>$user]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
