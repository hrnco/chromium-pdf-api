<?php
declare(strict_types=1);

final class ChromePdf
{
    private string $chromeBin;

    public function __construct(?string $chromeBin = null)
    {
        $this->chromeBin = $chromeBin ?: $this->detectChrome();
    }

    public function renderUrl(string $url, array $flags = []): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https', 'file'], true)) {
            throw new \RuntimeException('Invalid URL scheme (allowed: http, https, file)');
        }

        $tmpPdf = $this->tmpPdfPath();

        $defaultFlags = [
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-pdf-header-footer',
        ];

        if ($scheme === 'file') {
            // allow loading HTTP resources from a file:// page
            $defaultFlags[] = '--allow-file-access-from-files';
            $defaultFlags[] = '--disable-web-security';
            $defaultFlags[] = '--user-data-dir=/tmp/chrome-data'; // required for disable-web-security to take effect
            // TIP: relax private-network blocking (Docker service hostnames like http://symfony/)
            $defaultFlags[] = '--disable-features=BlockInsecurePrivateNetworkRequests,BlockInsecurePrivateNetworkRequestsFromPrivate';
            // $defaultFlags[] = '--allow-running-insecure-content'; // TIP: only if you still see blocking
        }

        $allFlags = array_merge($defaultFlags, $flags, [
            '--print-to-pdf=' . $tmpPdf,
        ]);

        $cmd = $this->buildCommand($url, $allFlags);
        $this->run($cmd);

        if (!is_file($tmpPdf) || filesize($tmpPdf) === 0) {
            @unlink($tmpPdf);
            throw new \RuntimeException('PDF generation failed: empty output');
        }
        return $tmpPdf;
    }


    /** Simple HTML → PDF (without DevTools, just using a data URL) */
    public function renderHtml(string $html, array $flags = []): string
    {
        $tmpHtml = tempnam(sys_get_temp_dir(), 'html_') . '.html';
        if (file_put_contents($tmpHtml, $html) === false) {
            throw new \RuntimeException('Failed to write temp HTML file');
        }

        // TIP: allow local file references if your HTML uses relative paths
        $flags = array_merge(['--allow-file-access-from-files'], $flags);

        try {
            return $this->renderUrl('file://' . $tmpHtml, $flags);
        } finally {
            @unlink($tmpHtml);
        }
    }

    private function detectChrome(): string
    {
        $candidates = [
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/local/bin/google-chrome',
        ];
        foreach ($candidates as $bin) {
            if (is_executable($bin)) {
                return $bin;
            }
        }
        throw new RuntimeException('Chrome/Chromium binary not found');
    }

    private function tmpPdfPath(): string
    {
        return tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
    }

    private function buildCommand(string $url, array $flags): string
    {
        // Safe escaping; flags already contain their values
        $parts = [escapeshellcmd($this->chromeBin)];
        foreach ($flags as $f) {
            $parts[] = $f; // flags already form complete parameters
        }
        $parts[] = escapeshellarg($url) . ' 2>&1';
        return implode(' ', $parts);
    }

    private function run(string $cmd): void
    {
        exec($cmd, $out, $ret);
        if ($ret !== 0) {
            throw new RuntimeException("Chrome exit code $ret:\n" . implode("\n", $out));
        }
    }
}
