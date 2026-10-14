<?php

return [

    'enabled' => (bool) env('ECHOPROOF_RECORD', false),

    'path' => env('ECHOPROOF_PATH', storage_path('echoproof/traffic.jsonl')),

    // Adds the recorder as global middleware. Set false to attach it to routes yourself.
    'global' => true,

    'sample_rate' => (float) env('ECHOPROOF_SAMPLE_RATE', 1.0),

    // Path patterns as accepted by Str::is, without the leading slash.
    'only' => [],

    'except' => ['telescope*', 'horizon*', '_debugbar*', 'livewire*', 'up'],

    'max_body_bytes' => 8 * 1024 * 1024,

    // Keep in sync with [sanitize] in echoproof.toml so replayed values stay comparable.
    'redact_headers' => ['authorization', 'proxy-authorization', 'cookie', 'set-cookie', 'x-api-key'],

    // Exact names redact any value; patterns with * redact string values only.
    'redact_fields' => [
        'password', 'token', 'access_token', 'refresh_token', 'secret', 'api_key', 'card_number', 'cvv', 'pin',
        'otp', '*password*', '*secret*', '*token*', '*_hash', 'pin_*', '*_pin', '*_otp',
    ],

];
