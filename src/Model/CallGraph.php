<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

/**
 * Represents a call graph extracted from source code.
 */
class CallGraph
{
    /**
     * @var array<int, array{
     *     fromClass:?string,
     *     fromMethod:?string,
     *     fromVisibility:?string,
     *     type:string,
     *     caller:?string,
     *     method:string
     * }>
     */
    private array $edges = [];

    /**
     * Add a call edge to the graph.
     *
     * @param ?string $fromClass
     * @param ?string $fromMethod
     * @param ?string $fromVisibility
     * @param string  $type
     * @param ?string $caller
     * @param string  $method
     */
    public function addCall(
        ?string $fromClass,
        ?string $fromMethod,
        ?string $fromVisibility,
        string $type,
        ?string $caller,
        string $method
    ): void {
        $this->edges[] = [
            'fromClass'      => $fromClass,
            'fromMethod'     => $fromMethod,
            'fromVisibility' => $fromVisibility,
            'type'           => $type,
            'caller'         => $caller,
            'method'         => $method,
        ];
    }

    /**
     * Bulk add calls from MethodCallCollector.
     *
     * @param array<int, array{
     *     fromClass:?string,
     *     fromMethod:?string,
     *     fromVisibility:?string,
     *     type:string,
     *     caller:?string,
     *     method:string
     * }> $calls
     */
    public function addCalls(array $calls): void
    {
        foreach ($calls as $call) {
            $this->addCall(
                $call['fromClass'] ?? null,
                $call['fromMethod'] ?? null,
                $call['fromVisibility'] ?? null,
                $call['type'],
                $call['caller'] ?? null,
                $call['method']
            );
        }
    }

    /**
     * Get all call edges in the graph.
     *
     * @return array<int, array{
     *     fromClass:?string,
     *     fromMethod:?string,
     *     fromVisibility:?string,
     *     type:string,
     *     caller:?string,
     *     method:string
     * }>
     */
    public function getEdges(): array
    {
        return $this->edges;
    }

    /**
     * Return a unique set of nodes (class::method signatures).
     *
     * @return string[]
     */
    public function getNodes(): array
    {
        $nodes = [];
        foreach ($this->edges as $edge) {
            if ($edge['fromClass'] && $edge['fromMethod']) {
                $nodes[] = $edge['fromClass'] . '::' . $edge['fromMethod'];
            }
            if ($edge['caller'] && $edge['method']) {
                $nodes[] = $edge['caller'] . '::' . $edge['method'];
            } elseif ($edge['method']) {
                $nodes[] = $edge['method'];
            }
        }
        return array_values(array_unique($nodes));
    }

    /**
     * Filter edges by originating class/method (entry point).
     *
     * @param string $class
     * @param string $method
     * @return array<int, array{
     *     fromClass:?string,
     *     fromMethod:?string,
     *     fromVisibility:?string,
     *     type:string,
     *     caller:?string,
     *     method:string
     * }>
     */
    public function getEdgesFrom(string $class, string $method): array
    {
        return array_filter(
            $this->edges,
            fn ($edge) => $edge['fromClass'] === $class && $edge['fromMethod'] === $method
        );
    }
}
