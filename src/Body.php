<?php

declare(strict_types=1);

namespace Echoproof\Laravel;

use JsonException;

final class Body
{
    public static function decode(string $bytes, ?string $contentType): string|array
    {
        if ($bytes === '') {
            return 'empty';
        }
        $declaredJson = $contentType !== null && str_contains(strtolower($contentType), 'json');
        if ($declaredJson || $bytes[0] === '{' || $bytes[0] === '[') {
            try {
                return ['json' => json_decode($bytes, false, 512, JSON_THROW_ON_ERROR)];
            } catch (JsonException) {
            }
        }
        if (mb_check_encoding($bytes, 'UTF-8')) {
            return ['text' => $bytes];
        }

        return ['base64' => base64_encode($bytes)];
    }

    public static function encode(mixed $body): string
    {
        if (! is_array($body)) {
            return '';
        }
        if (array_key_exists('json', $body)) {
            return json_encode($body['json'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        }
        if (isset($body['text']) && is_string($body['text'])) {
            return $body['text'];
        }
        if (isset($body['base64']) && is_string($body['base64'])) {
            return (string) base64_decode($body['base64'], true);
        }

        return '';
    }
}
