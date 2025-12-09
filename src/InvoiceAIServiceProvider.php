<?php

namespace OBSTechnologies\InvoiceAI;

use Illuminate\Support\ServiceProvider;
use OBSTechnologies\InvoiceAI\Contracts\InvoiceExtractorInterface;
use OBSTechnologies\InvoiceAI\Services\ClaudeInvoiceExtractor;

class InvoiceAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/invoiceai.php',
            'invoiceai'
        );

        $this->app->singleton(InvoiceExtractorInterface::class, function ($app) {
            $driver = config('invoiceai.default_driver', 'claude');

            return match ($driver) {
                'claude' => new ClaudeInvoiceExtractor(
                    config('invoiceai.drivers.claude.api_key'),
                    config('invoiceai.drivers.claude.model')
                ),
                default => throw new \InvalidArgumentException("Unsupported invoice extractor driver: {$driver}"),
            };
        });

        $this->app->alias(InvoiceExtractorInterface::class, 'invoiceai');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/invoiceai.php' => config_path('invoiceai.php'),
            ], 'invoiceai-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'invoiceai-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
