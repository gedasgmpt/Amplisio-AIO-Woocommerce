<?php

namespace Amplisio\AIO\Modules\RecoveredCarts;

use Amplisio\AIO\Modules\AbstractModule;
use Amplisio\AIO\Services\Container;
use WC_Order;

class RecoveredCartsModule extends AbstractModule
{
    private ?RecoveredCartsService $service = null;

    public function get_id(): string
    {
        return 'recovered_carts';
    }

    public function get_name(): string
    {
        return __('Recovered carts', 'amplisio-aio');
    }

    public function get_rest_base(): string
    {
        return 'recovered-carts';
    }

    public function register(Container $container): void
    {
        $container->singleton(RecoveredCartsService::class, static fn (): RecoveredCartsService => new RecoveredCartsService($GLOBALS['wpdb']));
        $this->service = $container->get(RecoveredCartsService::class);
    }

    public function boot(Container $container): void
    {
        $service = $this->service ?? $container->get(RecoveredCartsService::class);
        $service->maybe_create_table();

        add_action('woocommerce_order_status_completed', static function (int $order_id) use ($service): void {
            $order = wc_get_order($order_id);
            if ( ! $order instanceof WC_Order ) {
                return;
            }

            $flag = $order->get_meta('_amplisio_recovered_cart', true);
            if ( 'yes' !== $flag ) {
                return;
            }

            $service->record($order_id, (string) $order->get_billing_email(), (float) $order->get_total());
        });

        add_action('rest_api_init', static function () use ($service): void {
            (new RecoveredCartsRestController($service))->register_routes();
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
        $summary = ($this->service ?? new RecoveredCartsService($GLOBALS['wpdb']))->get_summary();

        return [
            [
                'id'          => 'recovered-carts',
                'title'       => __('Recovered carts', 'amplisio-aio'),
                'value'       => number_format_i18n($summary['count']),
                'description' => __('Recovered over the last 6 months', 'amplisio-aio'),
            ],
            [
                'id'          => 'recovered-revenue',
                'title'       => __('Recovered revenue', 'amplisio-aio'),
                'value'       => wc_price($summary['amount']),
                'description' => __('Total revenue attributed to recovery flows', 'amplisio-aio'),
            ],
        ];
    }

    public function run_scheduled_event(): void
    {
        ($this->service ?? new RecoveredCartsService($GLOBALS['wpdb']))->cleanup();
    }
}
