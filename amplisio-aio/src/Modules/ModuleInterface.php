<?php

namespace AmplisioAIO\Modules;

use AmplisioAIO\Core\ServiceContainer;

interface ModuleInterface
{
    public function get_slug(): string;

    public function get_name(): string;

    public function get_description(): string;

    public function is_enabled_by_default(): bool;

    public function enable( ServiceContainer $container ): void;

    public function disable( ServiceContainer $container ): void;
}
