<?php

namespace AcumenAi;

use AcumenAi\Context\RequestContext;
use AcumenAi\Redaction\PiiRedactor;
use AcumenAi\Transport\HttpTransport;
use AcumenAi\Transport\TransportInterface;
use AcumenAi\Wrappers\OpenAiClientWrapper;
use AcumenAi\Wrappers\AnthropicClientWrapper;

class AcumenAi
{
    private static ?self $instance = null;

    private TransportInterface $transport;
    private PiiRedactor $redactor;
    private RequestContext $context;
    private array $buffer = [];
    private int $flushAt;
    private bool $failOpen;

    public function __construct(
        TransportInterface $transport,
        ?PiiRedactor $redactor = null,
        int $flushAt = 20,
        bool $failOpen = true,
    ) {
        $this->transport = $transport;
        $this->redactor = $redactor ?? new PiiRedactor();
        $this->context = new RequestContext();
        $this->flushAt = $flushAt;
        $this->failOpen = $failOpen;
    }

    public static function configure(array $config): self
    {
        $transport = new HttpTransport(
            $config['endpoint'] ?? getenv('ACUMEN_AI_ENDPOINT') ?: 'https://app.acumenlogs.com/api/ai',
            $config['key_id'] ?? getenv('ACUMEN_AI_KEY_ID') ?: '',
            $config['secret'] ?? getenv('ACUMEN_AI_SECRET') ?: '',
        );

        $redactor = new PiiRedactor(
            $config['custom_patterns'] ?? [],
            (bool) ($config['hash_only'] ?? false),
            (bool) ($config['redaction_enabled'] ?? true),
        );

        self::$instance = new self(
            $transport,
            $redactor,
            (int) ($config['flush_at'] ?? 20),
            (bool) ($config['fail_open'] ?? true),
        );

        return self::$instance;
    }

    public static function instance(): self
    {
        if (! self::$instance) {
            self::configure([]);
        }

        return self::$instance;
    }

    public static function withUser(?string $userId): self
    {
        $ai = self::instance();
        $ai->context->endUserId = $userId;

        return $ai;
    }

    public static function withSession(?string $sessionId): self
    {
        $ai = self::instance();
        $ai->context->sessionId = $sessionId;

        return $ai;
    }

    public function record(array $event): void
    {
        $event['v'] = 1;
        $event['ts'] = $event['ts'] ?? gmdate('c');
        $event['end_user_id'] = $event['end_user_id'] ?? $this->context->endUserId;
        $event['session_id'] = $event['session_id'] ?? $this->context->sessionId;

        if (isset($event['input']) && is_array($event['input'])) {
            foreach ($event['input'] as &$message) {
                if (isset($message['content'])) {
                    $message['content'] = $this->redactor->redact((string) $message['content']);
                }
            }
        }

        if (isset($event['output']['content'])) {
            $event['output']['content'] = $this->redactor->redact((string) $event['output']['content']);
        }

        $this->buffer[] = $event;

        if (count($this->buffer) >= $this->flushAt) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $batch = $this->buffer;
        $this->buffer = [];

        try {
            $this->transport->sendEvents($batch);
        } catch (\Throwable $e) {
            if (! $this->failOpen) {
                throw $e;
            }
        }
    }

    /**
     * Synchronous guard — layers 1-3 only. Fails open on timeout/error.
     *
     * @return array{verdict: string, detections: array, latency_ms: int}
     */
    public function guard(array $messages, array $meta = []): array
    {
        $event = [
            'v' => 1,
            'type' => 'guard_check',
            'ts' => gmdate('c'),
            'end_user_id' => $this->context->endUserId,
            'session_id' => $this->context->sessionId,
            'input' => array_map(function ($m) {
                return [
                    'role' => $m['role'] ?? 'user',
                    'content' => $this->redactor->redact((string) ($m['content'] ?? '')),
                ];
            }, $messages),
            'meta' => $meta,
        ];

        try {
            return $this->transport->guard($event);
        } catch (\Throwable $e) {
            if ($this->failOpen) {
                return ['verdict' => 'allow', 'detections' => [], 'latency_ms' => 0];
            }
            throw $e;
        }
    }

    public static function wrapOpenAi(object $client): OpenAiClientWrapper
    {
        return new OpenAiClientWrapper($client, self::instance());
    }

    public static function wrapAnthropic(object $client): AnthropicClientWrapper
    {
        return new AnthropicClientWrapper($client, self::instance());
    }

    public function getContext(): RequestContext
    {
        return $this->context;
    }

    public function getBufferForTesting(): array
    {
        return $this->buffer;
    }
}
