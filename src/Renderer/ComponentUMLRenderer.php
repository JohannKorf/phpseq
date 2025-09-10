<?php

namespace PhpSeq\Renderer;

use PhpSeq\Analysis\ComponentGraph;

/**
 * Renders a ComponentGraph into a PlantUML diagram.
 */
final class ComponentUMLRenderer
{
    private bool $preferComposer;

    public function __construct(bool $preferComposer = false)
    {
        $this->preferComposer = $preferComposer;
    }

    /**
     * Render the given component graph to PlantUML.
     */
    public function render(
        ComponentGraph $graph,
        string $title,
        string $caption,
        string $edgeLabel,
        string $edgeDetail,
        ?int $maxEdges
    ): string {
        $lines = [];
        $lines[] = "@startuml";
        $lines[] = "title $title";

        // Participants (components)
        foreach ($graph->getComponents() as $name) {
            $safe = $this->sanitize($name);
            $lines[] = "component \"$name\" as $safe";
        }

        $lines[] = "";

        // Edges
        if ($edgeLabel === 'counts' || $edgeDetail === 'aggregate') {
            $edges = $graph->edgesFromCounts();
            foreach ($edges as $edge) {
                $from = $this->sanitize($edge['from']);
                $to   = $this->sanitize($edge['to']);
                $label = (string)($edge['count'] ?? '');
                $lines[] = "$from --> $to : $label";
            }
        } else {
            foreach ($graph->getEdges() as $edge) {
                $from = $this->sanitize($edge['from']);
                $to   = $this->sanitize($edge['to']);
                $label = $edge['label'] ?? '';
                $lines[] = "$from --> $to" . ($label ? " : $label" : "");
            }
        }

        if ($caption !== '') {
            $lines[] = "caption $caption";
        }

        $lines[] = "@enduml";
        return implode("\n", $lines);
    }

    private function sanitize(string $name): string
    {
        // turn repo names into valid PlantUML identifiers
        return preg_replace('/[^A-Za-z0-9_]/', '_', $name);
    }
}
