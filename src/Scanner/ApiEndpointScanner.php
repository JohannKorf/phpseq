<?php

namespace PhpSeq\Scanner;

/**
 * Simple scanner for API endpoints in go54-api.
 */
final class ApiEndpointScanner
{
    /**
     * Scan a directory for PHP route definitions and return endpoint paths.
     *
     * @param string $root Path to repo root (e.g., go54-api).
     * @return string[] List of endpoint paths like "/orders".
     */
    public function scan(string $root): array
    {
        $patterns = [
            '/Route::(get|post|put|patch|delete)\(\s*[\'"](?P<path>\/[^\'"]*)/i',
            '/->(get|post|put|patch|delete)\(\s*[\'"](?P<path>\/[^\'"]*)/i',
            '/#\[Route\((?:path\s*:\s*)?[\'"](?P<path>\/[^\'"]*)/i',
            '/@Route\(\s*[\'"](?P<path>\/[^\'"]*)/i',
            '/\$app->(get|post|put|patch|delete)\(\s*[\'"](?P<path>\/[^\'"]*)/i',
        ];

        $endpoints = [];

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if (!$file->isFile() || substr($file->getExtension(), 0, 3) !== 'php') continue;
            $text = @file_get_contents($file->getPathname());
            if ($text === false) continue;
            foreach ($patterns as $pat) {
                if (preg_match_all($pat, $text, $m)) {
                    foreach ($m['path'] as $p) {
                        $endpoints[] = $p;
                    }
                }
            }
        }

        return array_values(array_unique($endpoints));
    }
}
