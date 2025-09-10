<?php

namespace PhpSeq\Scanner;

use PhpSeq\Analysis\ComponentGraph;

/**
 * ProjectScanner builds a component-level communication graph.
 *
 * Instead of scanning classes, it detects repositories (components)
 * under the given root and wires known inter-component calls.
 *
 * Example: go54-website -> go54-api
 */
final class ProjectScanner
{
    private string $root;
    /** @var list<string> */
    private array $excludeGlobs = [];
    /** @var list<string> */
    private array $repoSrc = [];

    public function __construct()
    {
        // no dependencies
    }

    public function scanRoot(string $root, array $repoSrc, array $excludeGlobs = []): ComponentGraph
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->repoSrc = array_values(array_filter(array_map('trim', $repoSrc)));
        $this->excludeGlobs = $excludeGlobs;

        $graph = new ComponentGraph();

        if (!is_dir($this->root)) {
            throw new \RuntimeException("Source root not found: {$this->root}");
        }

        $it = new \FilesystemIterator($this->root, \FilesystemIterator::SKIP_DOTS);
        foreach ($it as $repoDir) {
            if (!$repoDir->isDir()) {
                continue;
            }
            $name = $repoDir->getFilename();
            if ($name[0] === '.' || $name === '.phpseq-cache') {
                continue;
            }

            // Each subfolder is a component
            $graph->addComponent($name);
        }

        // ------------------------------------------------------------------
        // Hard-coded known communications between components
        // TODO: later extend to detect real HTTP calls dynamically
        // ------------------------------------------------------------------
        if (in_array('go54-website', $graph->getComponents(), true)
            && in_array('go54-api', $graph->getComponents(), true)) {
            $graph->addEdge('go54-website', 'go54-api', 'HTTP calls');
        }

        return $graph;
    }
}
