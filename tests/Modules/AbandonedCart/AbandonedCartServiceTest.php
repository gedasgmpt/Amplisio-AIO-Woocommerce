<?php

use Amplisio\AIO\Modules\AbandonedCart\AbandonedCartService;

class AbandonedCartServiceTest extends WP_UnitTestCase
{
    private AbandonedCartService $service;

    public function setUp(): void
    {
        parent::setUp();
        global $wpdb;
        $this->service = new AbandonedCartService($wpdb);
        $this->service->maybe_create_table();
    }

    public function test_status_transitions(): void
    {
        $cart = $this->createCart();

        global $wpdb;
        $wpdb->update(
            $this->service->get_table_name(),
            ['updated_at' => gmdate('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['id' => $cart['id']]
        );

        $this->service->mark_carts_abandoned(60);

        $cart = $this->service->get_cart_by_key($cart['cart_key']);
        $this->assertNotNull($cart);
        $this->assertSame('abandoned', $cart['status']);
        $this->assertNotEmpty($cart['abandoned_at']);

        $this->service->mark_recovered((int) $cart['id'], 42, 120.0, 'customer@example.com', 'Jane');
        $cart = $this->service->get_cart_by_key($cart['cart_key']);
        $this->assertSame('recovered', $cart['status']);
        $this->assertSame(42, (int) $cart['recovered_order_id']);

        $wpdb->update(
            $this->service->get_table_name(),
            [
                'status'       => 'abandoned',
                'abandoned_at' => gmdate('Y-m-d H:i:s', strtotime('-20 days')),
            ],
            ['id' => $cart['id']]
        );

        $this->service->expire_abandoned_carts(14);

        $cart = $this->service->get_cart_by_key($cart['cart_key']);
        $this->assertSame('expired', $cart['status']);
    }

    public function test_coupon_issue_records_code(): void
    {
        $cart = $this->createCart();

        add_filter(
            'amplisio_aio_generate_coupon',
            static fn ($coupon, $cart_id, $email, $config) => [
                'code'       => 'TESTCOUPON',
                'expires_at' => gmdate('Y-m-d H:i:s', strtotime('+2 days')),
            ],
            10,
            4
        );

        $result = $this->service->issue_coupon_for_cart($cart['id'], 'customer@example.com', []);
        remove_all_filters('amplisio_aio_generate_coupon');

        $this->assertSame('TESTCOUPON', $result['code']);

        $stored = $this->service->get_cart($cart['id']);
        $this->assertSame('TESTCOUPON', $stored['coupon_code']);
    }

    private function createCart(): array
    {
        $cart_key = 'cart-' . wp_generate_uuid4();

        $this->service->record_cart($cart_key, [
            'email'      => 'customer@example.com',
            'consent'    => true,
            'first_name' => 'Jane',
            'items'      => [
                [
                    'product_id' => 1,
                    'quantity'   => 1,
                    'name'       => 'Sample product',
                ],
            ],
            'subtotal'   => 99.0,
        ]);

        $cart = $this->service->get_cart_by_key($cart_key);
        $this->assertNotNull($cart);

        return $cart;
    }
}
