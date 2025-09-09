<?php declare(strict_types=1);
namespace PhpSeq\Renderer;

use PhpSeq\Analysis\ComponentGraph;

final class ComponentUMLRenderer
{
    /**
     * @param 'counts'|'unique' $edgeLabelMode
     * @param 'aggregate'|'all' $edgeDetail
     */
    public function render(
        ComponentGraph $cg,
        string $title,
        string $caption,
        string $edgeLabelMode = 'counts',
        string $edgeDetail = 'aggregate',
        ?int $maxEdges = null
    ): string {
        $alias = static fn(string $name) => 'C_' . substr(md5($name), 0, 8);

        $out = [];
        $out[] = '@startuml components';
        if ($title !== '') $out[] = 'title ' . $this->esc($title);
        $out[] = 'skinparam componentStyle rectangle';
        $out[] = 'skinparam wrapWidth 220';
        $out[] = 'skinparam shadowing false';
        $out[] = 'hide stereotype';
        $out[] = '';

        foreach ($cg->getComponents() as $comp) {
            $label = $comp . "\n(" . $cg->classCount($comp) . " classes)";
            $out[] = sprintf('component "%s" as %s', $label, $alias($comp));
        }
        $out[] = '';

        if ($edgeDetail === 'aggregate') {
            $rows = [];
            foreach ($cg->getComponents() as $from) {
                $weights = $edgeLabelMode === 'unique' ? $cg->edgesFromUniqueCounts($from) : $cg->edgesFromCounts($from);
                foreach ($weights as $to => $w) {
                    $rows[] = ['from' => $from, 'to' => $to, 'w' => $w];
                }
            }
            usort($rows, fn($a,$b) => $b['w'] <=> $a['w']);
            if ($maxEdges !== null) $rows = array_slice($rows, 0, $maxEdges);
            foreach ($rows as $r) {
                $lbl = $edgeLabelMode === 'unique' ? ' unique edges' : ' calls';
                $out[] = sprintf('%s --> %s : %d%s', $alias($r['from']), $alias($r['to']), $r['w'], $r['w'] === 1 ? rtrim($lbl, 's') : $lbl);
            }
        } else {
            $pairs = $cg->allPairs();
            $edges = [];
            foreach ($pairs as $p) {
                $edges[] = [
                    'from' => $p['from'],
                    'to' => $p['to'],
                    's' => sprintf('%s ..> %s : %s::%s -> %s::%s', $alias($p['from']), $alias($p['to']), $p['fromClass'], $p['fromMethod'], $p['toClass'], $p['toMethod'])
                ];
            }
            usort($edges, fn($a,$b) => [$a['from'],$a['to'],$a['s']] <=> [$b['from'],$b['to'],$b['s']]);
            if ($maxEdges !== null) $edges = array_slice($edges, 0, $maxEdges);
            foreach ($edges as $e) $out[] = $e['s'];
        }

        if ($caption !== '') $out[] = 'caption ' . $this->esc($caption);
        $out[] = '@enduml';
        return implode("\n", $out) . "\n";
    }

    private function esc(string $s): string { return str_replace(["\r","\n"], [' ',' '], $s); }
}
