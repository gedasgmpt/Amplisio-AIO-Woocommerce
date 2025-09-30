<?php

namespace Amplisio\AIO\Modules\AbandonedCart;

use Amplisio\AIO\Modules\AbstractModule;
use Amplisio\AIO\Services\Container;
use Automattic\WooCommerce\Utilities\OrderUtil;
use WC_Cart;
use WC_Order;

use function __;
use function WC;
use function add_action;
use function add_filter;
use function add_query_arg;
use function apply_filters;
use function as_next_scheduled_action;
use function as_schedule_single_action;
use function esc_url;
use function is_user_logged_in;
use function number_format_i18n;
use function sanitize_key;
use function sanitize_text_field;
use function wc_add_notice;
use function wc_get_cart_url;
use function wc_get_order;
use function wc_price;
use function wp_json_encode;
use function wp_mail;
use function wp_unslash;

class AbandonedCartModule extends AbstractModule
{
    private ?AbandonedCartService $service = null;

    private ?AbandonedCartSequenceRepository $sequences = null;

    private bool $hpos_enabled = false;

    public function get_id(): string
    {
        return 'abandoned_cart';
    }

    public function get_name(): string
    {
        return __('Abandoned cart recovery', 'amplisio-aio');
    }

    public function get_rest_base(): string
    {
        return 'abandoned-cart';
    }

    public function register(Container $container): void
    {
        $container->singleton(AbandonedCartService::class, static fn (): AbandonedCartService => new AbandonedCartService($GLOBALS['wpdb']));
        $container->singleton(AbandonedCartSequenceRepository::class, static fn (): AbandonedCartSequenceRepository => new AbandonedCartSequenceRepository());
    }

    public function boot(Container $container): void
    {
        $this->service      = $container->get(AbandonedCartService::class);
        $this->sequences    = $container->get(AbandonedCartSequenceRepository::class);
        $this->hpos_enabled = $this->is_hpos_enabled();

        $this->service->maybe_create_table();

        add_action('woocommerce_cart_updated', [$this, 'capture_cart_from_session']);
        add_action('woocommerce_cart_loaded_from_session', [$this, 'capture_cart_from_session']);
        add_action('woocommerce_add_to_cart', [$this, 'capture_cart_from_session']);
        if ($this->hpos_enabled) {
            add_action('woocommerce_checkout_create_order', [$this, 'store_order_meta'], 10, 2);
        }
        add_action('woocommerce_checkout_order_processed', [$this, 'handle_order_processed'], 10, 3);
        add_action('wp', [$this, 'maybe_restore_cart']);

        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_privacy_eraser']);

        add_action('rest_api_init', function (): void {
            (new AbandonedCartRestController($this->service, $this->sequences))->register_routes();
        });

        add_action('amplisio_aio_abandoned_sequence', [$this, 'dispatch_sequence_email'], 10, 2);
    }

    public function get_default_settings(): array
    {
        return [
            'enabled' => false,
        ];
    }

    public function get_dashboard_cards(): array
    {
        $stats = $this->service?->get_stats() ?? ['counts' => ['abandoned' => 0, 'recovered' => 0, 'active' => 0, 'expired' => 0], 'revenue' => 0];

        return [
            [
                'id'          => 'abandoned-carts',
                'title'       => __('Abandoned carts', 'amplisio-aio'),
                'value'       => number_format_i18n($stats['counts']['abandoned'] ?? 0),
                'description' => __('Carts requiring follow-up', 'amplisio-aio'),
            ],
            [
                'id'          => 'recovered-carts-total',
                'title'       => __('Recovered revenue', 'amplisio-aio'),
                'value'       => wc_price($stats['revenue'] ?? 0),
                'description' => __('Total revenue from recovery sequences', 'amplisio-aio'),
            ],
        ];
    }

    public function run_scheduled_event(): void
    {
        $service   = $this->service ?? new AbandonedCartService($GLOBALS['wpdb']);
        $repository = $this->sequences ?? new AbandonedCartSequenceRepository();
        $settings  = $repository->get_settings();
        $sequences = $settings['sequences'];

        $service->mark_carts_abandoned((int) $settings['abandonAfterMinutes'], function (array $cart) use ($sequences): void {
            if ( ! function_exists('as_schedule_single_action') ) {
                return;
            }

            foreach ($sequences as $sequence) {
                $sequence_id = sanitize_key($sequence['id']);
                if ( ! $sequence_id ) {
                    continue;
                }

                $timestamp = strtotime($cart['abandoned_at']) + ((int) $sequence['delay'] * MINUTE_IN_SECONDS);
                if ( ! empty($cart['sequence_log']) ) {
                    $events = json_decode((string) $cart['sequence_log'], true);
                    if (is_array($events)) {
                        foreach ($events as $event) {
                            if (('sent' === ($event['type'] ?? '')) && $event['sequence_id'] === $sequence_id) {
                                continue 2;
                            }
                        }
                    }
                }

                if (function_exists('as_next_scheduled_action')) {
                    $next = as_next_scheduled_action('amplisio_aio_abandoned_sequence', [$cart['id'], $sequence_id], 'amplisio-aio');
                    if ($next) {
                        continue;
                    }
                }

                if ($timestamp <= time()) {
                    $timestamp = time() + MINUTE_IN_SECONDS;
                }

                as_schedule_single_action($timestamp, 'amplisio_aio_abandoned_sequence', [$cart['id'], $sequence_id], 'amplisio-aio');
            }
        });

        $service->expire_abandoned_carts((int) $settings['expireAfterDays']);
    }

    public function capture_cart_from_session(): void
    {
        if ( ! function_exists('WC') ) {
            return;
        }

        $wc = WC();
        if ( ! $wc || ! $wc->cart instanceof WC_Cart || ! $wc->session ) {
            return;
        }

        $cart = $wc->cart;
        if ($cart->is_empty()) {
            return;
        }

        $session_id = (string) $wc->session->get_customer_id();
        if ('' === $session_id) {
            return;
        }

        $items = [];
        foreach ($cart->get_cart() as $item) {
            $product = $item['data'] ?? null;
            $items[] = [
                'product_id'   => isset($item['product_id']) ? (int) $item['product_id'] : 0,
                'variation_id' => isset($item['variation_id']) ? (int) $item['variation_id'] : 0,
                'quantity'     => isset($item['quantity']) ? (int) $item['quantity'] : 0,
                'name'         => $product ? $product->get_name() : ($item['name'] ?? ''),
            ];
        }

        if ( ! $items ) {
            return;
        }

        $customer   = $wc->customer;
        $email      = $customer ? $customer->get_email() : '';
        $first_name = $customer ? $customer->get_first_name() : '';

        $settings = $this->sequences?->get_settings() ?? (new AbandonedCartSequenceRepository())->get_default_settings();
        $consent  = apply_filters('amplisio_aio_abandoned_cart_consent', is_user_logged_in() && (bool) $email, $cart, $customer);

        $this->service?->record_cart($session_id, [
            'email'      => $email,
            'first_name' => $first_name,
            'subtotal'   => (float) $cart->get_subtotal(),
            'items'      => $items,
            'consent'    => $consent,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + ((int) $settings['expireAfterDays'] * DAY_IN_SECONDS)),
        ]);
    }

    public function store_order_meta(WC_Order $order, array $data): void
    {
        if ( ! $this->hpos_enabled || ! function_exists('WC') ) {
            return;
        }

        $wc = WC();
        if ( ! $wc || ! $wc->session ) {
            return;
        }

        $cart_key = (string) $wc->session->get_customer_id();
        if ($cart_key) {
            $order->update_meta_data('_amplisio_cart_key', $cart_key);
        }
    }

    public function handle_order_processed(int $order_id, array $posted_data, WC_Order $order): void
    {
        if ( ! $order instanceof WC_Order ) {
            if (function_exists('wc_get_order')) {
                $order = wc_get_order($order_id);
            }

            if ( ! $order instanceof WC_Order ) {
                return;
            }
        }

        $cart_key = $this->hpos_enabled ? (string) $order->get_meta('_amplisio_cart_key') : '';
        $cart     = $cart_key ? $this->service?->get_cart_by_key($cart_key) : null;

        if (null === $cart && $order->get_billing_email()) {
            $matches = $this->service?->find_carts_for_email($order->get_billing_email()) ?? [];
            foreach ($matches as $candidate) {
                if ('recovered' === $candidate['status']) {
                    continue;
                }
                $cart = $candidate;
                break;
            }
        }

        if (null === $cart) {
            return;
        }

        $this->service?->mark_recovered(
            (int) $cart['id'],
            $order_id,
            (float) $order->get_total(),
            (string) $order->get_billing_email(),
            (string) $order->get_billing_first_name()
        );
    }

    public function maybe_restore_cart(): void
    {
        if ( ! function_exists('WC') || ! isset($_GET['amplisio_cart'], $_GET['amplisio_token']) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $cart_key = sanitize_text_field(wp_unslash((string) $_GET['amplisio_cart'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token    = sanitize_text_field(wp_unslash((string) $_GET['amplisio_token'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ('' === $cart_key || '' === $token) {
            return;
        }

        $payload = $this->service?->get_restore_payload($cart_key, $token);
        if ( ! $payload ) {
            return;
        }

        $wc = WC();
        if ( ! $wc || ! $wc->cart instanceof WC_Cart ) {
            return;
        }

        $cart = $wc->cart;
        $cart->empty_cart();

        foreach ($payload['items'] as $item) {
            if (empty($item['product_id'])) {
                continue;
            }

            $product_id   = (int) $item['product_id'];
            $variation_id = isset($item['variation_id']) ? (int) $item['variation_id'] : 0;
            $quantity     = max(1, (int) ($item['quantity'] ?? 1));

            if ($variation_id) {
                $cart->add_to_cart($product_id, $quantity, $variation_id);
            } else {
                $cart->add_to_cart($product_id, $quantity);
            }
        }

        if ( ! empty($payload['coupon_code']) ) {
            $cart->apply_coupon(sanitize_text_field($payload['coupon_code']));
        }

        wc_add_notice(__('Your cart has been restored. Continue checkout to complete your purchase.', 'amplisio-aio'), 'success');
    }

    public function register_privacy_exporter(array $exporters): array
    {
        $exporters['amplisio-aio-abandoned-cart'] = [
            'exporter_friendly_name' => __('Amplisio AIO Abandoned Cart', 'amplisio-aio'),
            'callback'               => [$this, 'export_personal_data'],
        ];

        return $exporters;
    }

    public function register_privacy_eraser(array $erasers): array
    {
        $erasers['amplisio-aio-abandoned-cart'] = [
            'eraser_friendly_name' => __('Amplisio AIO Abandoned Cart', 'amplisio-aio'),
            'callback'             => [$this, 'erase_personal_data'],
        ];

        return $erasers;
    }

    public function export_personal_data(string $email, int $page): array
    {
        $records = $this->service?->find_carts_for_email($email) ?? [];
        $data    = [];

        foreach ($records as $record) {
            $items = json_decode((string) $record['items'], true);
            if ( ! is_array($items) ) {
                $items = [];
            }

            $data[] = [
                'group_id'    => 'amplisio-aio-abandoned-cart',
                'group_label' => __('Amplisio AIO Abandoned Cart', 'amplisio-aio'),
                'item_id'     => 'amplisio-cart-' . (int) $record['id'],
                'data'        => [
                    [
                        'name'  => __('Status', 'amplisio-aio'),
                        'value' => $record['status'],
                    ],
                    [
                        'name'  => __('Last updated', 'amplisio-aio'),
                        'value' => $record['updated_at'],
                    ],
                    [
                        'name'  => __('Items', 'amplisio-aio'),
                        'value' => wp_json_encode($items),
                    ],
                ],
            ];
        }

        return [
            'data' => $data,
            'done' => true,
        ];
    }

    public function erase_personal_data(string $email, int $page): array
    {
        $count = $this->service?->erase_carts_for_email($email) ?? 0;

        return [
            'items_removed'  => $count > 0,
            'items_retained' => false,
            'messages'       => [],
        ];
    }

    public function dispatch_sequence_email(int $cart_id, string $sequence_id): void
    {
        $service   = $this->service ?? new AbandonedCartService($GLOBALS['wpdb']);
        $repository = $this->sequences ?? new AbandonedCartSequenceRepository();

        $cart = $service->get_cart($cart_id);
        if ( ! $cart || 'abandoned' !== $cart['status'] || empty($cart['email']) ) {
            return;
        }

        $sequence = $this->find_sequence($repository->get_sequences(), $sequence_id);
        if ( ! $sequence ) {
            return;
        }

        if ( ! empty($cart['sequence_log']) ) {
            $events = json_decode((string) $cart['sequence_log'], true);
            if (is_array($events)) {
                foreach ($events as $event) {
                    if (('sent' === ($event['type'] ?? '')) && $event['sequence_id'] === $sequence_id) {
                        return;
                    }
                }
            }
        }

        $coupon = null;
        if ( ! empty($sequence['autoCoupon']) ) {
            $coupon = $service->issue_coupon_for_cart($cart_id, (string) $cart['email'], $sequence);
        } elseif ( ! empty($cart['coupon_code']) ) {
            $coupon = [
                'code'       => $cart['coupon_code'],
                'expires_at' => $cart['coupon_expires_at'],
            ];
        }

        $replacements = [
            '{{first_name}}' => $cart['first_name'] ?: __('there', 'amplisio-aio'),
            '{{cart_link}}'  => esc_url(add_query_arg(
                [
                    'amplisio_cart'  => $cart['cart_key'],
                    'amplisio_token' => $cart['restore_token'],
                ],
                wc_get_cart_url()
            )),
            '{{coupon}}'     => $coupon['code'] ?? '',
        ];

        $subject = strtr($sequence['subject'], $replacements);
        $body    = strtr($sequence['body'], $replacements);

        $headers = apply_filters('amplisio_aio_abandoned_cart_email_headers', ['Content-Type: text/html; charset=UTF-8']);
        $sent    = wp_mail($cart['email'], $subject, $body, $headers);

        if ($sent) {
            $service->log_sequence_event($cart_id, [
                'type'        => 'sent',
                'sequence_id' => $sequence_id,
                'coupon_code' => $coupon['code'] ?? null,
            ]);
        }
    }

    private function find_sequence(array $sequences, string $sequence_id): ?array
    {
        foreach ($sequences as $sequence) {
            if (sanitize_key($sequence['id']) === sanitize_key($sequence_id)) {
                return $sequence;
            }
        }

        return null;
    }

    private function is_hpos_enabled(): bool
    {
        if (class_exists(OrderUtil::class)) {
            return OrderUtil::custom_orders_table_usage_is_enabled();
        }

        return false;
    }
}
