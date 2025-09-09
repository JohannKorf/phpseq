<?php
declare(strict_types=1);

namespace PhpSeq\Scanner;

/**
 * Heuristic PHP API endpoint scanner.
 * Supports common frameworks (Laravel, Slim, Symfony attributes/annotations).
 */
final class ApiEndpointScanner
{
    /**
     * @param string $root Root directory that contains one or more repos.
     * @return array{string: array{string: array<string,bool>}} map[method][path] => true
     */
    public static function scan(string $root): array
    {
        $routes = [];
        if (!is_dir($root)) return $routes;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $f) {
            if (!$f->isFile()) continue;
            $ext = strtolower(pathinfo($f->getFilename(), PATHINFO_EXTENSION));
            if ($ext !== 'php') continue;

            $code = @file_get_contents($f->getPathname());
            if ($code === false) continue;

            // Laravel: Route::get('/path'...), Route::post('/x'...)
            if (preg_match_all('/\bRoute::(get|post|put|patch|delete|options|any)\s*\(\s*([\'\"])(\/[^\'\"]*)\2/mi', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $method = strtoupper($mm[1] === 'any' ? 'GET' : $mm[1]);
                    $path = $mm[3];
                    $routes[$method][$path] = true;
                }
            }

            // Slim: $app->get('/path', ...)
            if (preg_match_all('/\$app->\s*(get|post|put|patch|delete|options)\s*\(\s*([\'\"])(\/[^\'\"]*)\2/mi', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $method = strtoupper($mm[1]);
                    $path = $mm[3];
                    $routes[$method][$path] = true;
                }
            }

            // Symfony attribute: #[Route(path: '/path', methods: ['GET'])]
            if (preg_match_all('/#\[\s*Route\s*\(([^\)]*)\)\s*\]/mi', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $args = $mm[1];
                    if (preg_match('/path\s*:\s*([\'\"])(\/[^\'\"]*)\1/i', $args, $pm)) {
                        $path = $pm[2];
                        $methods = ['GET'];
                        if (preg_match('/methods\s*:\s*\{?\s*([^\}]*)\s*\}?/i', $args, $mm2)) {
                            $raw = strtoupper($mm2[1]);
                            $methods = preg_split('/[^A-Z]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: ['GET'];
                        }
                        foreach ($methods as $method) {
                            $routes[$method][$path] = true;
                        }
                    }
                }
            }

            // Symfony annotation: @Route("/path", methods={"GET","POST"})
            if (preg_match_all('/@Route\s*\(\s*([\'\"])(\/[^\'\"]*)\1\s*,?\s*([^\)]*)\)/mi', $code, $m, PREG_SET_ORDER)) {
                foreach ($m as $mm) {
                    $path = $mm[2];
                    $methods = ['GET'];
                    $tail = $mm[3];
                    if (preg_match('/methods\s*=\s*\{([^\}]*)\}/i', $tail, $mm2)) {
                        $raw = strtoupper($mm2[1]);
                        $methods = preg_split('/[^A-Z]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: ['GET'];
                    }
                    foreach ($methods as $method) {
                        $routes[$method][$path] = true;
                    }
                }
            }
        }

        return $routes;
    }
}
