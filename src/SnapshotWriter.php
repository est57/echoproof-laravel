<?php

declare(strict_types=1);

namespace Echoproof\Laravel;

use RuntimeException;

final class SnapshotWriter
{
    public function __construct(private readonly string $path)
    {
    }

    public function append(array $exchange): void
    {
        $line = json_encode(
            $exchange,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        )."\n";
        $directory = dirname($this->path);
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException("cannot create {$directory}");
        }
        $created = ! file_exists($this->path);
        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("cannot write {$this->path}");
        }
        if ($created) {
            @chmod($this->path, 0600);
        }
    }
}
