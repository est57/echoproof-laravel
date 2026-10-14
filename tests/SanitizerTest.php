<?php

declare(strict_types=1);

namespace Echoproof\Laravel\Tests;

use Echoproof\Laravel\Body;
use Echoproof\Laravel\Sanitizer;
use PHPUnit\Framework\TestCase;

final class SanitizerTest extends TestCase
{
    public function test_patterns_redact_strings_only_like_the_cli(): void
    {
        $sanitizer = new Sanitizer([], ['token', '*token*', '*_hash', 'pin_*']);
        $body = Body::decode('{"pin_hash":"x","api_token":"t","max_tokens":16000,"pin_code":"1234","Token":5}', null);

        $redacted = json_encode($sanitizer->body($body)['json']);

        $this->assertSame('{"pin_hash":"[REDACTED]","api_token":"[REDACTED]","max_tokens":16000,"pin_code":"[REDACTED]","Token":"[REDACTED]"}', $redacted);
    }

    public function test_redacts_inside_arrays(): void
    {
        $sanitizer = new Sanitizer([], ['secret']);
        $body = Body::decode('[{"secret":{"a":1}},{"b":"c"}]', 'application/json');

        $this->assertSame('[{"secret":"[REDACTED]"},{"b":"c"}]', json_encode($sanitizer->body($body)['json']));
    }

    public function test_invalid_json_falls_back_to_text(): void
    {
        $this->assertSame(['text' => '{oops'], Body::decode('{oops', 'application/json'));
    }
}
