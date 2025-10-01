<?php

namespace AmplisioAIO\REST;

use AmplisioAIO\Core\Helpers\OptionsHelper;
use AmplisioAIO\Core\ModuleManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class SettingsController
{
    private const ROUTE_NAMESPACE = 'amplisio-aio/v1';

    private OptionsHelper $options;

    private ModuleManager $modules;

    public function __construct( OptionsHelper $options, ModuleManager $modules )
    {
        $this->options = $options;
        $this->modules = $modules;
    }

    public function hooks(): void
    {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    public function register_routes(): void
    {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/settings',
            [
                [
                    'methods'             => 'GET',
                    'callback'            => [ $this, 'get_settings' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
                [
                    'methods'             => 'POST',
                    'callback'            => [ $this, 'save_settings' ],
                    'permission_callback' => [ $this, 'permissions_check' ],
                ],
            ]
        );
    }

    public function permissions_check( WP_REST_Request $request ): bool
    {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return false;
        }

        $nonce = $request->get_header( 'X-WP-Nonce' );

        if ( ! $nonce ) {
            $nonce = $request->get_param( '_wpnonce' );
        }

        if ( ! $nonce ) {
            return false;
        }

        return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
    }

    public function get_settings( WP_REST_Request $request ): WP_REST_Response
    {
        return new WP_REST_Response(
            [
                'settings' => $this->options->get_all(),
                'modules'  => $this->modules->all(),
            ]
        );
    }

    public function save_settings( WP_REST_Request $request )
    {
        $payload = $request->get_json_params();

        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'amplisio_aio_invalid_payload', __( 'Invalid payload.', 'amplisio-aio' ), [ 'status' => 400 ] );
        }

        $settings = isset( $payload['settings'] ) && is_array( $payload['settings'] ) ? $payload['settings'] : [];
        $modules  = isset( $payload['modules'] ) && is_array( $payload['modules'] ) ? $payload['modules'] : [];

        $this->options->replace( $this->sanitize_settings( $settings ) );

        foreach ( $modules as $module ) {
            if ( ! isset( $module['slug'], $module['enabled'] ) ) {
                continue;
            }

            $slug    = sanitize_key( (string) $module['slug'] );
            $enabled = (bool) $module['enabled'];

            if ( $enabled ) {
                $this->modules->enable( $slug );
            } else {
                $this->modules->disable( $slug );
            }
        }

        return new WP_REST_Response(
            [
                'settings' => $this->options->get_all(),
                'modules'  => $this->modules->all(),
            ]
        );
    }

    private function sanitize_settings( array $settings ): array
    {
        $sanitized = [];
        foreach ( $settings as $key => $value ) {
            $normalized_key = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $key );
            $normalized_key = $normalized_key !== '' ? $normalized_key : (string) $key;
            $normalized_key_lower = strtolower( $normalized_key );

            if ( 'accentcolor' === $normalized_key_lower && function_exists( 'sanitize_hex_color' ) ) {
                $sanitized[ $normalized_key ] = sanitize_hex_color( $value ) ?: '#2d6cdf';
                continue;
            }

            $sanitized[ $normalized_key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
        }

        return $sanitized;
    }
}
