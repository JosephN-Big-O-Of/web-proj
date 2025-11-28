<?php
// Database connection helper. Supports SQLite (default) and MySQL when configured
date_default_timezone_set('UTC');

$config = require __DIR__ . '/config.php';
$dbConfig = $config['db'] ?? [];

if (($dbConfig['driver'] ?? 'sqlite') === 'mysql') {
    // Use PDO MySQL
    $host = $dbConfig['host'] ?: '127.0.0.1';
    $port = $dbConfig['port'] ?: 3306;
    $name = $dbConfig['name'] ?: 'events_db';
    $user = $dbConfig['user'] ?: 'root';
    $pass = $dbConfig['pass'] ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    try {
        $db = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        // Fallback to SQLite when MySQL connection fails (useful for local dev)
        error_log('MySQL connection failed: ' . $e->getMessage());
        // continue to sqlite fallback below
        $db = null;
    }
}

if (!isset($db)) {
    // Fallback to SQLite (default developer experience)
    $dbFile = __DIR__ . '/../data/database.sqlite';
    if (!file_exists(dirname($dbFile))) {
        mkdir(dirname($dbFile), 0755, true);
    }
    $init = !file_exists($dbFile);
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($init) {
        // Create users table
        $db->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            age INTEGER,
            role TEXT DEFAULT 'user',
            joined_at TEXT
        );");

        // Create events table
        $db->exec("CREATE TABLE events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            location TEXT,
            lat REAL,
            lng REAL,
            date TEXT,
            time TEXT,
            age_restriction INTEGER,
            price REAL,
            created_at TEXT
        );");

        // Seed a sample event
        $stmt = $db->prepare("INSERT INTO events (title,description,location,lat,lng,date,time,age_restriction,price,created_at)
            VALUES (:title,:description,:location,:lat,:lng,:date,:time,:age_restriction,:price,:created_at)");
        $stmt->execute([
            ':title' => 'Sample Event Near You',
            ':description' => 'A sample seeded event to demonstrate nearby search.',
            ':location' => 'City Center',
            ':lat' => 51.5074,
            ':lng' => -0.1278,
            ':date' => date('Y-m-d'),
            ':time' => '19:00',
            ':age_restriction' => 18,
            ':price' => 0,
            ':created_at' => date('c')
        ]);
    }
}

// If using MySQL but tables are missing, create them (idempotent)
try {
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $tables = [];
}

if (isset($db) && is_object($db)) {
    // Create tables if they do not exist for MySQL
    if (($dbConfig['driver'] ?? '') === 'mysql') {
        // users
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            age INT,
            role VARCHAR(50) DEFAULT 'user',
            joined_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // events
        $db->exec("CREATE TABLE IF NOT EXISTS events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            location VARCHAR(255),
            lat DOUBLE,
            lng DOUBLE,
            date DATE,
            time TIME,
            age_restriction INT,
            price DECIMAL(10,2),
            created_at DATETIME
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // seed if empty
        $count = $db->query("SELECT COUNT(*) FROM events")->fetchColumn();
        if ($count == 0) {
            $stmt = $db->prepare("INSERT INTO events (title,description,location,lat,lng,date,time,age_restriction,price,created_at)
                VALUES (:title,:description,:location,:lat,:lng,:date,:time,:age_restriction,:price,:created_at)");
            $stmt->execute([
                ':title' => 'Sample Event Near You',
                ':description' => 'A sample seeded event to demonstrate nearby search.',
                ':location' => 'City Center',
                ':lat' => 51.5074,
                ':lng' => -0.1278,
                ':date' => date('Y-m-d'),
                ':time' => '19:00',
                ':age_restriction' => 18,
                ':price' => 0,
                ':created_at' => date('c')
            ]);
        }
    }
}

return $db;
