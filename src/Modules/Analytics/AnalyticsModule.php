<?php

namespace Amplisio\AIO\Modules\Analytics;

use Amplisio\AIO\Modules\AbstractModule;
use Amplisio\AIO\Services\Container;

class AnalyticsModule extends AbstractModule
{
    private ?AnalyticsService $service = null;

    public function get_id(): string
    {
        return 'analytics';
    }

    public function get_name(): string
    {
        return __('Intelligence', 'amplisio-aio');
    }

    public function register(Container $container): void
    {
        $container->singleton(AnalyticsService::class, static fn (): AnalyticsService => new AnalyticsService());
        $this->service = $container->get(AnalyticsService::class);
    }

    public function boot(Container $container): void
    {
        $service = $this->service ?? $container->get(AnalyticsService::class);

        add_action('rest_api_init', static function () use ($service): void {
            (new AnalyticsRestController($service))->register_routes();
        });
    }

    public function get_default_settings(): array
    {
        return [
            'enabled' => true,
        ];
    }

    public function get_dashboard_cards(): array
    {
        $service = $this->service ?? new AnalyticsService();
        $metrics = $service->get_dashboard_metrics();

        return [
            [
                'id'          => 'aov',
                'title'       => __('Average order value', 'amplisio-aio'),
                'value'       => $metrics['average_order_value'],
                'description' => __('Rolling 30 day AOV', 'amplisio-aio'),
            ],
            [
                'id'          => 'conversion',
                'title'       => __('Conversion rate', 'amplisio-aio'),
                'value'       => $metrics['conversion_rate'],
                'description' => __('Orders / checkouts in last 30 days', 'amplisio-aio'),
            ],
            [
                'id'          => 'ltv',
                'title'       => __('Customer lifetime value', 'amplisio-aio'),
                'value'       => $metrics['customer_lifetime_value'],
                'description' => __('Median spend per customer', 'amplisio-aio'),
            ],
        ];
    }

    public function run_scheduled_event(): void
    {
        ($this->service ?? new AnalyticsService())->prime_cache();
    }
}
