<?php
require __DIR__ . '/app/_init.php';

$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS enemies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    species VARCHAR(255) NOT NULL,
    level VARCHAR(50) NOT NULL,
    attribute VARCHAR(50) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    max_health INT DEFAULT 0,
    attack INT DEFAULT 0,
    defense INT DEFAULT 0,
    crit_chance DECIMAL(5,2) DEFAULT 0.00,
    attack_speed INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Tabela 'enemies' criada com sucesso.";
} catch (PDOException $e) {
    echo "Erro ao criar tabela: " . $e->getMessage();
}
