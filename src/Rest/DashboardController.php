<?php

namespace Amplisio\AIO\Rest;

use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Repositories\OptionRepository;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class DashboardController extends WP_REST_Controller
{
    public function __construct(private ModuleManager $module_manager, private OptionRepository $options)
    {
        $this->namespace = 'amplisio/v1';
        $this->rest_base = 'dashboard';
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
            : new WP_Error('rest_forbidden', __('You are not allowed to view Amplisio data.', 'amplisio-aio'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items(WP_REST_Request $request): WP_REST_Response
    {
        $cards = $this->module_manager->get_dashboard_cards();
        $theme = $this->options->get_theme_settings();

        return new WP_REST_Response([
            'cards' => $cards,
            'theme' => $theme,
        ]);
    }
}
