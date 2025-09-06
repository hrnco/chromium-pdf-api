<?php
declare(strict_types=1);
require __DIR__ . '/../../src/ChromePdf.php';

/**
 * POST API: generate PDF from a URL.
 * Accepts either:
 *  - application/x-www-form-urlencoded or multipart/form-data:  url=...
 *  - application/json: {"url":"https://..."}
 *
 * Response: application/pdf (inline)
 * Errors:    400 invalid input, 500 generation failure
 */

$raw = file_get_contents('php://input') ?: '';
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';
$url = null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method Not Allowed. Use POST.";
    exit;
}

if (stripos($ctype, 'application/json') === 0) {
    $payload = json_decode($raw, true) ?: [];
    $url = $payload['url'] ?? null;
} else {
    $url = $_POST['url'] ?? null;
}

// Validate input
if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bad Request: provide a valid 'url'.";
    exit;
}

// Render
$pdfPath = null;
try {
    $chrome = new ChromePdf();
    $flags = [
        // '--landscape',
        // '--virtual-time-budget=8000',
        // '--user-agent=Mozilla/5.0 ...',
    ];

    $pdfPath = $chrome->renderUrl($url, $flags);

    // Response
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename=\"document.pdf\""');
    header('Content-Length: ' . (string)filesize($pdfPath));
    readfile($pdfPath);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error: " . $e->getMessage();
} finally {
    if ($pdfPath && is_file($pdfPath)) {
        @unlink($pdfPath);
    }
}
