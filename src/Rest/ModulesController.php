<?php

namespace Amplisio\AIO\Rest;

use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Repositories\OptionRepository;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

use function __;
use function current_user_can;
use function register_rest_route;
use function rest_authorization_required_code;
use function sanitize_key;
use function sprintf;

class ModulesController extends WP_REST_Controller
{
    public function __construct(private ModuleManager $modules, private OptionRepository $options)
    {
        $this->namespace = 'amplisio/v1';
        $this->rest_base = 'modules';
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

        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<module>[a-z0-9_-]+)',
            [
                [
                    'methods'             => 'POST',
                    'callback'            => [$this, 'update_item'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
            ]
        );
    }

    public function permissions_check(): bool|WP_Error
    {
        return current_user_can('manage_woocommerce')
            ? true
            : new WP_Error('rest_forbidden', __('You are not allowed to manage Amplisio modules.', 'amplisio-aio'), ['status' => rest_authorization_required_code()]);
    }

    public function get_items(WP_REST_Request $request): WP_REST_Response
    {
        $modules = [];

        foreach ($this->modules->get_modules() as $module) {
            $settings = $this->modules->get_module_settings($module->get_id());

            $modules[] = [
                'id'         => $module->get_id(),
                'name'       => $module->get_name(),
                'enabled'    => (bool) ($settings['enabled'] ?? false),
                'settings'   => $settings,
                'defaults'   => $module->sanitize_settings($module->get_default_settings()),
                'capability' => $module->get_capability(),
                'restRoute'  => sprintf('%s/%s', $this->namespace, $module->get_rest_base()),
            ];
        }

        return new WP_REST_Response([
            'modules'  => $modules,
            'settings' => $this->options->get_modules(),
        ]);
    }

    public function update_item(WP_REST_Request $request): WP_REST_Response
    {
        $module_id = sanitize_key((string) $request['module']);
        $module    = $this->modules->get_module($module_id);

        if ( ! $module ) {
            return new WP_REST_Response([
                'message' => __('Module not found.', 'amplisio-aio'),
            ], 404);
        }

        $params = $request->get_json_params();
        if ( ! is_array($params) ) {
            $params = [];
        }

        $settings = $this->modules->update_module_settings($module_id, $params);

        return new WP_REST_Response([
            'id'       => $module_id,
            'name'     => $module->get_name(),
            'settings' => $settings,
        ]);
    }
}
