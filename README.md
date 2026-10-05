# Acumen AI PHP SDK

Capture LLM traffic from PHP apps and send it to Acumen AI Security Monitoring.

## Install

```bash
composer require deferdie/acumen-ai-php
```

## Laravel

Publish config (optional):

```bash
php artisan vendor:publish --tag=acumen-ai-config
```

Set env:

```
ACUMEN_AI_ENDPOINT=https://app.acumenlogs.com/api/ai
ACUMEN_AI_KEY_ID=aik_...
ACUMEN_AI_SECRET=aisk_...
```

## Usage

```php
use AcumenAi\AcumenAi;

AcumenAi::withUser($userId)->withSession($sessionId);

$client = AcumenAi::wrapOpenAi(OpenAI::client(env('OPENAI_API_KEY')));
$response = $client->chat()->create([
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'user', 'content' => 'Hello'],
    ],
]);

// Optional sync guard (layers 1-3 only, fails open)
$decision = AcumenAi::instance()->guard([
    ['role' => 'user', 'content' => $userMessage],
]);
if ($decision['verdict'] === 'block') {
    abort(403);
}
```

Anthropic:

```php
$client = AcumenAi::wrapAnthropic($anthropicClient);
```

Events flush automatically on Laravel request termination, or call `AcumenAi::instance()->flush()`.

PII (emails, phones, cards, API keys) is redacted client-side before leaving your server.
