<?php
/**
 * Plugin Name:       Amplisio AIO for WooCommerce
 * Plugin URI:        https://example.com/amplisio-aio
 * Description:       Modular growth suite for WooCommerce with actionable analytics and automation.
 * Version:           1.0.0
 * Author:            Amplisio
 * Author URI:        https://example.com
 * Requires PHP:      8.1
 * Requires at least: 6.3
 * Text Domain:       amplisio-aio
 * Domain Path:       /languages
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * WC requires at least: 8.0
 * WC tested up to:   8.5
 */

define( 'AMPLISIO_AIO_FILE', __FILE__ );
define( 'AMPLISIO_AIO_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMPLISIO_AIO_URL', plugin_dir_url( __FILE__ ) );
define( 'AMPLISIO_AIO_VERSION', '1.0.0' );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Ensure Action Scheduler is available.
if ( ! class_exists( 'ActionScheduler' ) && file_exists( __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
    require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
}

use Amplisio\AIO\Plugin;

if ( ! function_exists( 'amplisio_aio' ) ) {
    function amplisio_aio(): Plugin {
        static $plugin = null;

        if ( null === $plugin ) {
            $plugin = new Plugin( AMPLISIO_AIO_FILE );
        }

        return $plugin;
    }
}

amplisio_aio()->boot();
