<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\Error;

final class ProjectScanner
{
    private string $root;
    /** @var list<string> */
    private array $excludeGlobs = [];
    /** @var list<string> */
    private array $repoSrc = []; // e.g. ['src','app','lib']

    public function __construct(string $root) { $this->root = rtrim($root, DIRECTORY_SEPARATOR); }

    /** @param list<string> $excludeGlobs */
    public function setExcludeGlobs(array $excludeGlobs): void { $this->excludeGlobs = $excludeGlobs; }
    /** @param list<string> $repoSrc */
    public function setRepoSrc(array $repoSrc): void { $this->repoSrc = $repoSrc; }

    public function scan(?array $entries = null, int $depth = 3): CallGraph
    {
        $graph = new CallGraph();
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $rootIter = new \FilesystemIterator($this->root, \FilesystemIterator::SKIP_DOTS);
        $rootDir = $this->expandPath($this->root);
        if (!is_dir($rootDir)) {
            throw new \RuntimeException(sprintf(
                "Source root '%s' does not exist (expanded to '%s').",
                $this->root, $rootDir
            ));
        }
        $rootIter = new \FilesystemIterator($rootDir, \FilesystemIterator::SKIP_DOTS);
        foreach ($rootIter as $repoDir) {
            if (!$repoDir->isDir()) continue;

            $scanFolders = [];
            if ($this->repoSrc) {
                foreach ($this->repoSrc as $sub) {
                    $p = $repoDir->getPathname() . DIRECTORY_SEPARATOR . $sub;
                    if (is_dir($p)) $scanFolders[] = $p;
                }
            } else {
                $scanFolders[] = $repoDir->getPathname();
            }

            foreach ($scanFolders as $folder) {
                $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $file) {
                    if (!$file->isFile()) continue;
                    if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) !== 'php') continue;

                    $rel = substr($file->getPathname(), strlen($this->root) + 1);
                    if ($this->isExcluded($rel)) continue;

                    $code = @file_get_contents($file->getPathname());
                    if ($code === false) continue;

                    try { $ast = $parser->parse($code); } catch (Error $e) { continue; }
                    if (!$ast) continue;

                    $className = null;
                    foreach ($ast as $node) {
                        if ($node instanceof \PhpParser\Node\Stmt\Namespace_) {
                            foreach ($node->stmts as $stmt) {
                                if ($stmt instanceof \PhpParser\Node\Stmt\Class_) {
                                    $className = ($node->name ? $node->name->toString() . '\\' : '') . $stmt->name->toString();
                                }
                            }
                        }
                    }
                    if (!$className) continue;

                    $graph->mapClassToFile($className, $file->getPathname());

                    $collector = new MethodCallCollector($className);
                    $traverser = new NodeTraverser();
                    $traverser->addVisitor($collector);
                    $traverser->traverse($ast);

                    foreach ($collector->getMethods() as $method => $vis) {
                        [$cls, $m] = explode('::', $method);
                        $graph->addMethod($cls, $m, $vis);
                    }
                    foreach ($collector->getCalls() as $c) {
                        $graph->addCall($c['from'], $c['to']);
                    }
                }
            }
        }
        return $graph;
    }

    private function isExcluded(string $relativePath): bool
    {
        foreach ($this->excludeGlobs as $g) {
            if (fnmatch($g, $relativePath)) return true;
        }
        return false;
    }

    /**
    * Expand ~, $VARS, ${VARS}, strip file:// and normalize.
    */
    private function expandPath(string $path): string
    {
        $path = trim($path);
        // Expand tilde
        if ($path === '~' || str_starts_with($path, '~/')) {
            $home = getenv('HOME') ?: getenv('USERPROFILE') ?: '';
            if ($home !== '') {
                $path = $home . substr($path, 1);
            }
        }
        // Expand $VAR and ${VAR}
        $path = preg_replace_callback('/\\$(\\w+)|\\$\\{([^}]+)\\}/', function ($m) {
            $var = $m[1] ?? $m[2];
            $val = getenv($var);
            return $val === false ? $m[0] : $val;
        }, $path);
        // Strip file:// and normalize; keep non-existent paths as-is
        $noScheme = preg_replace('#^file://#', '', $path);
        $real = realpath($noScheme);
        return $real !== false ? $real : $noScheme;
    }

}