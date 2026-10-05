<?php

return [
    'endpoint' => env('ACUMEN_AI_ENDPOINT', 'https://app.acumenlogs.com/api/ai'),
    'key_id' => env('ACUMEN_AI_KEY_ID'),
    'secret' => env('ACUMEN_AI_SECRET'),
    'flush_at' => (int) env('ACUMEN_AI_FLUSH_AT', 20),
    'fail_open' => (bool) env('ACUMEN_AI_FAIL_OPEN', true),
    'redaction_enabled' => (bool) env('ACUMEN_AI_REDACTION', true),
    'hash_only' => (bool) env('ACUMEN_AI_HASH_ONLY', false),
    'custom_patterns' => [],
];
