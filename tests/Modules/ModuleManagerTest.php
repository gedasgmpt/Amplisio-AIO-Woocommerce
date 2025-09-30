<?php

use Amplisio\AIO\Modules\AbstractModule;
use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Repositories\OptionRepository;
use Amplisio\AIO\Services\Container;

class ModuleManagerTest extends WP_UnitTestCase
{
    private ModuleManager $manager;
    private OptionRepository $options;
    private Container $container;
    private ModuleManagerTestModule $module;

    protected function setUp(): void
    {
        parent::setUp();

        delete_option(OptionRepository::OPTION_KEY);

        $this->options   = new OptionRepository();
        $this->container = new Container();
        $this->container->singleton(OptionRepository::class, fn (): OptionRepository => $this->options);

        $this->manager = new ModuleManager($this->options, $this->container);
        $this->module  = new ModuleManagerTestModule();
    }

    public function test_register_module_persists_default_settings(): void
    {
        $this->manager->register_module($this->module);

        $this->assertTrue($this->module->registered);

        $modules = $this->options->get_modules();
        $this->assertArrayHasKey('test-module', $modules);
        $this->assertSame([
            'enabled'   => false,
            'threshold' => 10,
        ], $modules['test-module']);
    }

    public function test_boot_enabled_modules_only_boots_enabled_entries(): void
    {
        $this->manager->register_module($this->module);
        $this->manager->boot_enabled_modules();

        $this->assertFalse($this->module->booted);

        $this->manager->set_module_status('test-module', true);
        $this->manager->boot_enabled_modules();

        $this->assertTrue($this->module->booted);
    }

    public function test_update_module_settings_merges_and_sanitizes(): void
    {
        $this->manager->register_module($this->module);

        $settings = $this->manager->update_module_settings('test-module', [
            'enabled'   => '1',
            'threshold' => -5,
        ]);

        $this->assertSame([
            'enabled'   => true,
            'threshold' => 0,
        ], $settings);

        $stored = $this->manager->get_module_settings('test-module');
        $this->assertSame($settings, $stored);
    }
}

class ModuleManagerTestModule extends AbstractModule
{
    public bool $registered = false;
    public bool $booted = false;

    public function get_id(): string
    {
        return 'test-module';
    }

    public function get_name(): string
    {
        return 'Test Module';
    }

    public function register(Container $container): void
    {
        $this->registered = true;
    }

    public function boot(Container $container): void
    {
        $this->booted = true;
    }

    public function get_default_settings(): array
    {
        return [
            'enabled'   => false,
            'threshold' => 10,
        ];
    }

    public function sanitize_settings(array $settings): array
    {
        $sanitized = parent::sanitize_settings($settings);
        $sanitized['threshold'] = max(0, (int) $sanitized['threshold']);

        return $sanitized;
    }
}
