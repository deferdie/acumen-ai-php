<?php

namespace AcumenAi\Wrappers;

use AcumenAi\AcumenAi;

/**
 * Thin decorator around openai-php/client that records chat completions.
 * Forwards unknown methods to the underlying client.
 */
class OpenAiClientWrapper
{
    public function __construct(
        private object $client,
        private AcumenAi $acumen,
    ) {
    }

    public function chat(): OpenAiChatWrapper
    {
        return new OpenAiChatWrapper($this->client->chat(), $this->acumen);
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

class OpenAiChatWrapper
{
    public function __construct(
        private object $chat,
        private AcumenAi $acumen,
    ) {
    }

    public function create(array $parameters)
    {
        $started = (int) (microtime(true) * 1000);
        $response = $this->chat->create($parameters);
        $latency = (int) (microtime(true) * 1000) - $started;

        $output = '';
        if (is_object($response) && isset($response->choices[0]->message->content)) {
            $output = (string) $response->choices[0]->message->content;
        } elseif (is_array($response)) {
            $output = (string) ($response['choices'][0]['message']['content'] ?? '');
        }

        $toolCalls = [];
        $rawTools = null;
        if (is_object($response) && isset($response->choices[0]->message->toolCalls)) {
            $rawTools = $response->choices[0]->message->toolCalls;
        }

        if (is_array($rawTools) || is_object($rawTools)) {
            foreach ($rawTools as $tool) {
                $name = is_object($tool) ? ($tool->function->name ?? $tool->name ?? '') : ($tool['function']['name'] ?? '');
                $argsRaw = is_object($tool) ? ($tool->function->arguments ?? '{}') : ($tool['function']['arguments'] ?? '{}');
                $args = is_string($argsRaw) ? (json_decode($argsRaw, true) ?: []) : (array) $argsRaw;
                $toolCalls[] = ['name' => $name, 'args' => $args];
            }
        }

        $this->acumen->record([
            'type' => 'llm_call',
            'model' => $parameters['model'] ?? null,
            'input' => $parameters['messages'] ?? [],
            'output' => ['content' => $output],
            'tool_calls' => $toolCalls,
            'usage' => [
                'in' => is_object($response) ? ($response->usage->promptTokens ?? null) : ($response['usage']['prompt_tokens'] ?? null),
                'out' => is_object($response) ? ($response->usage->completionTokens ?? null) : ($response['usage']['completion_tokens'] ?? null),
            ],
            'latency_ms' => $latency,
        ]);

        return $response;
    }

    public function __call(string $name, array $arguments)
    {
        return $this->chat->{$name}(...$arguments);
    }
}
