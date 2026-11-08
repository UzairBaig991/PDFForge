<?php
declare(strict_types=1);

namespace PDFForge\Services;

use RuntimeException;

final class PdfEngine
{
    public function executable(string $program): string
    {
        $engineName = match ($program) {
            'qpdf' => 'QPDF',
            'gs' => 'Ghostscript',
            default => 'PDF processing engine',
        };
        $setting = trim((string) app_config(match ($program) {
            'qpdf' => 'qpdf_path',
            'gs' => 'ghostscript_path',
            default => 'pdf_engine_path',
        }));
        if ($setting === '') {
            throw new RuntimeException($engineName . ' is not configured.');
        }

        $name = match ($program) {
            'gs' => PHP_OS_FAMILY === 'Windows' ? 'gswin64c.exe' : 'gs',
            default => $program . (PHP_OS_FAMILY === 'Windows' ? '.exe' : ''),
        };
        if (is_file($setting)) {
            $candidate = strcasecmp(basename($setting), $name) === 0
                ? $setting
                : dirname($setting) . DIRECTORY_SEPARATOR . $name;
        } else {
            $candidate = rtrim($setting, DIRECTORY_SEPARATOR . '/\\') . DIRECTORY_SEPARATOR . $name;
        }
        if (!is_file($candidate) || !is_executable($candidate)) {
            throw new RuntimeException($engineName . ' is not configured.');
        }
        return $candidate;
    }

    public function run(array $command, string $outputDirectory): int
    {
        if (!function_exists('proc_open')) {
            throw new RuntimeException('The PDF processing engine cannot be started on this server.');
        }

        $stdout = fopen($outputDirectory . DIRECTORY_SEPARATOR . 'engine.stdout', 'wb');
        $stderr = fopen($outputDirectory . DIRECTORY_SEPARATOR . 'engine.stderr', 'wb');
        if ($stdout === false || $stderr === false) {
            if (is_resource($stdout)) {
                fclose($stdout);
            }
            if (is_resource($stderr)) {
                fclose($stderr);
            }
            throw new RuntimeException('Temporary processing storage is unavailable.');
        }

        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => $stdout,
            2 => $stderr,
        ], $pipes, APP_ROOT);
        fclose($stdout);
        fclose($stderr);
        if (!is_resource($process)) {
            throw new RuntimeException('The PDF processing engine could not be started.');
        }

        fclose($pipes[0]);
        return proc_close($process);
    }
}
