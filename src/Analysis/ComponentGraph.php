<?php

namespace PhpSeq\Analysis;

/**
 * Represents a graph of components and their communication edges.
 */
final class ComponentGraph
{
    /** @var array<string,bool> */
    private array $components = [];

    /** @var array<int,array{from:string,to:string,label:?string}> */
    private array $edges = [];

    /** @var array<string,int> */
    private array $classCounts = [];

    public function addComponent(string $name): void
    {
        $this->components[$name] = true;
        if (!isset($this->classCounts[$name])) {
            $this->classCounts[$name] = 0;
        }
    }

    public function addEdge(string $from, string $to, ?string $label = null): void
    {
        $this->addComponent($from);
        $this->addComponent($to);
        $this->edges[] = [
            'from'  => $from,
            'to'    => $to,
            'label' => $label,
        ];
    }

    /** Increment class count for a component */
    public function incrementClassCount(string $component): void
    {
        $this->addComponent($component);
        $this->classCounts[$component] = ($this->classCounts[$component] ?? 0) + 1;
    }

    /** Get class count for a component */
    public function classCount(string $component): int
    {
        return $this->classCounts[$component] ?? 0;
    }

    /** @return string[] */
    public function getComponents(): array
    {
        return array_keys($this->components);
    }

    /** @return array<int,array{from:string,to:string,label:?string}> */
    public function getEdges(): array
    {
        return $this->edges;
    }

    public function edgeCount(): int
    {
        return count($this->edges);
    }

    public function subgraphForComponent(string $component): self
    {
        $sub = new self();
        $sub->addComponent($component);
        $sub->classCounts[$component] = $this->classCounts[$component] ?? 0;

        foreach ($this->edges as $edge) {
            if ($edge['from'] === $component || $edge['to'] === $component) {
                $sub->addComponent($edge['from']);
                $sub->addComponent($edge['to']);
                $sub->edges[] = $edge;
                $sub->classCounts[$edge['from']] = $this->classCounts[$edge['from']] ?? 0;
                $sub->classCounts[$edge['to']] = $this->classCounts[$edge['to']] ?? 0;
            }
        }
        return $sub;
    }

    public function merge(self $other): void
    {
        foreach ($other->getComponents() as $c) {
            $this->addComponent($c);
            if (isset($other->classCounts[$c])) {
                $this->classCounts[$c] = ($this->classCounts[$c] ?? 0) + $other->classCounts[$c];
            }
        }
        foreach ($other->getEdges() as $e) {
            $this->edges[] = $e;
        }
    }

        /**
     * Returns edges aggregated by (from,to), with counts.
     *
     * @return array<int,array{from:string,to:string,count:int}>
     */
    public function edgesFromCounts(): array
    {
        $counts = [];
        foreach ($this->edges as $edge) {
            $key = $edge['from'] . '::' . $edge['to'];
            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'from'  => $edge['from'],
                    'to'    => $edge['to'],
                    'count' => 0,
                ];
            }
            $counts[$key]['count']++;
        }
        return array_values($counts);
    }

}
