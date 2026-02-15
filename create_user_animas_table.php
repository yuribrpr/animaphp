<?php
require __DIR__ . '/app/_init.php';

$pdo = db();

$sql = "
CREATE TABLE IF NOT EXISTS user_animas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    anima_id INT UNSIGNED NOT NULL,
    nickname VARCHAR(50) DEFAULT NULL,
    level INT DEFAULT 1,
    current_exp INT DEFAULT 0,
    next_level_exp INT DEFAULT 1000,
    current_health INT DEFAULT 0,
    bonus_attack INT DEFAULT 0,
    bonus_defense INT DEFAULT 0,
    reduction_attack_speed INT DEFAULT 0,
    bonus_crit_chance DECIMAL(5,2) DEFAULT 0.00,
    is_main BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (anima_id) REFERENCES animas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "Tabela 'user_animas' criada com sucesso.";
} catch (PDOException $e) {
    echo "Erro ao criar tabela: " . $e->getMessage();
}
