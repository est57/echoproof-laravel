<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Replay;

use InvalidArgumentException;
use RuntimeException;

final class FixtureStore
{
    public const ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public function __construct(private readonly string $directory)
    {
    }

    public static function validId(string $id): bool
    {
        return preg_match(self::ID_PATTERN, $id) === 1;
    }

    /** @param  list<array>  $calls */
    public function putFixtures(string $id, array $calls): void
    {
        $this->write("fixtures-{$id}", $calls);
    }

    /** @return list<array> */
    public function takeFixtures(string $id): array
    {
        return $this->take("fixtures-{$id}");
    }

    /** @param  list<array>  $calls */
    public function putCaptured(string $id, array $calls): void
    {
        $this->write("outbound-{$id}", $calls);
    }

    /** @return list<array> */
    public function takeCaptured(string $id): array
    {
        return $this->take("outbound-{$id}");
    }

    private function write(string $name, array $calls): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException("cannot create {$this->directory}");
        }
        $path = $this->path($name);
        $json = json_encode(array_values($calls), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException("cannot write {$path}");
        }
        @chmod($path, 0600);
    }

    private function take(string $name): array
    {
        $path = $this->path($name);
        if (! is_file($path)) {
            return [];
        }
        $calls = json_decode((string) file_get_contents($path), true);
        @unlink($path);

        return is_array($calls) ? array_values($calls) : [];
    }

    private function path(string $name): string
    {
        $id = substr($name, strpos($name, '-') + 1);
        if (! self::validId($id)) {
            throw new InvalidArgumentException('invalid exchange id');
        }

        return $this->directory.DIRECTORY_SEPARATOR.$name.'.json';
    }
}
