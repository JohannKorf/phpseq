<?php declare(strict_types=1);

namespace PhpSeq\Scanner;

class CallGraph
{
    private array $methods = []; // class::method => visibility
    private array $calls = [];   // from => [to]
    private array $entries = [];

    public function addMethod(string $class, string $method, string $visibility): void
    {
        $this->methods[$class . '::' . $method] = $visibility;
    }

    public function addCall(string $from, string $to): void
    {
        $this->calls[$from][] = $to;
    }

    public function addEntryPoint(string $method): void
    {
        $this->entries[] = $method;
    }

    public function getEntryPoints(): array
    {
        return array_unique($this->entries);
    }

    public function getAllMethods(): array
    {
        return $this->methods;
    }

    public function getVisibility(string $method): string
    {
        return $this->methods[$method] ?? 'public';
    }

    public function getCalls(string $from): array
    {
        return $this->calls[$from] ?? [];
    }
}
