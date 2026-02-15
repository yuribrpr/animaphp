<?php
require __DIR__ . '/../_init.php';

require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/app/admin/animas.php');
}

if (!csrf_validate($_POST['_csrf'] ?? null)) {
    set_flash('error', 'Sessão expirada. Tente novamente.');
    redirect('/app/admin/animas.php');
}

$animaId = (int)($_POST['anima_id'] ?? 0);
if ($animaId <= 0) {
    set_flash('error', 'Anima inválido.');
    redirect('/app/admin/animas.php');
}

$stmt = $pdo->prepare('SELECT id, image_path FROM animas WHERE id = ? LIMIT 1');
$stmt->execute([$animaId]);
$anima = $stmt->fetch();
if (!$anima) {
    set_flash('error', 'Anima não encontrado.');
    redirect('/app/admin/animas.php');
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    set_flash('error', 'Envie um arquivo de imagem.');
    redirect('/app/admin/animas.php');
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    set_flash('error', 'Falha no upload do arquivo.');
    redirect('/app/admin/animas.php');
}

$tmpName = (string)($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    set_flash('error', 'Arquivo inválido.');
    redirect('/app/admin/animas.php');
}

$maxBytes = 6 * 1024 * 1024;
$size = (int)($file['size'] ?? 0);
if ($size <= 0 || $size > $maxBytes) {
    set_flash('error', 'Tamanho máximo: 6MB.');
    redirect('/app/admin/animas.php');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string)$finfo->file($tmpName);

$ext = match ($mime) {
    'image/gif' => 'gif',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    default => null,
};

if (!$ext) {
    set_flash('error', 'Formato não suportado.');
    redirect('/app/admin/animas.php');
}

$rootDir = dirname(__DIR__, 2);
$uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'animas';
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    set_flash('error', 'Pasta de upload sem permissão.');
    redirect('/app/admin/animas.php');
}

$rand = bin2hex(random_bytes(16));
$filename = 'anima_' . $animaId . '_' . $rand . '.' . $ext;
$dest = $uploadDir . DIRECTORY_SEPARATOR . $filename;

if (!move_uploaded_file($tmpName, $dest)) {
    set_flash('error', 'Não foi possível salvar o arquivo.');
    redirect('/app/admin/animas.php');
}

$publicPath = '/uploads/animas/' . $filename;

$prev = (string)($anima['image_path'] ?? '');
if ($prev !== '' && str_starts_with($prev, '/uploads/animas/')) {
    $prevFile = $uploadDir . DIRECTORY_SEPARATOR . basename($prev);
    if (is_file($prevFile)) {
        @unlink($prevFile);
    }
}

$stmt = $pdo->prepare('UPDATE animas SET image_path = ? WHERE id = ?');
$stmt->execute([$publicPath, $animaId]);

set_flash('success', 'Imagem atualizada.');
redirect('/app/admin/animas.php');

