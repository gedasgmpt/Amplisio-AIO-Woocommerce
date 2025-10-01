<?php

namespace AmplisioAIO\Tests;

use AmplisioAIO\Core\Helpers\OptionsHelper;
use AmplisioAIO\Core\ModuleManager;
use AmplisioAIO\Core\ServiceContainer;
use AmplisioAIO\Modules\ModuleInterface;
use PHPUnit\Framework\TestCase;

class ModuleManagerTest extends TestCase
{
    public function testEnableAndDisableModule(): void
    {
        $store = [];
        $options = new OptionsHelper(
            'amplisio_modules',
            static function ( string $option_name, $default = [] ) use ( &$store ) {
                return $store[ $option_name ] ?? $default;
            },
            static function ( string $option_name, $value ) use ( &$store ) {
                $store[ $option_name ] = $value;

                return true;
            }
        );

        $container = new ServiceContainer();
        $manager   = new ModuleManager( $container, 'amplisio_modules', $options );

        $module = new class() implements ModuleInterface {
            public bool $enabled = false;

            public function get_slug(): string
            {
                return 'test-module';
            }

            public function get_name(): string
            {
                return 'Test Module';
            }

            public function get_description(): string
            {
                return 'A module used for testing.';
            }

            public function is_enabled_by_default(): bool
            {
                return false;
            }

            public function enable( ServiceContainer $container ): void
            {
                $this->enabled = true;
            }

            public function disable( ServiceContainer $container ): void
            {
                $this->enabled = false;
            }
        };

        $manager->register_module( $module );

        self::assertFalse( $manager->is_enabled( 'test-module' ) );

        $manager->enable( 'test-module' );

        self::assertTrue( $manager->is_enabled( 'test-module' ) );
        self::assertTrue( $module->enabled );

        $manager->disable( 'test-module' );

        self::assertFalse( $manager->is_enabled( 'test-module' ) );
        self::assertFalse( $module->enabled );
    }
}
