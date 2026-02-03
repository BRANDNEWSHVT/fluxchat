<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\LLM\Cliproxy\Cliproxy;
use Illuminate\Support\ServiceProvider;
use Prism\Prism\PrismManager;

final class CliproxyServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->app
            ->make(PrismManager::class)
            ->extend('cliproxy', function ($application, $config): Cliproxy {
                return new Cliproxy(
                    apiKey: $config['api_key'] ?? '',
                    url: $config['url'] ?? 'http://localhost:8317/v1',
                );
            });
    }
}
