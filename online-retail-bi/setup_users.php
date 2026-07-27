<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

$db->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'admin',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB;
");

$stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
$stmt->execute(['admin@bi.com']);
if ($stmt->fetchColumn() == 0) {
    $pass = password_hash('admin123', PASSWORD_BCRYPT);
    $db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)')->execute(['Administrator BI', 'admin@bi.com', $pass, 'admin']);
    echo "✅ Users table & Default admin (admin@bi.com / admin123) created successfully!\n";
} else {
    echo "✅ Users table is ready!\n";
}
