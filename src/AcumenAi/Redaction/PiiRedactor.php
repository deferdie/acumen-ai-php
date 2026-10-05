<?php

namespace AcumenAi\Redaction;

class PiiRedactor
{
    private array $customPatterns;
    private bool $hashOnly;
    private bool $enabled;

    private const PATTERNS = [
        'email' => '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/',
        'phone' => '/\b(?:\+?\d{1,3}[\s-]?)?(?:\(?\d{2,4}\)?[\s-]?)?\d{3,4}[\s-]?\d{3,4}\b/',
        'card' => '/\b(?:\d[ -]*?){13,19}\b/',
        'api_key' => '/\b(?:sk|aisk|aik)[-_][A-Za-z0-9]{16,}\b/',
    ];

    public function __construct(array $customPatterns = [], bool $hashOnly = false, bool $enabled = true)
    {
        $this->customPatterns = $customPatterns;
        $this->hashOnly = $hashOnly;
        $this->enabled = $enabled;
    }

    public function redact(string $text): string
    {
        if (! $this->enabled) {
            return $text;
        }

        if ($this->hashOnly) {
            return hash('sha256', $text);
        }

        foreach (self::PATTERNS as $name => $pattern) {
            $text = preg_replace_callback($pattern, function ($m) use ($name) {
                return '['.$name.']';
            }, $text) ?? $text;
        }

        foreach ($this->customPatterns as $i => $pattern) {
            try {
                $text = preg_replace($pattern, '[custom_'.$i.']', $text) ?? $text;
            } catch (\Throwable $e) {
                // skip invalid pattern
            }
        }

        return $text;
    }
}
