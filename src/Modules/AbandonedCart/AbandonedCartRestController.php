<?php

namespace Amplisio\AIO\Modules\AbandonedCart;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

use function __;
use function add_query_arg;
use function apply_filters;
use function current_user_can;
use function esc_url;
use function register_rest_route;
use function rest_authorization_required_code;
use function sanitize_email;
use function sanitize_text_field;
use function wc_get_cart_url;
use function wc_price;
use function wp_mail;
use function wp_kses_post;

class AbandonedCartRestController extends WP_REST_Controller
{
    public function __construct(private AbandonedCartService $service, private AbandonedCartSequenceRepository $repository)
    {
        $this->namespace = 'amplisio/v1';
        $this->rest_base = 'abandoned-cart';
    }

    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/stats',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_stats'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/recoveries',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_recoveries'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/top-products',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_top_products'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/sequences',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_sequences'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'update_sequences'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/sequence-performance',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_sequence_performance'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/preview',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'preview_sequence'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/test',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'send_test'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );
    }

    public function permissions_check(): bool|WP_Error
    {
        return current_user_can('manage_woocommerce')
            ? true
            : new WP_Error('rest_forbidden', __('You are not allowed to access abandoned cart data.', 'amplisio-aio'), ['status' => rest_authorization_required_code()]);
    }

    public function get_stats(): WP_REST_Response
    {
        $stats = $this->service->get_stats();
        $stats['revenue_formatted'] = function_exists('wc_price') ? wc_price($stats['revenue']) : $stats['revenue'];

        return new WP_REST_Response($stats);
    }

    public function get_recoveries(): WP_REST_Response
    {
        return new WP_REST_Response($this->service->get_recent_recoveries());
    }

    public function get_top_products(): WP_REST_Response
    {
        return new WP_REST_Response($this->service->get_top_products());
    }

    public function get_sequences(): WP_REST_Response
    {
        return new WP_REST_Response($this->repository->get_settings());
    }

    public function update_sequences(WP_REST_Request $request): WP_REST_Response
    {
        $data = json_decode($request->get_body(), true);
        if ( ! is_array($data) ) {
            $data = [];
        }

        $this->repository->update_settings($data);

        return new WP_REST_Response($this->repository->get_settings());
    }

    public function get_sequence_performance(): WP_REST_Response
    {
        $settings = $this->repository->get_settings();
        $data     = $this->service->get_sequence_performance($settings['sequences']);

        return new WP_REST_Response($data);
    }

    public function preview_sequence(WP_REST_Request $request): WP_REST_Response
    {
        $params   = $this->parse_json_body($request);
        $sequence = $this->find_sequence($params);
        if ( ! $sequence ) {
            return new WP_REST_Response(['message' => __('Sequence not found.', 'amplisio-aio')], 404);
        }

        $sample = $this->render_template($sequence, [
            'first_name' => __('there', 'amplisio-aio'),
            'cart_link'  => esc_url(add_query_arg([], wc_get_cart_url())),
            'coupon'     => __('SAVE10', 'amplisio-aio'),
        ]);

        return new WP_REST_Response($sample);
    }

    public function send_test(WP_REST_Request $request): WP_REST_Response
    {
        $params   = $this->parse_json_body($request);
        $sequence = $this->find_sequence($params);
        if ( ! $sequence ) {
            return new WP_REST_Response(['message' => __('Sequence not found.', 'amplisio-aio')], 404);
        }

        $email = sanitize_email($params['email'] ?? '');
        if ( ! $email ) {
            return new WP_REST_Response(['message' => __('A valid email is required.', 'amplisio-aio')], 400);
        }

        $preview = $this->render_template($sequence, [
            'first_name' => __('Tester', 'amplisio-aio'),
            'cart_link'  => esc_url(add_query_arg([], wc_get_cart_url())),
            'coupon'     => __('SAVE10', 'amplisio-aio'),
        ]);

        $headers = apply_filters('amplisio_aio_abandoned_cart_email_headers', ['Content-Type: text/html; charset=UTF-8']);
        wp_mail($email, $preview['subject'], $preview['body'], $headers);

        return new WP_REST_Response(['sent' => true]);
    }

    private function parse_json_body(WP_REST_Request $request): array
    {
        $params = json_decode($request->get_body(), true);
        if ( ! is_array($params) ) {
            $params = [];
        }

        return $params;
    }

    private function find_sequence(array $params): ?array
    {
        $settings = $this->repository->get_settings();
        $id       = sanitize_text_field((string) ($params['sequenceId'] ?? ''));

        foreach ($settings['sequences'] as $sequence) {
            if ($sequence['id'] === $id) {
                return $sequence;
            }
        }

        return null;
    }

    private function render_template(array $sequence, array $replacements): array
    {
        $map = [];
        foreach ($replacements as $key => $value) {
            $map['{{' . $key . '}}'] = (string) $value;
        }

        $subject = strtr($sequence['subject'], $map);
        $body    = strtr($sequence['body'], $map);

        return [
            'subject' => $subject,
            'body'    => wp_kses_post($body),
        ];
    }
}
