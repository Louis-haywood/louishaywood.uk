<?php
/**
 * Portfolio CMS API
 * Handles content read/write and image uploads.
 * Password is set below — never expose this file's source.
 */

define('ADMIN_PASSWORD', 'Louis789');
define('CONTENT_FILE',   __DIR__ . '/content.json');
define('UPLOAD_DIR',     __DIR__ . '/images/');

// ── CORS / headers ────────────────────────────────────────────────────────────
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Password');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
function checkAuth() {
    $headers = getallheaders();
    $pw = $headers['X-Admin-Password'] ?? $headers['x-admin-password'] ?? '';
    if ($pw !== ADMIN_PASSWORD) {
        http_response_code(401);
        echo json_encode(['error' => 'Incorrect password.']);
        exit;
    }
}

// ── Route ─────────────────────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'content';
$method = $_SERVER['REQUEST_METHOD'];

// GET content — public (the live site fetches this directly from content.json,
// but this endpoint is used by the admin login check)
if ($action === 'content' && $method === 'GET') {
    checkAuth();
    if (!file_exists(CONTENT_FILE)) {
        http_response_code(404);
        echo json_encode(['error' => 'content.json not found']);
        exit;
    }
    echo file_get_contents(CONTENT_FILE);
    exit;
}

// POST content — save edited content
if ($action === 'content' && $method === 'POST') {
    checkAuth();
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if ($data === null) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    file_put_contents(CONTENT_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['ok' => true, 'message' => 'Saved successfully.']);
    exit;
}

// POST upload — image upload
if ($action === 'upload' && $method === 'POST') {
    checkAuth();
    if (empty($_FILES['image'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No image received.']);
        exit;
    }
    $file     = $_FILES['image'];
    $allowed  = ['image/jpeg','image/png','image/gif','image/webp','image/avif'];
    $mimeType = mime_content_type($file['tmp_name']);
    if (!in_array($mimeType, $allowed)) {
        http_response_code(400);
        echo json_encode(['error' => 'File type not allowed.']);
        exit;
    }
    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'upload-' . time() . '-' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
    $dest     = UPLOAD_DIR . $filename;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save image.']);
        exit;
    }
    echo json_encode(['url' => 'images/' . $filename]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Unknown action.']);
