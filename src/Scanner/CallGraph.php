<?php declare(strict_types=1);
namespace PhpSeq\Scanner;

final class CallGraph
{
    /** @var array<string,string> */
    private array $methods = []; // Class::method => visibility

    /** @var array<string,list<string>> */
    private array $edges = [];   // from => list of to

    /** @var array<string,string> */
    private array $classFiles = []; // Class => file path

    public function addMethod(string $class, string $method, string $visibility): void
    {
        $this->methods[$class . '::' . $method] = $visibility;
    }

    public function addCall(string $from, string $to): void
    {
        $this->edges[$from][] = $to;
    }

    public function mapClassToFile(string $class, string $file): void
    {
        $this->classFiles[$class] = $file;
    }

    /** @return array<string,string> */
    public function getAllMethods(): array
    {
        return $this->methods;
    }

    /** @return list<string> */
    public function getCalls(string $from): array
    {
        return $this->edges[$from] ?? [];
    }

    /** @return array<string,string> */
    public function getAllClassFiles(): array
    {
        return $this->classFiles;
    }

    public function getVisibility(string $method): string
    {
        return $this->methods[$method] ?? 'public';
    }
}
