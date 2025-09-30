<?php

namespace Amplisio\AIO\Modules;

use Amplisio\AIO\Services\Container;

use function wp_parse_args;

abstract class AbstractModule implements ModuleInterface
{
    public function register(Container $container): void
    {
        // Default no-op.
    }

    public function boot(Container $container): void
    {
        // Default no-op.
    }

    public function get_dashboard_cards(): array
    {
        return [];
    }

    public function get_capability(): string
    {
        return 'manage_woocommerce';
    }

    public function sanitize_settings(array $settings): array
    {
        $defaults   = $this->get_default_settings();
        $sanitized  = wp_parse_args($settings, $defaults);

        if (array_key_exists('enabled', $defaults)) {
            $sanitized['enabled'] = (bool) $sanitized['enabled'];
        }

        return $sanitized;
    }

    public function get_rest_base(): string
    {
        return $this->get_id();
    }

    public function run_scheduled_event(): void
    {
        // Default no-op.
    }
}
