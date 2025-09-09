<?php declare(strict_types=1);
namespace PhpSeq\Util;

final class ComposerNameCache
{
    private string $cacheDir;
    private string $cacheFile;
    /** @var array<string,string> */
    private array $map = [];

    public function __construct(string $root)
    {
        $this->cacheDir = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.phpseq-cache';
        $this->cacheFile = $this->cacheDir . DIRECTORY_SEPARATOR . 'composerNames.json';
        if (is_file($this->cacheFile)) {
            $data = json_decode((string)@file_get_contents($this->cacheFile), true);
            if (is_array($data)) $this->map = $data;
        }
    }

    public function save(): void
    {
        if (!is_dir($this->cacheDir)) @mkdir($this->cacheDir, 0777, true);
        @file_put_contents($this->cacheFile, json_encode($this->map, JSON_PRETTY_PRINT));
    }

    public function set(string $repoDir, string $name): void { $this->map[$repoDir] = $name; }
    public function get(string $repoDir): ?string { return $this->map[$repoDir] ?? null; }
}
