<?php

namespace AmplisioAIO\Modules\_Example;

use AmplisioAIO\Core\ServiceContainer;
use AmplisioAIO\Modules\ModuleInterface;

class ExampleModule implements ModuleInterface
{
    public function get_slug(): string
    {
        return 'example';
    }

    public function get_name(): string
    {
        return __( 'Example Module', 'amplisio-aio' );
    }

    public function get_description(): string
    {
        return __( 'Demonstrates how modules can be toggled on and off.', 'amplisio-aio' );
    }

    public function is_enabled_by_default(): bool
    {
        return true;
    }

    public function enable( ServiceContainer $container ): void
    {
        add_action(
            'init',
            static function (): void {
                do_action( 'amplisio_aio_example_module_enabled' );
            }
        );
    }

    public function disable( ServiceContainer $container ): void
    {
        remove_all_actions( 'amplisio_aio_example_module_enabled' );
    }
}
