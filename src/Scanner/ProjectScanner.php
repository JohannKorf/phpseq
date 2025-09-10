<?php

namespace PhpSeq\Scanner;

use PhpSeq\Analysis\ComponentGraph;

/**
 * Scans repos under a root and builds component-level edges.
 */
final class ProjectScanner
{
    private string $root;
    private array $excludeGlobs = [];
    private array $repoSrc = [];
    private bool $showEndpoints;
    private bool $ignoreNoise;

    private array $noiseHosts = [
        'www.w3.org',
        'schema.org',
        'fonts.googleapis.com',
        'cdnjs.cloudflare.com',
    ];

    public function __construct(bool $showEndpoints = false, bool $ignoreNoise = false)
    {
        $this->showEndpoints = $showEndpoints;
        $this->ignoreNoise   = $ignoreNoise;
    }

    public function scanRoot(string $root, array $repoSrc, array $excludeGlobs = []): ComponentGraph
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->repoSrc = $repoSrc;
        $this->excludeGlobs = $excludeGlobs;

        $graph = new ComponentGraph();

        if (!is_dir($this->root)) {
            throw new \RuntimeException("Source root not found: {$this->root}");
        }

        // 1. Collect components
        $components = [];
        $it = new \FilesystemIterator($this->root, \FilesystemIterator::SKIP_DOTS);
        foreach ($it as $repoDir) {
            if (!$repoDir->isDir()) continue;
            $name = $repoDir->getFilename();
            if ($name[0] === '.' || $name === '.phpseq-cache') continue;
            $components[] = $name;
            $graph->addComponent($name);
        }

        // 2. If go54-api exists, scan endpoints
        $apiEndpoints = [];
        if (in_array('go54-api', $components, true)) {
            $scanner = new ApiEndpointScanner();
            $apiEndpoints = $scanner->scan($this->root . DIRECTORY_SEPARATOR . 'go54-api');
        }

        // 3. Naive detection of edges: search for component names or URLs in files
        foreach ($components as $comp) {
            $repoPath = $this->root . DIRECTORY_SEPARATOR . $comp;
            $this->scanRepo($graph, $comp, $repoPath, $components, $apiEndpoints);
        }

        return $graph;
    }

    private function scanRepo(ComponentGraph $graph, string $fromComponent, string $repoPath, array $allComponents, array $apiEndpoints): void
    {
        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($repoPath, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($rii as $file) {
            if (!$file->isFile()) continue;
            $text = @file_get_contents($file->getPathname());
            if ($text === false) continue;

            // Search for URLs
            if (preg_match_all('/https?:\/\/[^\s"\'<>]+/i', $text, $m)) {
                foreach ($m[0] as $url) {
                    $host = parse_url($url, PHP_URL_HOST) ?? '';
                    $path = parse_url($url, PHP_URL_PATH) ?? '/';
                    if (!$host) continue;

                    // internal?
                    $target = null;
                    foreach ($allComponents as $c) {
                        if (stripos($host, $c) !== false) {
                            $target = $c;
                            break;
                        }
                    }

                    if ($target) {
                        $endpoints = [];
                        if ($this->showEndpoints && $target === 'go54-api') {
                            foreach ($apiEndpoints as $ep) {
                                if (stripos($path, $ep) !== false) {
                                    $endpoints[] = $ep;
                                }
                            }
                        }
                        $graph->addEdge($fromComponent, $target, 'calls', $endpoints);
                    } else {
                        if ($this->ignoreNoise && in_array($host, $this->noiseHosts, true)) {
                            continue;
                        }
                        $graph->addEdge($fromComponent, $host, 'external');
                    }
                }
            }
        }
    }
}
