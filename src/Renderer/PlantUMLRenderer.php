<?php declare(strict_types=1);

namespace PhpSeq\Renderer;

use PhpSeq\Scanner\CallGraph;

class PlantUMLRenderer
{
    public function render(CallGraph $graph, string $entry, int $depth = 3): string
    {
        $out = ["@startuml", "autonumber"];

        $methods = $graph->getAllMethods();
        $classes = [];
        foreach ($methods as $m => $vis) {
            [$cls, $method] = explode('::', $m);
            $classes[$cls][$method] = $vis;
        }

        foreach ($classes as $cls => $ms) {
            $out[] = "participant \"$cls\" as " . $this->alias($cls) . " << (C,#ADD1B2) >>";
            foreach ($ms as $method => $vis) {
                $sym = $vis === 'public' ? '+' : ($vis === 'protected' ? '#' : '-');
                $out[] = "note right of " . $this->alias($cls) . " : " . $sym . $method . "()";
            }
        }

        $visited = [];
        $this->renderCalls($graph, $entry, $depth, $out, $visited);

        $out[] = "@enduml";
        return implode("\n", $out);
    }

    private function renderCalls(CallGraph $graph, string $method, int $depth, array &$out, array &$visited): void
    {
        if ($depth <= 0 || in_array($method, $visited, true)) return;
        $visited[] = $method;

        [$cls, $meth] = explode('::', $method);
        foreach ($graph->getCalls($method) as $to) {
            $target = null;
            foreach ($graph->getAllMethods() as $m => $_) {
                if (str_ends_with($m, '::' . $to)) {
                    $target = $m;
                    break;
                }
            }
            if ($target) {
                [$tcls, $tmeth] = explode('::', $target);
                $out[] = $this->alias($cls) . " -> " . $this->alias($tcls) . " : " . $target;
                $this->renderCalls($graph, $target, $depth - 1, $out, $visited);
            }
        }
    }

    private function alias(string $cls): string
    {
        return str_replace('\\', '_', $cls);
    }
}
