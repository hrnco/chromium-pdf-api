<?php
declare(strict_types=1);
require __DIR__ . '/../../src/ChromePdf.php';

/**
 * POST API: generate PDF from either URL or raw HTML.
 * Accepts:
 *  - application/json:
 *      { "url": "https://...", OR "html": "<!doctype html>...", "baseUrl": "https://example.com/" }
 *  - application/x-www-form-urlencoded or multipart/form-data:
 *      url=...  OR  html=... [&baseUrl=...]
 *
 * Response: application/pdf (inline)
 * Errors:   400 invalid input, 500 generation failure
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    header('Content-Type: text/plain; charset=utf-8');
    echo "Method Not Allowed. Use POST.";
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$ctype = $_SERVER['CONTENT_TYPE'] ?? '';

$payload = [];
if (stripos($ctype, 'application/json') === 0) {
    $payload = json_decode($raw, true) ?: [];
} else {
    $payload = $_POST;
}

$url = $payload['url'] ?? null;
$html = $payload['html'] ?? null;
$baseUrl = $payload['baseUrl'] ?? null;

// Validate input (prefer HTML if both provided)
if (is_string($html) && $html !== '') {
    // OK (HTML path)
} elseif (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
    // OK (URL path)
} else {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bad Request: provide either a valid 'url' or non-empty 'html'.";
    exit;
}

$pdfPath = null;

try {
    $chrome = new ChromePdf();
    $flags = [
        // TIP: optional Chromium flags
        // '--landscape',
        // '--virtual-time-budget=8000',
        // '--user-agent=Mozilla/5.0 ...',
    ];

    if (is_string($html) && $html !== '') {
        // If baseUrl is provided, inject <base> into <head> for relative assets.
        if ($baseUrl) {
            $safeBase = htmlspecialchars($baseUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            if (preg_match('/<head[^>]*>/i', $html)) {
                $html = preg_replace(
                    '/<head([^>]*)>/i',
                    '<head$1><base href="' . $safeBase . '">',
                    $html,
                    1
                );
            } else {
                // No <head> present — prepend a minimal head with base (kept minimal on purpose)
                $html = "<!doctype html>\n<html><head><base href=\"{$safeBase}\"></head>" .
                    "<body>{$html}</body></html>";
            }
        }
        $pdfPath = $chrome->renderHtml($html, $flags);
    } else {
        $pdfPath = $chrome->renderUrl($url, $flags);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="document.pdf"');
    header('Content-Length: ' . (string)filesize($pdfPath));
    readfile($pdfPath);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Error: " . $e->getMessage();
} finally {
    if (isset($pdfPath) && $pdfPath && is_file($pdfPath)) {
        @unlink($pdfPath);
    }
}
