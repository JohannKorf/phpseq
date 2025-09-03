<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpSeq\Model\CallGraph;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use SplFileInfo;

final class ProjectScanner
{
    public function __construct(
        private readonly string $srcDir,
        private readonly bool $includeVendor = false,
        private readonly int $maxNodes = 500
    ) {}

    public function scan(): CallGraph
    {
        $graph = new CallGraph();
        $lexer = new Lexer\Emulative(['usedAttributes' => ['comments', 'startLine', 'endLine']]);
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);

        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->srcDir));
        $phpFiles = new RegexIterator($rii, '/^.+\.php$/i', RegexIterator::GET_MATCH);

        $count = 0;
        foreach ($phpFiles as $files) {
            foreach ($files as $file) {
                if (!$this->includeVendor() && str_contains($file, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $code = @file_get_contents($file);
                if ($code === false) continue;
                try {
                    $ast = $parser->parse($code);
                } catch (Error $e) {
                    // skip broken files
                    continue;
                }

                $traverser = new NodeTraverser();
                $traverser->addVisitor(new NameResolver());
                $collector = new MethodCallCollector($graph, $file);
                $traverser->addVisitor($collector);
                $traverser->traverse($ast ?? []);

                $count++;
                if ($count > $this->maxNodes) break 2;
            }
        }

        return $graph;
    }

    private function includeVendor(): bool
    {
        return $this->includeVendor;
    }
}
