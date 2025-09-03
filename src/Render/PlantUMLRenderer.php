<?php declare(strict_types=1);

namespace PhpSeq\Render;

use PhpSeq\Model\CallGraph;

final class PlantUMLRenderer
{
    /** @var string[] */
    private array $groupTopNs;

    /**
     * @param string[] $groupTopNs e.g. ['App','Domain']
     */
    public function __construct(array $groupTopNs = [])
    {
        $this->groupTopNs = $groupTopNs;
    }

    public function renderSequence(CallGraph $graph, string $entryFqn, int $maxDepth = 3): string
    {
        $participants = [];
        $lines = [];
        $visited = [];
        $depth = 0;

        $pushParticipant = function(string $class) use (&$participants) {
            if ($class === '' || $class === '?') return;
            $participants[$class] = true;
        };

        $dfs = function(string $fromFqn, int $level) use (&$dfs, $graph, &$lines, &$visited, $maxDepth, $pushParticipant)
        {
            if ($level > $maxDepth) return;
            $visited[$fromFqn] = true;
            $fromInfo = $graph->methodInfo($fromFqn);
            $fromClass = $fromInfo['class'] ?? explode('::', $fromFqn)[0] ?? '';
            $pushParticipant($fromClass);

            foreach ($graph->edges($fromFqn) as $edge) {
                $target = $edge['target'];
                $type = $edge['type'];
                $toClass = '';
                if (str_contains($target, '::')) {
                    $toClass = explode('::', $target)[0];
                } elseif (str_contains($target, '()')) {
                    $toClass = $target;
                }
                $pushParticipant($toClass);
                $label = $target;
                $lines[] = sprintf('%s -> %s: %s', $this->alias($fromClass), $this->alias($toClass), $label);
                if ($graph->hasMethod($target) && !($visited[$target] ?? false)) {
                    $dfs($target, $level + 1);
                }
            }
        };

        $dfs($entryFqn, 0);

        $uml = ["@startuml", "title " . $entryFqn];

        // Participants (grouped by top-level namespace if requested)
        if ($this->groupTopNs) {
            $groups = [];
            foreach (array_keys($participants) as $class) {
                $top = explode('\\', ltrim($class, '\\'))[0] ?? '';
                $groups[$top][] = $class;
            }
            foreach ($groups as $top=>$classes) {
                $uml[] = "box " . $top;
                foreach ($classes as $c) {
                    $uml[] = "participant " . $c . " as " . $this->alias($c);
                }
                $uml[] = "end box";
            }
        } else {
            foreach (array_keys($participants) as $class) {
                $uml[] = "participant " . $class . " as " . $this->alias($class);
            }
        }

        $uml[] = "";
        foreach ($lines as $l) $uml[] = $l;
        $uml[] = "@enduml";

        return implode("\n", $uml) . "\n";
    }

    private function alias(string $class): string
    {
        if ($class === '' || $class === '?') return 'Unknown';
        return 'P' . substr(md5($class), 0, 8);
    }
}
