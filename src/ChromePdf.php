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
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('Invalid URL');
        }
        $tmpPdf = $this->tmpPdfPath();

        $defaultFlags = [
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-pdf-header-footer',
            // optionally add more:
            // '--force-color-profile=srgb',
            // '--lang=en-US',
        ];

        $allFlags = array_merge($defaultFlags, $flags, [
            '--print-to-pdf=' . $tmpPdf,
        ]);

        $cmd = $this->buildCommand($url, $allFlags);
        $this->run($cmd);

        if (!is_file($tmpPdf) || filesize($tmpPdf) === 0) {
            @unlink($tmpPdf);
            throw new RuntimeException('PDF generation failed: empty output');
        }

        return $tmpPdf; // caller should read it and delete afterwards
    }

    /** Simple HTML → PDF (without DevTools, just using a data URL) */
    public function renderHtml(string $html, array $flags = []): string
    {
        $dataUrl = 'data:text/html;charset=utf-8,' . rawurlencode($html);
        return $this->renderUrl($dataUrl, $flags);
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
