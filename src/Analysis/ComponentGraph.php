<?php

namespace PhpSeq\Analysis;

final class ComponentGraph
{
    private array $components = [];
    /** @var array<int,array{from:string,to:string,endpoints:array,label:?string}> */
    private array $edges = [];

    public function addComponent(string $name): void
    {
        $this->components[$name] = true;
    }

    /**
     * Add an edge. Endpoints is optional: an array of paths like ['/orders','/customers'].
     */
    public function addEdge(string $from, string $to, ?string $label = null, array $endpoints = []): void
    {
        $this->addComponent($from);
        $this->addComponent($to);
        $this->edges[] = [
            'from'      => $from,
            'to'        => $to,
            'label'     => $label,
            'endpoints' => $endpoints,
        ];
    }

    public function getComponents(): array
    {
        return array_keys($this->components);
    }

    public function getEdges(): array
    {
        return $this->edges;
    }

    public function edgesFromCounts(): array
    {
        $counts = [];
        foreach ($this->edges as $edge) {
            $key = $edge['from'].'::'.$edge['to'];
            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'from'      => $edge['from'],
                    'to'        => $edge['to'],
                    'label'     => $edge['label'],
                    'endpoints' => [],
                    'count'     => 0,
                ];
            }
            $counts[$key]['count']++;
            $counts[$key]['endpoints'] = array_unique(array_merge(
                $counts[$key]['endpoints'],
                $edge['endpoints'] ?? []
            ));
        }
        return array_values($counts);
    }

    public function merge(self $other): void
    {
        foreach ($other->getComponents() as $c) $this->addComponent($c);
        foreach ($other->getEdges() as $e) $this->edges[] = $e;
    }

    public function edgeCount(): int
    {
        return count($this->edges);
    }

    public function subgraphForComponent(string $component): self
    {
        $sub = new self();
        $sub->addComponent($component);
        foreach ($this->edges as $edge) {
            if ($edge['from'] === $component || $edge['to'] === $component) {
                $sub->addComponent($edge['from']);
                $sub->addComponent($edge['to']);
                $sub->edges[] = $edge;
            }
        }
        return $sub;
    }
}
