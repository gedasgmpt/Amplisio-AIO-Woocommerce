<?php

namespace Amplisio\AIO\Modules\RecoveredCarts;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Response;

class RecoveredCartsRestController extends WP_REST_Controller
{
    public function __construct(private RecoveredCartsService $service)
    {
        $this->namespace = 'amplisio/v1';
        $this->rest_base = 'recovered-carts';
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
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );
    }

    public function permissions_check(): bool|WP_Error
    {
        return current_user_can('manage_woocommerce')
            ? true
            : new WP_Error('rest_forbidden', __('You are not allowed to view recovered carts.', 'amplisio-aio'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items(): WP_REST_Response
    {
        return new WP_REST_Response($this->service->get_summary());
    }
}
