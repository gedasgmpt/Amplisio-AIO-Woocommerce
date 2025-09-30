<?php

namespace Amplisio\AIO\Modules\BackInStock;

use Amplisio\AIO\Modules\AbstractModule;
use Amplisio\AIO\Services\Container;

class BackInStockModule extends AbstractModule
{
    private ?BackInStockService $service = null;

    public function get_id(): string
    {
        return 'back_in_stock';
    }

    public function get_name(): string
    {
        return __('Back in stock', 'amplisio-aio');
    }

    public function get_rest_base(): string
    {
        return 'back-in-stock';
    }

    public function register(Container $container): void
    {
        $container->singleton(BackInStockService::class, static fn (): BackInStockService => new BackInStockService($GLOBALS['wpdb']));
        $this->service = $container->get(BackInStockService::class);
    }

    public function boot(Container $container): void
    {
        $service = $this->service ?? $container->get(BackInStockService::class);
        $service->maybe_create_table();

        add_action('rest_api_init', static function () use ($service): void {
            (new BackInStockRestController($service))->register_routes();
        });
    }

    public function get_default_settings(): array
    {
        return [
            'enabled' => false,
        ];
    }

    public function get_dashboard_cards(): array
    {
        $summary = ($this->service ?? new BackInStockService($GLOBALS['wpdb']))->get_summary();

        return [
            [
                'id'          => 'back-in-stock-total',
                'title'       => __('Back-in-stock signups', 'amplisio-aio'),
                'value'       => number_format_i18n($summary['total']),
                'description' => __('All-time subscribers', 'amplisio-aio'),
            ],
            [
                'id'          => 'back-in-stock-pending',
                'title'       => __('Waiting to notify', 'amplisio-aio'),
                'value'       => number_format_i18n($summary['pending']),
                'description' => __('Customers to alert when inventory returns', 'amplisio-aio'),
            ],
        ];
    }

    public function run_scheduled_event(): void
    {
        ($this->service ?? new BackInStockService($GLOBALS['wpdb']))->cleanup();
    }
}
