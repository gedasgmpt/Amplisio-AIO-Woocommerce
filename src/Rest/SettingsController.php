<?php

namespace Amplisio\AIO\Rest;

use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Repositories\OptionRepository;
use Amplisio\AIO\Services\Helpers\Sanitization;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

use function sanitize_key;

class SettingsController extends WP_REST_Controller
{
    public function __construct(private OptionRepository $options, private ModuleManager $modules)
    {
        $this->namespace = 'amplisio/v1';
        $this->rest_base = 'settings';
    }

    public function register_routes(): void
    {
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [$this, 'get_item'],
                    'permission_callback' => [$this, 'permissions_check'],
                ],
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
        return current_user_can('manage_options')
            ? true
            : new WP_Error('rest_forbidden', __('You are not allowed to update Amplisio settings.', 'amplisio-aio'), ['status' => rest_authorization_required_code()]);
    }

    public function get_item(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response($this->options->get());
    }

    public function update_item(WP_REST_Request $request): WP_REST_Response
    {
        $body      = $request->get_json_params();
        $theme_raw = isset($body['theme']) && is_array($body['theme']) ? $body['theme'] : [];
        $modules   = isset($body['modules']) && is_array($body['modules']) ? $body['modules'] : [];

        $theme = Sanitization::sanitize_theme_settings($theme_raw);

        $this->options->update_theme_settings($theme);

        foreach ($modules as $module_id => $payload) {
            $key = sanitize_key($module_id);
            if ('' === $key) {
                continue;
            }

            if (is_array($payload)) {
                $this->modules->update_module_settings($key, $payload);
            } else {
                $this->modules->set_module_status($key, (bool) $payload);
            }
        }

        return new WP_REST_Response($this->options->get());
    }
}
