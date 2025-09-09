<?php declare(strict_types=1);
namespace PhpSeq\Renderer;

use PhpSeq\Scanner\CallGraph;

final class PlantUMLRenderer
{
    public function render(CallGraph $graph, string $method, int $depth = 3): string
    {
        $alias = str_replace(['\\','::'], '_', $method);
        $title = 'Sequence Diagram for ' . $method;
        $caption = 'Generated on ' . date('Y-m-d H:i:s');

        $out = ["@startuml $alias", "title " . $title, "autonumber"];

        $visited = [];
        $calls = [];
        $this->walk($graph, $method, $depth, $visited, $calls);

        $classes = [];
        foreach (array_keys($visited) as $m) {
            [$cls, $meth] = explode('::', $m);
            $classes[$cls][$meth] = $graph->getVisibility($m);
        }
        foreach ($classes as $cls => $ms) {
            $out[] = 'participant "' . $cls . '" as ' . $this->alias($cls) . ' << (C,#ADD1B2) >>';
            foreach ($ms as $mname => $vis) {
                $sym = $vis === 'public' ? '+' : ($vis === 'protected' ? '#' : '-');
                $out[] = 'note right of ' . $this->alias($cls) . ' : ' . $sym . $mname . '()';
            }
        }

        foreach ($calls as [$from, $to]) {
            [$fcls] = explode('::', $from);
            [$tcls] = explode('::', $to);
            $out[] = $this->alias($fcls) . ' -> ' . $this->alias($tcls) . ' : ' . $to;
        }

        $out[] = 'caption ' . $caption;
        $out[] = '@enduml';
        return implode("\n", $out) . "\n";
    }

    private function walk(CallGraph $graph, string $method, int $depth, array &$visited, array &$calls): void
    {
        if ($depth < 0 || isset($visited[$method])) return;
        $visited[$method] = true;
        foreach ($graph->getCalls($method) as $to) {
            $calls[] = [$method, $to];
            $this->walk($graph, $to, $depth - 1, $visited, $calls);
        }
    }

    private function alias(string $cls): string
    {
        return str_replace('\\', '_', $cls);
    }
}
