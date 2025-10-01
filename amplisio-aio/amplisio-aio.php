<?php
/**
 * Plugin Name:       Amplisio AIO for WooCommerce
 * Plugin URI:        https://amplisio.com/
 * Description:       Modular auto-styling foundation for WooCommerce with HPOS compatibility.
 * Version:           0.1.0
 * Author:            Amplisio
 * Author URI:        https://amplisio.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       amplisio-aio
 * Domain Path:       /languages
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'AMPLISIO_AIO_VERSION', '0.1.0' );
define( 'AMPLISIO_AIO_PLUGIN_FILE', __FILE__ );
define( 'AMPLISIO_AIO_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMPLISIO_AIO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AMPLISIO_AIO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Display admin notice for missing requirements.
 *
 * @param string $message Notice content.
 * @return void
 */
function amplisio_aio_admin_notice( string $message ): void {
    add_action(
        'admin_notices',
        static function () use ( $message ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
        }
    );
}

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
    amplisio_aio_admin_notice( __( 'Amplisio AIO for WooCommerce requires PHP 8.1 or newer.', 'amplisio-aio' ) );
    return;
}

if ( ! defined( 'WP_DEBUG' ) ) {
    define( 'WP_DEBUG', false );
}

if ( ! did_action( 'woocommerce_loaded' ) && ! class_exists( 'WooCommerce', false ) ) {
    amplisio_aio_admin_notice( __( 'Amplisio AIO for WooCommerce requires WooCommerce to be activated.', 'amplisio-aio' ) );
    return;
}

$autoload = AMPLISIO_AIO_PLUGIN_PATH . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
    require_once $autoload;
} else {
    spl_autoload_register(
        static function ( string $class ): void {
            $prefix = 'AmplisioAIO\\';
            if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
                return;
            }

            $relative = substr( $class, strlen( $prefix ) );
            $relative = str_replace( '\\', DIRECTORY_SEPARATOR, $relative );
            $file     = AMPLISIO_AIO_PLUGIN_PATH . 'src/' . $relative . '.php';

            if ( file_exists( $file ) ) {
                require_once $file;
            }
        }
    );
}

register_activation_hook(
    __FILE__,
    static function (): void {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
            deactivate_plugins( plugin_basename( __FILE__ ) );
            wp_die( esc_html__( 'Amplisio AIO for WooCommerce requires PHP 8.1 or newer.', 'amplisio-aio' ) );
        }
    }
);

add_action(
    'before_woocommerce_init',
    static function (): void {
        if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', AMPLISIO_AIO_PLUGIN_FILE, true );
        }
    }
);

add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain( 'amplisio-aio', false, dirname( AMPLISIO_AIO_PLUGIN_BASENAME ) . '/languages' );

        if ( ! class_exists( \AmplisioAIO\Plugin::class ) ) {
            amplisio_aio_admin_notice( __( 'Amplisio AIO core classes could not be loaded.', 'amplisio-aio' ) );
            return;
        }

        \AmplisioAIO\Plugin::instance()->boot();
    }
);
