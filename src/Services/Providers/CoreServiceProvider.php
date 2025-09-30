<?php

namespace Amplisio\AIO\Services\Providers;

use Amplisio\AIO\CLI\Commands\ModuleToggleCommand;
use Amplisio\AIO\CLI\Commands\StatusCommand;
use Amplisio\AIO\Modules\AbandonedCart\AbandonedCartModule;
use Amplisio\AIO\Modules\Analytics\AnalyticsModule;
use Amplisio\AIO\Modules\BackInStock\BackInStockModule;
use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Modules\RecoveredCarts\RecoveredCartsModule;
use Amplisio\AIO\Repositories\OptionRepository;
use Amplisio\AIO\Rest\DashboardController;
use Amplisio\AIO\Rest\ModulesController;
use Amplisio\AIO\Rest\SettingsController;
use Amplisio\AIO\Services\Container;
use Amplisio\AIO\Services\Front\ThemeAdapter;
use Amplisio\AIO\Services\Scheduler\ActionSchedulerService;

use function sprintf;

class CoreServiceProvider
{
    public function __construct(private Container $container, private string $plugin_file)
    {
    }

    public function register(): void
    {
        $this->container->singleton(ModuleManager::class, function (Container $container): ModuleManager {
            return new ModuleManager(
                $container->get(OptionRepository::class),
                $container
            );
        });

        $this->container->singleton(ThemeAdapter::class, function (Container $container): ThemeAdapter {
            return new ThemeAdapter($container->get(OptionRepository::class));
        });

        $this->container->singleton(ActionSchedulerService::class, function (Container $container): ActionSchedulerService {
            return new ActionSchedulerService($container->get(ModuleManager::class));
        });
    }

    public function boot(): void
    {
        $module_manager = $this->container->get(ModuleManager::class);

        $module_manager->register_module(new AnalyticsModule());
        $module_manager->register_module(new AbandonedCartModule());
        $module_manager->register_module(new RecoveredCartsModule());
        $module_manager->register_module(new BackInStockModule());

        $module_manager->boot_enabled_modules();

        $this->register_admin_assets();
        $this->register_rest_controllers();
        $this->register_cli_commands();
        $this->register_scheduler();
        $this->register_front_theme();
    }

    private function register_admin_assets(): void
    {
        add_action('admin_menu', [$this, 'register_dashboard_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function register_dashboard_page(): void
    {
        add_menu_page(
            __('Amplisio', 'amplisio-aio'),
            __('Amplisio', 'amplisio-aio'),
            'manage_woocommerce',
            'amplisio-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-line'
        );
    }

    public function render_dashboard_page(): void
    {
        echo '<div class="wrap amplisio-dashboard"><div id="amplisio-aio-dashboard" class="amplisio-dashboard__root" aria-live="polite"></div></div>';
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ( 'toplevel_page_amplisio-dashboard' !== $hook ) {
            return;
        }

        $asset_url = plugins_url('build/admin-dashboard.js', $this->plugin_file);
        $css_url   = plugins_url('build/admin-dashboard.css', $this->plugin_file);

        wp_enqueue_script(
            'amplisio-aio-admin',
            $asset_url,
            [],
            AMPLISIO_AIO_VERSION,
            true
        );

        $module_manager = $this->container->get(ModuleManager::class);
        $options        = $this->container->get(OptionRepository::class);
        $theme_adapter  = $this->container->get(ThemeAdapter::class);

        wp_localize_script('amplisio-aio-admin', 'amplisioAioData', [
            'root'     => esc_url_raw(rest_url('amplisio/v1')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'settings' => $options->get(),
            'themeFallbacks' => $theme_adapter->get_fallbacks(),
            'themeCssVariables' => $theme_adapter->get_css_variables(),
            'modules'  => array_map(
                static function ($module) use ($module_manager): array {
                    $settings = $module_manager->get_module_settings($module->get_id());

                    return [
                        'id'         => $module->get_id(),
                        'name'       => $module->get_name(),
                        'enabled'    => (bool) ($settings['enabled'] ?? false),
                        'capability' => $module->get_capability(),
                        'restRoute'  => sprintf('amplisio/v1/%s', $module->get_rest_base()),
                        'settings'   => $settings,
                    ];
                },
                $module_manager->get_modules()
            ),
        ]);

        wp_enqueue_style('amplisio-aio-admin', $css_url, [], AMPLISIO_AIO_VERSION);
    }

    private function register_rest_controllers(): void
    {
        $dashboard_controller = new DashboardController(
            $this->container->get(ModuleManager::class),
            $this->container->get(OptionRepository::class)
        );

        $settings_controller = new SettingsController(
            $this->container->get(OptionRepository::class),
            $this->container->get(ModuleManager::class)
        );

        $modules_controller = new ModulesController(
            $this->container->get(ModuleManager::class),
            $this->container->get(OptionRepository::class)
        );

        add_action('rest_api_init', static function () use ($dashboard_controller, $settings_controller, $modules_controller): void {
            $dashboard_controller->register_routes();
            $settings_controller->register_routes();
            $modules_controller->register_routes();
        });
    }

    private function register_cli_commands(): void
    {
        if ( defined('WP_CLI') && WP_CLI ) {
            \WP_CLI::add_command('amplisio status', new StatusCommand(
                $this->container->get(ModuleManager::class),
                $this->container->get(OptionRepository::class)
            ));

            \WP_CLI::add_command('amplisio module', new ModuleToggleCommand(
                $this->container->get(ModuleManager::class)
            ));
        }
    }

    private function register_scheduler(): void
    {
        $scheduler = $this->container->get(ActionSchedulerService::class);
        $scheduler->register();
    }

    private function register_front_theme(): void
    {
        add_action('wp_head', function (): void {
            $this->container->get(ThemeAdapter::class)->output_root_variables();
        }, 20);
    }
}
