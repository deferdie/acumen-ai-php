<?php

namespace AcumenAi\Tests;

use AcumenAi\AcumenAi;
use AcumenAi\Redaction\PiiRedactor;
use AcumenAi\Transport\FakeTransport;
use PHPUnit\Framework\TestCase;

class AcumenAiTest extends TestCase
{
    public function test_redacts_email_and_buffers_events(): void
    {
        $transport = new FakeTransport();
        $ai = new AcumenAi($transport, new PiiRedactor(), flushAt: 2);

        $ai->getContext()->endUserId = 'u1';
        $ai->record([
            'type' => 'llm_call',
            'input' => [['role' => 'user', 'content' => 'Email me at alice@example.com']],
            'output' => ['content' => 'ok'],
        ]);

        $this->assertCount(1, $ai->getBufferForTesting());
        $this->assertStringContainsString('[email]', $ai->getBufferForTesting()[0]['input'][0]['content']);

        $ai->record([
            'type' => 'llm_call',
            'input' => [['role' => 'user', 'content' => 'hello']],
            'output' => ['content' => 'world'],
        ]);

        $this->assertCount(0, $ai->getBufferForTesting());
        $this->assertCount(2, $transport->sent);
        $this->assertSame('u1', $transport->sent[0]['end_user_id']);
    }

    public function test_guard_fails_open(): void
    {
        $transport = new FakeTransport();
        $ai = new AcumenAi($transport);
        $result = $ai->guard([['role' => 'user', 'content' => 'hi']]);
        $this->assertSame('allow', $result['verdict']);
    }

    public function test_hash_only_mode(): void
    {
        $redactor = new PiiRedactor([], hashOnly: true);
        $out = $redactor->redact('secret text');
        $this->assertSame(hash('sha256', 'secret text'), $out);
    }
}
