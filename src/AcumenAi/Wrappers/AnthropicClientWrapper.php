<?php

namespace AcumenAi\Wrappers;

use AcumenAi\AcumenAi;

/**
 * Thin decorator around anthropic-php client messages API.
 */
class AnthropicClientWrapper
{
    public function __construct(
        private object $client,
        private AcumenAi $acumen,
    ) {
    }

    public function messages(): AnthropicMessagesWrapper
    {
        return new AnthropicMessagesWrapper($this->client->messages, $this->acumen);
    }

    public function __call(string $name, array $arguments)
    {
        return $this->client->{$name}(...$arguments);
    }

    public function __get(string $name)
    {
        return $this->client->{$name};
    }
}

class AnthropicMessagesWrapper
{
    public function __construct(
        private object $messages,
        private AcumenAi $acumen,
    ) {
    }

    public function create(array $parameters)
    {
        $started = (int) (microtime(true) * 1000);
        $response = $this->messages->create($parameters);
        $latency = (int) (microtime(true) * 1000) - $started;

        $output = '';
        if (is_object($response) && isset($response->content)) {
            foreach ($response->content as $block) {
                if (($block->type ?? null) === 'text') {
                    $output .= (string) ($block->text ?? '');
                }
            }
        } elseif (is_array($response)) {
            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $output .= (string) ($block['text'] ?? '');
                }
            }
        }

        $input = $parameters['messages'] ?? [];
        if (! empty($parameters['system'])) {
            array_unshift($input, ['role' => 'system', 'content' => $parameters['system']]);
        }

        $this->acumen->record([
            'type' => 'llm_call',
            'model' => $parameters['model'] ?? null,
            'input' => $input,
            'output' => ['content' => $output],
            'latency_ms' => $latency,
            'meta' => ['provider' => 'anthropic'],
        ]);

        return $response;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->messages->{$name}(...$arguments);
    }
}
