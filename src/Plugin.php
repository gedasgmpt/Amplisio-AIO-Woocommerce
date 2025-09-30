<?php

namespace Amplisio\AIO;

use Amplisio\AIO\Repositories\OptionRepository;
use Amplisio\AIO\Services\Container;
use Amplisio\AIO\Services\Providers\CoreServiceProvider;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

class Plugin
{
    private string $file;

    private Container $container;

    public function __construct(string $file)
    {
        $this->file      = $file;
        $this->container = new Container();
    }

    public function boot(): void
    {
        register_activation_hook($this->file, [self::class, 'activate']);
        register_deactivation_hook($this->file, [self::class, 'deactivate']);

        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init(): void
    {
        load_plugin_textdomain('amplisio-aio', false, dirname(plugin_basename($this->file)) . '/languages/');

        $this->container->singleton(Container::class, fn (): Container => $this->container);
        $this->container->singleton(OptionRepository::class, fn (): OptionRepository => new OptionRepository());

        $core = new CoreServiceProvider($this->container, $this->file);
        $core->register();
        $core->boot();
    }

    public static function activate(): void
    {
        $repository = new OptionRepository();
        $repository->initialize_defaults();
    }

    public static function deactivate(): void
    {
        // Placeholder for potential cleanup in future versions.
    }

    public function declare_hpos_compatibility(): void
    {
        if ( class_exists(FeaturesUtil::class) ) {
            FeaturesUtil::declare_compatibility('custom_order_tables', plugin_basename($this->file), true);
        }
    }

    public function container(): Container
    {
        return $this->container;
    }
}
