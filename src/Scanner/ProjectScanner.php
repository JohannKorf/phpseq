<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\Error;

class ProjectScanner
{
    private string $src;

    public function __construct(string $src)
    {
        $this->src = $src;
    }

    public function scan(?array $entries = null, int $depth = 3): CallGraph
    {
        $graph = new CallGraph();

        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->src));
        foreach ($rii as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $code = file_get_contents($file->getPathname());
            try {
                $ast = $parser->parse($code);
            } catch (Error $e) {
                continue;
            }

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

            $collector = new MethodCallCollector($className);
            $traverser = new NodeTraverser();
            $traverser->addVisitor($collector);
            $traverser->traverse($ast);

            foreach ($collector->getMethods() as $method => $vis) {
                $graph->addMethod($className, $method, $vis);
            }
            foreach ($collector->getCalls() as $call) {
                $graph->addCall($call['from'], $call['to']);
            }
        }

        if ($entries) {
            foreach ($entries as $ep) {
                $graph->addEntryPoint($ep);
            }
        } else {
            foreach ($graph->getAllMethods() as $full => $vis) {
                if ($vis === 'public') {
                    $graph->addEntryPoint($full);
                }
            }
        }

        return $graph;
    }
}
