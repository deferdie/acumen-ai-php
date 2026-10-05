<?php

namespace AcumenAi\Transport;

class FakeTransport implements TransportInterface
{
    /** @var array<int, array<string, mixed>> */
    public array $sent = [];

    public array $lastGuard = [];

    public function sendEvents(array $events): void
    {
        foreach ($events as $event) {
            $this->sent[] = $event;
        }
    }

    public function guard(array $event): array
    {
        $this->lastGuard = $event;

        return ['verdict' => 'allow', 'detections' => [], 'latency_ms' => 1];
    }
}
