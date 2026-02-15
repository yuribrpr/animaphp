<?php

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');

if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $projectSessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($projectSessionDir)) {
        @mkdir($projectSessionDir, 0777, true);
    }

    if (is_dir($projectSessionDir) && is_writable($projectSessionDir)) {
        session_save_path($projectSessionDir);
    }
    else {
        $savePath = (string)ini_get('session.save_path');
        if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
            session_save_path(sys_get_temp_dir());
        }
    }
    session_start();
}

const APP_NAME = 'Anima Online';
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'adminlte_pdo';
const DB_USER = 'root';
const DB_PASS = '';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function set_flash(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function get_flash(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }

    $value = (string)$_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $value;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['_csrf'];
}

function csrf_validate(?string $token): bool
{
    if (!is_string($token) || $token === '' || empty($_SESSION['_csrf'])) {
        return false;
    }

    return hash_equals((string)$_SESSION['_csrf'], $token);
}

function pdo_server(): PDO
{
    $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', DB_HOST, DB_PORT);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    return $pdo;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    pdo_server();

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    ensure_schema($pdo);
    return $pdo;
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            bits INT UNSIGNED NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    try {
        $pdo->exec('ALTER TABLE users ADD COLUMN bits INT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash');
    } catch (Throwable $t) {
    }

    ensure_animas_table($pdo);
    ensure_enemies_table($pdo);
    ensure_user_animas_table($pdo);
    ensure_maps_tables($pdo);
}

function ensure_animas_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS animas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            species VARCHAR(120) NOT NULL,
            next_evolution_id INT UNSIGNED DEFAULT NULL,
            level ENUM('Rookie', 'Champion', 'Ultimate', 'Mega', 'Burst Mode') NOT NULL,
            attribute ENUM('virus', 'vacina', 'data', 'unknown') NOT NULL,
            attack INT UNSIGNED NOT NULL DEFAULT 0,
            defense INT UNSIGNED NOT NULL DEFAULT 0,
            max_health INT UNSIGNED NOT NULL DEFAULT 0,
            attack_speed INT UNSIGNED NOT NULL DEFAULT 0,
            crit_chance DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            image_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_animas_species (species),
            KEY idx_animas_level (level),
            KEY idx_animas_attribute (attribute),
            CONSTRAINT fk_animas_next_evolution_id FOREIGN KEY (next_evolution_id) REFERENCES animas(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $pdo->exec("ALTER TABLE animas MODIFY level ENUM('Rookie', 'Champion', 'Ultimate', 'Mega', 'Burst Mode') NOT NULL");
    } catch (Throwable $t) {
    }

    try {
        $pdo->exec("ALTER TABLE animas MODIFY attribute ENUM('virus', 'vacina', 'data', 'unknown') NOT NULL");
    } catch (Throwable $t) {
    }

    try {
        $pdo->exec("ALTER TABLE animas MODIFY attack INT UNSIGNED NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE animas MODIFY defense INT UNSIGNED NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE animas MODIFY max_health INT UNSIGNED NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE animas MODIFY attack_speed INT UNSIGNED NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE animas MODIFY crit_chance DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    } catch (Throwable $t) {
    }
}

function ensure_enemies_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS enemies (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            species VARCHAR(120) NOT NULL,
            level VARCHAR(50) NOT NULL,
            attribute VARCHAR(50) NOT NULL,
            image_path VARCHAR(255) DEFAULT NULL,
            max_health INT NOT NULL DEFAULT 0,
            attack INT NOT NULL DEFAULT 0,
            defense INT NOT NULL DEFAULT 0,
            crit_chance DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            attack_speed INT NOT NULL DEFAULT 0,
            reward_exp INT NOT NULL DEFAULT 0,
            reward_bits INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_enemies_species (species)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $pdo->exec('ALTER TABLE enemies ADD COLUMN reward_exp INT NOT NULL DEFAULT 0 AFTER attack_speed');
    } catch (Throwable $t) {
    }

    try {
        $pdo->exec('ALTER TABLE enemies ADD COLUMN reward_bits INT NOT NULL DEFAULT 0 AFTER reward_exp');
    } catch (Throwable $t) {
    }
}

function ensure_user_animas_table(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_animas (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            anima_id INT UNSIGNED NOT NULL,
            nickname VARCHAR(50) DEFAULT NULL,
            level INT NOT NULL DEFAULT 1,
            current_exp INT NOT NULL DEFAULT 0,
            next_level_exp INT NOT NULL DEFAULT 1000,
            current_health INT NOT NULL DEFAULT 0,
            bonus_attack INT NOT NULL DEFAULT 0,
            bonus_defense INT NOT NULL DEFAULT 0,
            reduction_attack_speed INT NOT NULL DEFAULT 0,
            bonus_crit_chance DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            is_main TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_animas_user (user_id),
            KEY idx_user_animas_anima (anima_id),
            CONSTRAINT fk_user_animas_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_animas_anima FOREIGN KEY (anima_id) REFERENCES animas(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function ensure_maps_tables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS maps (
            id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(120) NOT NULL,
            background_image_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_maps_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS map_enemies (
                map_id INT NOT NULL,
                enemy_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (map_id, enemy_id),
                KEY idx_map_enemies_enemy_id (enemy_id),
                CONSTRAINT fk_map_enemies_map FOREIGN KEY (map_id) REFERENCES maps(id) ON DELETE CASCADE,
                CONSTRAINT fk_map_enemies_enemy FOREIGN KEY (enemy_id) REFERENCES enemies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $t) {
        // Fallback for legacy DBs with incompatible FK types (signed/unsigned mismatch).
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS map_enemies (
                map_id INT NOT NULL,
                enemy_id INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (map_id, enemy_id),
                KEY idx_map_enemies_enemy_id (enemy_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $id = (int)$_SESSION['user_id'];
    if ($id <= 0) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, bits, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    return is_array($user) ? $user : null;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        set_flash('error', 'Faça login para continuar.');
        redirect('/app/login.php');
    }

    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}
