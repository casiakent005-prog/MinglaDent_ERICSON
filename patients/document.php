<?php
require_once 'bootstrap.php';

$patient = patientPortalContext();
$documentId = (int) ($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT d.storage_name, d.original_name, d.mime_type FROM patient_documents d INNER JOIN patients p ON p.patient_id = d.patient_id WHERE d.document_id = ? AND p.user_id = ? LIMIT 1");
$stmt->execute([$documentId, $patient['user_id']]);
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