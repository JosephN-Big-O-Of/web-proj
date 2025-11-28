<?php
session_start();
header('Content-Type: application/json');
try {
    $db = require __DIR__ . '/db.php';
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception('Invalid JSON');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    $errors = [];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required';
    if ($password === '') $errors['password'] = 'Password is required';
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors' => $errors]);
        exit;
    }
    $stmt = $db->prepare('SELECT id,name,email,password_hash,age FROM users WHERE email = :email');
    $stmt->execute([':email'=>$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);
        echo json_encode(['error'=>'Invalid credentials']);
        exit;
    }
    // set session
    $_SESSION['user_id'] = $user['id'];
    // return user (without hash)
    unset($user['password_hash']);
    echo json_encode(['success'=>true,'user'=>$user]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
