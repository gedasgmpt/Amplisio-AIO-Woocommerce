<?php

namespace Amplisio\AIO\Modules;

use Amplisio\AIO\Repositories\OptionRepository;
use Amplisio\AIO\Services\Container;

class ModuleManager
{
    /**
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    public function __construct(private OptionRepository $options, private Container $container)
    {
    }

    public function register_module(ModuleInterface $module): void
    {
        $this->modules[$module->get_id()] = $module;
        $module->register($this->container);

        $stored = $this->options->get_modules()[$module->get_id()] ?? [];

        if (is_bool($stored)) {
            $stored = ['enabled' => $stored];
        } elseif ( ! is_array($stored) ) {
            $stored = [];
        }

        $sanitized = $module->sanitize_settings($stored);
        $this->options->set_module_settings($module->get_id(), $sanitized);
    }

    public function boot_enabled_modules(): void
    {
        foreach ($this->modules as $module) {
            if ( $this->options->is_module_enabled($module->get_id()) ) {
                $module->boot($this->container);
            }
        }
    }

    public function get_modules(): array
    {
        return $this->modules;
    }

    public function get_module(string $module_id): ?ModuleInterface
    {
        return $this->modules[$module_id] ?? null;
    }

    public function enabled_modules(): array
    {
        return array_filter(
            $this->modules,
            fn (ModuleInterface $module): bool => $this->options->is_module_enabled($module->get_id())
        );
    }

    public function set_module_status(string $module_id, bool $enabled): void
    {
        if ( ! isset($this->modules[$module_id]) ) {
            return;
        }

        $settings             = $this->get_module_settings($module_id);
        $settings['enabled']  = $enabled;

        $this->update_module_settings($module_id, $settings);
    }

    public function get_dashboard_cards(): array
    {
        $cards = [];
        foreach ($this->enabled_modules() as $module) {
            $cards = array_merge($cards, $module->get_dashboard_cards());
        }

        return $cards;
    }

    public function run_scheduled_events(): void
    {
        foreach ($this->enabled_modules() as $module) {
            $module->run_scheduled_event();
        }
    }

    public function get_module_settings(string $module_id): array
    {
        if ( ! isset($this->modules[$module_id]) ) {
            return [];
        }

        $stored = $this->options->get_modules()[$module_id] ?? [];

        if (is_bool($stored)) {
            $stored = ['enabled' => $stored];
        } elseif ( ! is_array($stored) ) {
            $stored = [];
        }

        return $this->modules[$module_id]->sanitize_settings($stored);
    }

    public function update_module_settings(string $module_id, array $settings): array
    {
        if ( ! isset($this->modules[$module_id]) ) {
            return [];
        }

        $current = $this->get_module_settings($module_id);
        $merged  = array_merge($current, $settings);
        $clean   = $this->modules[$module_id]->sanitize_settings($merged);

        $this->options->set_module_settings($module_id, $clean);

        return $clean;
    }
}
