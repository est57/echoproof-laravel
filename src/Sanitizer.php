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
            $parts[] = $value !== null && $this->redacts($key, urldecode($value))
                ? $name.'='.rawurlencode(self::REDACTED)
                : $pair;
        }

        return str_replace('?'.$query, '?'.implode('&', $parts), $url);
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
                $value->{$key} = $this->redacts(strtolower((string) $key), $child)
                    ? self::REDACTED
                    : $this->value($child);
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
