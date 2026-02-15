<?php
require_once __DIR__ . '/../_init.php';

// Prepare response header
header('Content-Type: application/json');

$user = current_user();
if (!$user) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            list_animas();
            break;
        case 'save':
            save_anima();
            break;
        case 'get':
            get_anima();
            break;
        case 'delete':
            delete_anima();
            break;
        case 'list_options':
            list_options();
            break;
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}
catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function list_animas()
{
    $pdo = db();
    // Fetch all animas
    // To show evolution name, we might want to join or just fetch raw.
    // Let's self join to get evolution name
    $sql = "SELECT a.*, p.name as next_evolution_name 
            FROM animas a 
            LEFT JOIN animas p ON a.next_evolution_id = p.id
            ORDER BY a.id DESC";

    $stmt = $pdo->query($sql);
    $animas = $stmt->fetchAll();

    echo json_encode(['data' => $animas]);
}

function list_options()
{
    $pdo = db();
    $stmt = $pdo->query("SELECT id, name FROM animas ORDER BY name ASC");
    echo json_encode($stmt->fetchAll());
}

function get_anima()
{
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("Invalid ID");
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM animas WHERE id = ?");
    $stmt->execute([$id]);
    $anima = $stmt->fetch();

    if (!$anima) {
        throw new Exception("Anima not found");
    }

    echo json_encode($anima);
}

function save_anima()
{
    $pdo = db();
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $species = trim($_POST['species'] ?? '');
    $next_evolution_id = !empty($_POST['next_evolution_id']) ? (int)$_POST['next_evolution_id'] : null;
    $level = $_POST['level'] ?? '';
    $attribute = $_POST['attribute'] ?? '';

    // Stats
    $attack = (int)($_POST['attack'] ?? 0);
    $defense = (int)($_POST['defense'] ?? 0);
    $max_health = (int)($_POST['max_health'] ?? 0);
    $attack_speed = (int)($_POST['attack_speed'] ?? 0);
    $crit_chance = (int)($_POST['crit_chance'] ?? 0);

    // Validation
    if (empty($name) || empty($species) || empty($level) || empty($attribute)) {
        throw new Exception("Please fill in all required fields.");
    }

    // Handle Image Upload
    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__, 2) . '/uploads/animas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('anima_') . '.' . $ext;
        $targetFile = $uploadDir . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $image_path = '/uploads/animas/' . $filename;
        }
    }

    // Update or Insert
    if ($id > 0) {
        // Update
        $sql = "UPDATE animas SET 
                name = ?, species = ?, next_evolution_id = ?, level = ?, attribute = ?,
                attack = ?, defense = ?, max_health = ?, attack_speed = ?, crit_chance = ?";

        $params = [
            $name, $species, $next_evolution_id, $level, $attribute,
            $attack, $defense, $max_health, $attack_speed, $crit_chance
        ];

        if ($image_path) {
            $sql .= ", image_path = ?";
            $params[] = $image_path;
        }

        $sql .= " WHERE id = ?";
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

    }
    else {
        // Insert
        // Make sure next_evolution_id is valid or NULL
        // Note: You can't evolve into something that doesn't exist yet, so this cyclic dependency is tricky 
        // if creating the higher level first. But the UI allows creating without evolution first.

        $sql = "INSERT INTO animas (
                name, species, next_evolution_id, level, attribute,
                attack, defense, max_health, attack_speed, crit_chance, image_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $name, $species, $next_evolution_id, $level, $attribute,
            $attack, $defense, $max_health, $attack_speed, $crit_chance, $image_path
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    echo json_encode(['success' => true]);
}

function delete_anima()
{
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        throw new Exception("Invalid ID");
    }

    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM animas WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
}
