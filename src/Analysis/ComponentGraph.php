<?php declare(strict_types=1);
namespace PhpSeq\Analysis;

final class ComponentGraph
{
    /** @var array<string, array{classes: array<string,bool>}> */
    private array $components = [];

    /** @var array<string, array<string,int>> */
    private array $edgeCounts = []; // from -> to -> total count

    /** @var array<string, array<string, array<string,bool>>> */
    private array $edgeUnique = []; // from -> to -> ('ClassA::m->ClassB::n' => true)

    /** @var array<int, array{from:string,to:string,fromClass:string,toClass:string,fromMethod:string,toMethod:string}> */
    private array $edgeAllPairs = []; // list of individual pairs for detailed view

    public function addClassToComponent(string $component, string $class): void
    {
        if (!isset($this->components[$component])) {
            $this->components[$component] = ['classes' => []];
        }
        $this->components[$component]['classes'][$class] = true;
    }

    public function addEdge(string $fromComponent, string $toComponent, string $fromMethod, string $toMethod): void
    {
        if ($fromComponent === '' || $toComponent === '') return;
        // counts
        $this->edgeCounts[$fromComponent][$toComponent] = ($this->edgeCounts[$fromComponent][$toComponent] ?? 0) + 1;

        // unique
        $k = $fromMethod . '->' . $toMethod;
        $this->edgeUnique[$fromComponent][$toComponent][$k] = true;

        // detailed pairs
        [$fc] = explode('::', $fromMethod, 2);
        [$tc] = explode('::', $toMethod, 2);
        $fm = explode('::', $fromMethod, 2)[1] ?? '';
        $tm = explode('::', $toMethod, 2)[1] ?? '';
        $this->edgeAllPairs[] = [
            'from' => $fromComponent,
            'to' => $toComponent,
            'fromClass' => $fc,
            'toClass' => $tc,
            'fromMethod' => $fm,
            'toMethod' => $tm,
        ];
    }

    /** @return string[] */
    public function getComponents(): array
    {
        return array_keys($this->components);
    }

    public function classCount(string $component): int
    {
        return isset($this->components[$component]) ? count($this->components[$component]['classes']) : 0;
    }

    /** @return array<string,int> */
    public function edgesFromCounts(string $component): array
    {
        return $this->edgeCounts[$component] ?? [];
    }

    /** @return array<string,int> */
    public function edgesFromUniqueCounts(string $component): array
    {
        $out = [];
        if (!isset($this->edgeUnique[$component])) return $out;
        foreach ($this->edgeUnique[$component] as $to => $set) {
            $out[$to] = count($set);
        }
        return $out;
    }

    /** @return array<int, array{from:string,to:string,fromClass:string,toClass:string,fromMethod:string,toMethod:string}> */
    public function allPairs(): array
    {
        return $this->edgeAllPairs;
    }
}
