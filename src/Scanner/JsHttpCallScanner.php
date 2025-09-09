<?php
declare(strict_types=1);

namespace PhpSeq\Scanner;

/**
 * Extremely lightweight JS/TS scanner that looks for axios and fetch calls.
 * Heuristic based (fast) and safe to run on large trees.
 */
final class JsHttpCallScanner
{
    /**
     * @param string $root         Path to JS/TS project (e.g. Next.js).
     * @param string $apiBaseVar   Env var name used for axios baseURL (e.g. NEXT_PUBLIC_API_URL).
     * @return list<array{method:string,path:string,file:string,line:int}>
     */
    public static function scan(string $root, string $apiBaseVar = 'NEXT_PUBLIC_API_URL'): array
    {
        $calls = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $fileNo = 0;
        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, ['js','jsx','ts','tsx'], true)) continue;

            $code = @file_get_contents($f->getPathname());
            if ($code === false) continue;

            $lines = preg_split('/\R/', $code) ?: [$code];

            foreach ($lines as $ln => $line) {
                // axios.get("/foo"), axios.post(`/bar`)
                if (preg_match_all('/\baxios\.(get|post|put|delete|patch|head|options)\s*\(\s*([\'"`])([^\'"`]+)\2/mi', $line, $m, PREG_SET_ORDER)) {
                    foreach ($m as $mm) {
                        $calls[] = ['method' => $mm[1], 'path' => self::normalizePath($mm[3]), 'file' => $f->getPathname(), 'line' => $ln+1];
                    }
                }

                // instance.get("/foo") where instance likely came from axios.create(...)
                if (preg_match_all('/\b([A-Za-z_][A-Za-z0-9_]*)\.(get|post|put|delete|patch|head|options)\s*\(\s*([\'"`])([^\'"`]+)\3/mi', $line, $m, PREG_SET_ORDER)) {
                    foreach ($m as $mm) {
                        // best-effort: accept it as an HTTP call
                        $calls[] = ['method' => $mm[2], 'path' => self::normalizePath($mm[4]), 'file' => $f->getPathname(), 'line' => $ln+1];
                    }
                }

                // fetch("/foo")
                if (preg_match_all('/\bfetch\s*\(\s*([\'"`])([^\'"`]+)\1/mi', $line, $m, PREG_SET_ORDER)) {
                    foreach ($m as $mm) {
                        $calls[] = ['method' => 'GET', 'path' => self::normalizePath($mm[2]), 'file' => $f->getPathname(), 'line' => $ln+1];
                    }
                }
            }

            $fileNo++;
        }

        return $calls;
    }

    private static function normalizePath(string $p): string
    {
        // Strip host if present, keep path/query
        if (preg_match('#https?://[^/]+(/.*)$#i', $p, $m)) {
            $p = $m[1];
        }
        if ($p === '') $p = '/';
        if ($p[0] !== '/') $p = '/' . $p;
        // Remove trailing spaces
        return rtrim($p);
    }
}
