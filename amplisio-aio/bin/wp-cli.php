<?php

use AmplisioAIO\Core\ModuleManager;
use AmplisioAIO\Plugin;

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

WP_CLI::add_command(
    'amplisio module list',
    static function (): void {
        /** @var ModuleManager $modules */
        $modules = Plugin::instance()->container()->get( ModuleManager::class );
        $items   = $modules->all();

        WP_CLI\Utils\format_items( 'table', $items, [ 'slug', 'name', 'description', 'enabled' ] );
    }
);

WP_CLI::add_command(
    'amplisio module enable',
    static function ( array $args ): void {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Please provide a module slug.' );
        }

        $slug = sanitize_key( $args[0] );

        /** @var ModuleManager $modules */
        $modules = Plugin::instance()->container()->get( ModuleManager::class );

        if ( ! $modules->enable( $slug ) ) {
            WP_CLI::error( sprintf( 'Module "%s" not found.', $slug ) );
        }

        WP_CLI::success( sprintf( 'Module "%s" enabled.', $slug ) );
    }
);

WP_CLI::add_command(
    'amplisio module disable',
    static function ( array $args ): void {
        if ( empty( $args[0] ) ) {
            WP_CLI::error( 'Please provide a module slug.' );
        }

        $slug = sanitize_key( $args[0] );

        /** @var ModuleManager $modules */
        $modules = Plugin::instance()->container()->get( ModuleManager::class );

        if ( ! $modules->disable( $slug ) ) {
            WP_CLI::error( sprintf( 'Module "%s" not found.', $slug ) );
        }

        WP_CLI::success( sprintf( 'Module "%s" disabled.', $slug ) );
    }
);
