<?php

namespace AcumenAi\Transport;

interface TransportInterface
{
    /** @param array<int, array<string, mixed>> $events */
    public function sendEvents(array $events): void;

    /** @return array{verdict: string, detections: array, latency_ms: int} */
    public function guard(array $event): array;
}
