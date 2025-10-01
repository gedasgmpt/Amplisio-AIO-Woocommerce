<?php

namespace AmplisioAIO;

use AmplisioAIO\AdminUI\SettingsPage;
use AmplisioAIO\Core\Helpers\AssetHelper;
use AmplisioAIO\Core\Helpers\OptionsHelper;
use AmplisioAIO\Core\Helpers\ThemeTokenHelper;
use AmplisioAIO\Core\ModuleManager;
use AmplisioAIO\Core\ServiceContainer;
use AmplisioAIO\Core\FrontAssets;
use AmplisioAIO\Modules\_Example\ExampleModule;
use AmplisioAIO\REST\SettingsController;

class Plugin
{
    private static ?self $instance = null;

    private ServiceContainer $container;

    private bool $booted = false;

    public static function instance(): self
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void
    {
        if ( $this->booted ) {
            return;
        }

        $this->container = new ServiceContainer();

        $this->register_services();

        do_action( 'amplisio_aio_loaded', $this->container );

        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            require_once AMPLISIO_AIO_PLUGIN_PATH . 'bin/wp-cli.php';
        }

        $this->booted = true;
    }

    public function container(): ServiceContainer
    {
        return $this->container;
    }

    private function register_services(): void
    {
        $this->container->singleton(
            OptionsHelper::class,
            static fn (): OptionsHelper => new OptionsHelper( 'amplisio_aio_settings' )
        );

        $this->container->singleton(
            AssetHelper::class,
            static fn (): AssetHelper => new AssetHelper(
                AMPLISIO_AIO_PLUGIN_PATH . 'assets/manifest.json',
                AMPLISIO_AIO_PLUGIN_URL . 'assets/'
            )
        );

        $this->container->singleton(
            ThemeTokenHelper::class,
            static fn (): ThemeTokenHelper => new ThemeTokenHelper()
        );

        $this->container->singleton(
            ModuleManager::class,
            function (): ModuleManager {
                $module_manager = new ModuleManager( $this->container, 'amplisio_aio_modules' );
                $module_manager->register_module( new ExampleModule() );

                return $module_manager;
            }
        );

        $this->container->singleton(
            SettingsPage::class,
            function (): SettingsPage {
                $page = new SettingsPage(
                    $this->container->get( OptionsHelper::class ),
                    $this->container->get( AssetHelper::class ),
                    $this->container->get( ModuleManager::class ),
                    $this->container->get( ThemeTokenHelper::class )
                );

                $page->hooks();

                return $page;
            }
        );

        $this->container->singleton(
            SettingsController::class,
            function (): SettingsController {
                $controller = new SettingsController(
                    $this->container->get( OptionsHelper::class ),
                    $this->container->get( ModuleManager::class )
                );

                $controller->hooks();

                return $controller;
            }
        );

        $this->container->singleton(
            FrontAssets::class,
            function (): FrontAssets {
                $front = new FrontAssets(
                    $this->container->get( AssetHelper::class ),
                    $this->container->get( OptionsHelper::class )
                );

                $front->hooks();

                return $front;
            }
        );
    }
}
