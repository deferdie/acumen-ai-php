<?php

namespace AcumenAi;

use Illuminate\Support\ServiceProvider;

class AcumenAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/acumen-ai.php', 'acumen-ai');

        $this->app->singleton(AcumenAi::class, function () {
            return AcumenAi::configure([
                'endpoint' => config('acumen-ai.endpoint'),
                'key_id' => config('acumen-ai.key_id'),
                'secret' => config('acumen-ai.secret'),
                'flush_at' => config('acumen-ai.flush_at', 20),
                'fail_open' => config('acumen-ai.fail_open', true),
                'redaction_enabled' => config('acumen-ai.redaction_enabled', true),
                'hash_only' => config('acumen-ai.hash_only', false),
                'custom_patterns' => config('acumen-ai.custom_patterns', []),
            ]);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/acumen-ai.php' => config_path('acumen-ai.php'),
        ], 'acumen-ai-config');

        if ($this->app->runningInConsole() === false && method_exists($this->app, 'terminating')) {
            $this->app->terminating(function () {
                try {
                    $this->app->make(AcumenAi::class)->flush();
                } catch (\Throwable $e) {
                    // never break host app
                }
            });
        }
    }
}
