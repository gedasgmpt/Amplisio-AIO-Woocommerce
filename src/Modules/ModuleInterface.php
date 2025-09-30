<?php

namespace Amplisio\AIO\Modules;

use Amplisio\AIO\Services\Container;

interface ModuleInterface
{
    public function get_id(): string;

    public function get_name(): string;

    public function register(Container $container): void;

    public function boot(Container $container): void;

    public function get_default_settings(): array;

    public function get_dashboard_cards(): array;

    public function get_capability(): string;

    public function sanitize_settings(array $settings): array;

    public function get_rest_base(): string;

    public function run_scheduled_event(): void;
}
