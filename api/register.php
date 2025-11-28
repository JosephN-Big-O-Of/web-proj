<?php
header('Content-Type: application/json');
try {
    $db = require __DIR__ . '/db.php';
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) throw new Exception('Invalid JSON');
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $age = isset($data['age']) ? $data['age'] : null;

    $errors = [];
    if ($name === '') $errors['name'] = 'Name is required';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required';
    if ($password === '' || strlen($password) < 6) $errors['password'] = 'Password is required (min 6 chars)';
    if ($age !== null && $age !== '') {
        if (!is_numeric($age) || intval($age) < 0) $errors['age'] = 'Age must be a non-negative number';
        else $age = intval($age);
    } else {
        $age = null;
    }
    if (!empty($errors)) {
        http_response_code(400);
        echo json_encode(['errors'=>$errors]);
        exit;
    }
    // check existing
    $stmt = $db->prepare('SELECT id FROM users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Email already registered']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (name,email,password_hash,age,joined_at) VALUES (:name,:email,:hash,:age,:joined)');
    $stmt->execute([
        ':name'=>$name,':email'=>$email,':hash'=>$hash,':age'=>$age,':joined'=>date('c')
    ]);
    $id = $db->lastInsertId();
    echo json_encode(['success'=>true,'user'=>['id'=>$id,'name'=>$name,'email'=>$email,'age'=>$age]]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()]);
}
