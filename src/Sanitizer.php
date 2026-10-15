<?php

declare(strict_types=1);

namespace Echoproof\Laravel;

use stdClass;

final class Sanitizer
{
    public const REDACTED = '[REDACTED]';

    /** @var array<string, true> */
    private array $headers = [];

    /** @var array<string, true> */
    private array $fields = [];

    /** @var list<string> */
    private array $patterns = [];

    /** @var array<string, true> Values redacted so far, erased everywhere by scrub(). */
    private array $secrets = [];

    /** Shorter values would erase ordinary text. */
    private const MIN_SECRET = 8;

    /**
     * @param  list<string>  $headers
     * @param  list<string>  $fields
     */
    public function __construct(array $headers, array $fields)
    {
        foreach ($headers as $name) {
            $this->headers[strtolower($name)] = true;
        }
        foreach ($fields as $field) {
            $field = strtolower($field);
            if (str_contains($field, '*')) {
                $this->patterns[] = $field;
            } else {
                $this->fields[$field] = true;
            }
        }
    }

    /**
     * Empty headers become an object so they encode as {} rather than [].
     *
     * @param  array<string, string>  $headers
     * @return array<string, string>|stdClass
     */
    public function headers(array $headers): array|stdClass
    {
        if ($headers === []) {
            return new stdClass;
        }
        foreach ($headers as $name => $value) {
            if (isset($this->headers[$name])) {
                $this->remember($value);
                $headers[$name] = self::REDACTED;
            }
        }

        return $headers;
    }

    public function url(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return $url;
        }
        $parts = [];
        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, null);
            $key = strtolower(urldecode((string) $name));
            $secret = $value !== null && $this->redacts($key, urldecode($value));
            if ($secret) {
                $this->remember(urldecode((string) $value));
            }
            $parts[] = $secret ? $name.'='.rawurlencode(self::REDACTED) : $pair;
        }

        return str_replace('?'.$query, '?'.implode('&', $parts), $url);
    }

    /** Collects secrets from a query string that stays as recorded, such as the inbound path. */
    public function query(string $url): void
    {
        $this->url($url);
    }

    /** Starts a new exchange: secrets from an earlier request never carry over. */
    public function reset(): void
    {
        $this->secrets = [];
    }

    /**
     * Erases every value redacted so far wherever else it appears, for example an API key a
     * third party echoes back in its response, then forgets them.
     */
    public function scrub(array $data): array
    {
        $secrets = array_keys($this->secrets);
        $this->secrets = [];
        if ($secrets === []) {
            return $data;
        }
        usort($secrets, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        return $this->erase($data, $secrets);
    }

    private function erase(mixed $value, array $secrets): mixed
    {
        if (is_string($value)) {
            return str_replace($secrets, self::REDACTED, $value);
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->erase($item, $secrets), $value);
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $child) {
                $value->{$key} = $this->erase($child, $secrets);
            }
        }

        return $value;
    }

    private function remember(mixed $value): void
    {
        if (! is_string($value)) {
            return;
        }
        $value = trim($value);
        if ($value === self::REDACTED) {
            return;
        }
        if (strlen($value) >= self::MIN_SECRET) {
            $this->secrets[$value] = true;
        }
        foreach (preg_split('/\s+/', $value) ?: [] as $part) {
            if (strlen($part) >= self::MIN_SECRET) {
                $this->secrets[$part] = true;
            }
        }
    }

    public function body(string|array $body): string|array
    {
        if (is_array($body) && array_key_exists('json', $body)) {
            $body['json'] = $this->value($body['json']);
        }

        return $body;
    }

    private function value(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $child) {
                if ($this->redacts(strtolower((string) $key), $child)) {
                    $this->remember($child);
                    $value->{$key} = self::REDACTED;
                } else {
                    $value->{$key} = $this->value($child);
                }
            }

            return $value;
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item) => $this->value($item), $value);
        }

        return $value;
    }

    private function redacts(string $key, mixed $value): bool
    {
        if (isset($this->fields[$key])) {
            return true;
        }
        if (! is_string($value)) {
            return false;
        }
        foreach ($this->patterns as $pattern) {
            if (self::wildcard($pattern, $key)) {
                return true;
            }
        }

        return false;
    }

    private static function wildcard(string $pattern, string $text): bool
    {
        $regex = '/^'.implode('.*', array_map(fn (string $part) => preg_quote($part, '/'), explode('*', $pattern))).'$/s';

        return preg_match($regex, $text) === 1;
    }
}
