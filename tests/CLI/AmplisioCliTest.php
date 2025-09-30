<?php

use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Plugin;
use Amplisio\AIO\Repositories\OptionRepository;

if ( ! class_exists('WP_CLI') ) {
    class WP_CLI
    {
        public static array $commands = [];
        public static array $messages = [];

        public static function add_command(string $name, $callable): void
        {
            self::$commands[$name] = $callable;
        }

        public static function log(string $message): void
        {
            self::$messages[] = $message;
        }

        public static function success(string $message): void
        {
            self::$messages[] = $message;
        }

        public static function error(string $message): void
        {
            throw new RuntimeException($message);
        }

        public static function reset(): void
        {
            self::$messages = [];
        }
    }
}

if ( ! defined('WP_CLI') ) {
    define('WP_CLI', true);
}

class AmplisioCliTest extends WP_UnitTestCase
{
    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        WP_CLI::$commands = [];
        WP_CLI::reset();

        delete_option(OptionRepository::OPTION_KEY);
        delete_option('amplisio_aio_abandoned_sequences');

        $this->plugin = new Plugin(AMPLISIO_AIO_FILE);
        $this->plugin->init();
    }

    public function test_status_command_outputs_registered_modules(): void
    {
        $this->assertArrayHasKey('amplisio status', WP_CLI::$commands);

        WP_CLI::reset();
        call_user_func(WP_CLI::$commands['amplisio status']);

        $this->assertNotEmpty(WP_CLI::$messages);
        $this->assertStringContainsString('Amplisio AIO v', WP_CLI::$messages[0]);

        $this->assertTrue($this->arrayContainsSubstring(WP_CLI::$messages, 'Intelligence'));
    }

    public function test_module_command_toggles_module_status(): void
    {
        $this->assertArrayHasKey('amplisio module', WP_CLI::$commands);

        /** @var ModuleManager $manager */
        $manager = $this->plugin->container()->get(ModuleManager::class);
        $settings = $manager->get_module_settings('abandoned_cart');
        $this->assertFalse($settings['enabled']);

        WP_CLI::reset();
        call_user_func(WP_CLI::$commands['amplisio module'], 'enable', 'abandoned_cart');

        $settings = $manager->get_module_settings('abandoned_cart');
        $this->assertTrue($settings['enabled']);

        $this->assertTrue($this->arrayContainsSubstring(WP_CLI::$messages, 'Enabled Abandoned cart recovery'));
    }

    private function arrayContainsSubstring(array $messages, string $needle): bool
    {
        foreach ($messages as $message) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}
