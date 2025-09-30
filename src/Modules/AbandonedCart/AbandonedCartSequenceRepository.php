<?php

namespace Amplisio\AIO\Modules\AbandonedCart;

use function __;

class AbandonedCartSequenceRepository
{
    private const OPTION_KEY = 'amplisio_aio_abandoned_sequences';

    public function get_settings(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if ( ! is_array($stored) ) {
            $stored = [];
        }

        return wp_parse_args($stored, $this->get_default_settings());
    }

    public function update_settings(array $settings): void
    {
        $defaults = $this->get_default_settings();
        $payload  = [
            'abandonAfterMinutes' => max(5, (int) ($settings['abandonAfterMinutes'] ?? $defaults['abandonAfterMinutes'])),
            'expireAfterDays'     => max(1, (int) ($settings['expireAfterDays'] ?? $defaults['expireAfterDays'])),
            'sequences'           => $this->sanitize_sequences($settings['sequences'] ?? $defaults['sequences']),
        ];

        update_option(self::OPTION_KEY, $payload, false);
    }

    public function get_sequences(): array
    {
        $settings = $this->get_settings();
        return $settings['sequences'];
    }

    public function get_default_settings(): array
    {
        return [
            'abandonAfterMinutes' => 60,
            'expireAfterDays'     => 14,
            'sequences'           => [
                [
                    'id'           => 'reminder-1',
                    'name'         => __('First reminder', 'amplisio-aio'),
                    'delay'        => 60,
                    'subject'      => __('You left something behind', 'amplisio-aio'),
                    'body'         => __('Hi {{first_name}}, we saved your cart. Complete your purchase here: {{cart_link}}', 'amplisio-aio'),
                    'autoCoupon'   => false,
                    'couponType'   => 'percent',
                    'couponAmount' => 10,
                    'couponExpiryDays' => 7,
                ],
                [
                    'id'           => 'reminder-2',
                    'name'         => __('Last chance', 'amplisio-aio'),
                    'delay'        => 180,
                    'subject'      => __('Still thinking it over?', 'amplisio-aio'),
                    'body'         => __('Take another look at your cart: {{cart_link}}. Use coupon {{coupon}} before it expires.', 'amplisio-aio'),
                    'autoCoupon'   => true,
                    'couponType'   => 'percent',
                    'couponAmount' => 15,
                    'couponExpiryDays' => 3,
                ],
            ],
        ];
    }

    private function sanitize_sequences(array $sequences): array
    {
        $sanitized = [];

        foreach ($sequences as $sequence) {
            if (empty($sequence['id'])) {
                continue;
            }

            $sanitized[] = [
                'id'              => sanitize_key($sequence['id']),
                'name'            => sanitize_text_field($sequence['name'] ?? ''),
                'delay'           => max(5, (int) ($sequence['delay'] ?? 60)),
                'subject'         => sanitize_text_field($sequence['subject'] ?? ''),
                'body'            => wp_kses_post($sequence['body'] ?? ''),
                'autoCoupon'      => (bool) ($sequence['autoCoupon'] ?? false),
                'couponType'      => in_array($sequence['couponType'] ?? 'percent', ['percent', 'fixed_cart', 'fixed_product'], true)
                    ? $sequence['couponType']
                    : 'percent',
                'couponAmount'    => (float) ($sequence['couponAmount'] ?? 0),
                'couponExpiryDays'=> max(1, (int) ($sequence['couponExpiryDays'] ?? 7)),
            ];
        }

        return $sanitized;
    }
}
