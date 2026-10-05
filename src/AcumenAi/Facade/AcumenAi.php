<?php

namespace AcumenAi\Facade;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \AcumenAi\AcumenAi withUser(?string $userId)
 * @method static \AcumenAi\AcumenAi withSession(?string $sessionId)
 * @method static void record(array $event)
 * @method static void flush()
 * @method static array guard(array $messages, array $meta = [])
 * @method static \AcumenAi\Wrappers\OpenAiClientWrapper wrapOpenAi(object $client)
 * @method static \AcumenAi\Wrappers\AnthropicClientWrapper wrapAnthropic(object $client)
 */
class AcumenAi extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \AcumenAi\AcumenAi::class;
    }
}
