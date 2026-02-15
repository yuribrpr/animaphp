<?php
require __DIR__ . '/app/_init.php';
$pdo = db();

try {
    echo "--- USERS TABLE ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE users");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));

    echo "\n--- ANIMAS TABLE ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE animas");
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
