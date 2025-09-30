<?php

namespace Amplisio\AIO\Modules\Analytics;

use WC_Order;
use WC_Order_Query;

class AnalyticsService
{
    private const CACHE_KEY = 'amplisio_aio_analytics_metrics';
    private const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

    public function get_dashboard_metrics(): array
    {
        $cached = get_transient(self::CACHE_KEY);
        if ( $cached && is_array($cached) ) {
            return $cached;
        }

        $metrics = $this->calculate_metrics();
        set_transient(self::CACHE_KEY, $metrics, self::CACHE_TTL);

        return $metrics;
    }

    public function prime_cache(): void
    {
        $metrics = $this->calculate_metrics();
        set_transient(self::CACHE_KEY, $metrics, self::CACHE_TTL);
    }

    private function calculate_metrics(): array
    {
        $orders = $this->get_recent_orders();

        $total_value = 0.0;
        $order_count = count($orders);
        $customers   = [];

        foreach ($orders as $order) {
            $total_value += (float) $order->get_total();
            $customers[$order->get_customer_id() ?: $order->get_billing_email()][] = (float) $order->get_total();
        }

        $average_order_value = $order_count > 0 ? $total_value / $order_count : 0.0;
        $ltv_values          = array_map(
            static fn (array $purchases): float => array_sum($purchases),
            $customers
        );

        $median_ltv = 0.0;
        if ( ! empty($ltv_values) ) {
            sort($ltv_values);
            $middle = (int) floor((count($ltv_values) - 1) / 2);
            if ( count($ltv_values) % 2 ) {
                $median_ltv = $ltv_values[$middle];
            } else {
                $median_ltv = ($ltv_values[$middle] + $ltv_values[$middle + 1]) / 2;
            }
        }

        $checkout_sessions = max($order_count, (int) get_option('amplisio_aio_checkout_sessions', $order_count));
        $conversion_rate   = $checkout_sessions > 0 ? ($order_count / $checkout_sessions) * 100 : 0.0;

        return [
            'average_order_value'     => wc_price($average_order_value),
            'conversion_rate'         => number_format_i18n($conversion_rate, 2) . '%',
            'customer_lifetime_value' => wc_price($median_ltv),
        ];
    }

    /**
     * @return WC_Order[]
     */
    private function get_recent_orders(): array
    {
        $query = new WC_Order_Query([
            'status' => ['wc-completed', 'wc-processing'],
            'date_created' => '>' . (new \DateTime('-30 days'))->format('Y-m-d H:i:s'),
            'limit' => 100,
            'type' => 'shop_order',
            'return' => 'objects',
        ]);

        return $query->get_orders();
    }
}
