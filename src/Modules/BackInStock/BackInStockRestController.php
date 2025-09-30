<?php

namespace Amplisio\AIO\Modules\BackInStock;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

class BackInStockRestController extends WP_REST_Controller
{
    public function __construct(private BackInStockService $service)
    {
        $this->namespace = 'amplisio/v1';
        $this->rest_base = 'back-in-stock';
    }

    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_items'],
                    'permission_callback' => [$this, 'view_permissions_check'],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'create_item'],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    public function view_permissions_check(): bool|WP_Error
    {
        return current_user_can('manage_woocommerce')
            ? true
            : new WP_Error('rest_forbidden', __('You are not allowed to view signups.', 'amplisio-aio'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items(): WP_REST_Response
    {
        return new WP_REST_Response($this->service->get_summary());
    }

    public function create_item(WP_REST_Request $request): WP_REST_Response
    {
        $nonce = $request->get_header('X-WP-Nonce');
        if ( ! wp_verify_nonce($nonce, 'wp_rest') ) {
            return new WP_REST_Response(['message' => __('Invalid security token.', 'amplisio-aio')], 403);
        }

        $product_id = (int) $request->get_param('product_id');
        $email      = sanitize_email((string) $request->get_param('email'));

        if ( $product_id <= 0 || empty($email) ) {
            return new WP_REST_Response(['message' => __('Missing signup data.', 'amplisio-aio')], 400);
        }

        $this->service->record_signup($product_id, $email);

        return new WP_REST_Response(['message' => __('Signup saved.', 'amplisio-aio')]);
    }
}
