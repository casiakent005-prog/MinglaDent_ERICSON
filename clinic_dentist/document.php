<?php
require_once '../clinic_portal/bootstrap.php';
$context = clinicPortalRequireRoles(['dentist']);
$documentId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT storage_name, original_name, mime_type FROM patient_documents WHERE document_id = ? AND clinic_id = ? LIMIT 1");
$stmt->execute([$documentId, $context['clinic_id']]);
$document = $stmt->fetch();

if (!$document) {
    http_response_code(404);
    exit('Document not found.');
}

$filePath = dirname(__DIR__) . '/storage/patient_documents/' . basename($document['storage_name']);
if (!is_file($filePath)) {
    http_response_code(404);
    exit('Document file not found.');
}

header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . rawurlencode($document['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;