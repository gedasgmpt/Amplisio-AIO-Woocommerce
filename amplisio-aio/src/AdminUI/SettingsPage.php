<?php

namespace AmplisioAIO\AdminUI;

use AmplisioAIO\Core\Helpers\AssetHelper;
use AmplisioAIO\Core\Helpers\OptionsHelper;
use AmplisioAIO\Core\Helpers\ThemeTokenHelper;
use AmplisioAIO\Core\ModuleManager;

class SettingsPage
{
    private OptionsHelper $options;

    private AssetHelper $assets;

    private ModuleManager $modules;

    private ThemeTokenHelper $theme_tokens;

    private string $page_hook = '';

    public function __construct(
        OptionsHelper $options,
        AssetHelper $assets,
        ModuleManager $modules,
        ThemeTokenHelper $theme_tokens
    ) {
        $this->options      = $options;
        $this->assets       = $assets;
        $this->modules      = $modules;
        $this->theme_tokens = $theme_tokens;
    }

    public function hooks(): void
    {
        add_action( 'admin_menu', [ $this, 'register_page' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function register_page(): void
    {
        $this->page_hook = add_submenu_page(
            'woocommerce',
            __( 'Amplisio AIO', 'amplisio-aio' ),
            __( 'Amplisio AIO', 'amplisio-aio' ),
            'manage_woocommerce',
            'amplisio-aio',
            [ $this, 'render' ],
            56
        );
    }

    public function enqueue_assets( string $hook ): void
    {
        if ( $hook !== $this->page_hook ) {
            return;
        }

        $this->assets->enqueue( 'admin', 'amplisio-aio-admin', [ 'wp-api-fetch' ] );

        wp_enqueue_script( 'wp-api-fetch' );

        wp_add_inline_script(
            'amplisio-aio-admin',
            'window.amplisioAioSettings = ' . wp_json_encode( $this->get_boot_data() ) . ';',
            'before'
        );
    }

    public function render(): void
    {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to access this page.', 'amplisio-aio' ) );
        }

        echo '<div class="wrap amplisio-aio-admin">';
        echo '<h1>' . esc_html__( 'Amplisio AIO Settings', 'amplisio-aio' ) . '</h1>';
        echo '<div id="amplisio-aio-settings-root"></div>';
        echo '</div>';
    }

    private function get_boot_data(): array
    {
        return [
            'restUrl'   => rest_url( 'amplisio-aio/v1/settings' ),
            'nonce'     => wp_create_nonce( 'wp_rest' ),
            'settings'  => $this->options->get_all(),
            'modules'   => $this->modules->all(),
            'theme'     => $this->theme_tokens->tokens(),
            'i18n'      => [
                'intro'   => __( 'Control Amplisio styling from a single dashboard.', 'amplisio-aio' ),
                'general' => __( 'General settings', 'amplisio-aio' ),
                'modules' => __( 'Modules', 'amplisio-aio' ),
                'accentColor' => __( 'Accent color', 'amplisio-aio' ),
                'save'    => __( 'Save settings', 'amplisio-aio' ),
                'enabled' => __( 'Enabled', 'amplisio-aio' ),
                'disabled'=> __( 'Disabled', 'amplisio-aio' ),
                'success' => __( 'Settings saved successfully.', 'amplisio-aio' ),
                'error'   => __( 'An error occurred while saving.', 'amplisio-aio' ),
            ],
        ];
    }
}
