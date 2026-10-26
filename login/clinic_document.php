<?php
require_once __DIR__ . '/../database/config.php';
require_once __DIR__ . '/../includes/clinic_applications.php';
$user = getCurrentUser();
if (!$user || $user['status'] !== 'active' || !in_array($user['role'], ['admin', 'clinic'], true)) {
    http_response_code(403); exit('Access denied.');
}
$stmt = $pdo->prepare('SELECT d.* FROM clinic_application_documents d JOIN clinics c ON c.clinic_id = d.clinic_id WHERE d.document_id = ? AND (? = \'admin\' OR c.owner_user_id = ?) LIMIT 1');
$stmt->execute([(int) ($_GET['id'] ?? 0), $user['role'], $user['id']]);
$document = $stmt->fetch();
if (!$document) { http_response_code(404); exit('Document not found.'); }
$path = clinicDocumentDirectory() . '/' . basename($document['storage_name']);
if (!is_file($path)) { http_response_code(404); exit('Document file not found.'); }
header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . rawurlencode($document['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store');
readfile($path);
