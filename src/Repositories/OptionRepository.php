<?php

namespace Amplisio\AIO\Repositories;

class OptionRepository
{
    public const OPTION_KEY = 'amplisio_aio_settings';

    public function initialize_defaults(): void
    {
        $defaults = $this->get_defaults();
        $existing = get_option(self::OPTION_KEY);

        if ( ! is_array($existing) ) {
            update_option(self::OPTION_KEY, $defaults, false);
            return;
        }

        $merged = wp_parse_args($existing, $defaults);
        if ( ! is_array($merged['modules']) ) {
            $merged['modules'] = [];
        }

        update_option(self::OPTION_KEY, $merged, false);
    }

    public function get(): array
    {
        $value = get_option(self::OPTION_KEY, []);
        if ( ! is_array($value) ) {
            $value = [];
        }

        $value = wp_parse_args($value, $this->get_defaults());

        if ( ! is_array($value['modules']) ) {
            $value['modules'] = [];
        }

        return $value;
    }

    public function update(array $settings): void
    {
        $settings = wp_parse_args($settings, $this->get_defaults());
        update_option(self::OPTION_KEY, $settings, false);
    }

    public function get_theme_settings(): array
    {
        $settings = $this->get();
        return $settings['theme'];
    }

    public function get_modules(): array
    {
        $settings = $this->get();
        return $settings['modules'];
    }

    public function is_module_enabled(string $module_id): bool
    {
        $modules = $this->get_modules();
        $module  = $modules[$module_id] ?? false;

        if (is_array($module)) {
            return (bool) ($module['enabled'] ?? false);
        }

        return (bool) $module;
    }

    public function set_module_status(string $module_id, bool $enabled): void
    {
        $settings = $this->get();
        $module   = $settings['modules'][$module_id] ?? [];

        if (is_bool($module)) {
            $module = ['enabled' => $module];
        } elseif ( ! is_array($module) ) {
            $module = [];
        }

        $module['enabled']           = $enabled;
        $settings['modules'][$module_id] = $module;

        $this->update($settings);
    }

    public function update_theme_settings(array $theme): void
    {
        $settings         = $this->get();
        $settings['theme'] = wp_parse_args($theme, $this->get_defaults()['theme']);
        $this->update($settings);
    }

    public function set_module_settings(string $module_id, array $module_settings): void
    {
        $settings                    = $this->get();
        $settings['modules'][$module_id] = $module_settings;
        $this->update($settings);
    }

    private function get_defaults(): array
    {
        return [
            'theme'   => [
                'fontFamily'   => '',
                'primaryColor' => '',
                'accentColor'  => '',
                'radius'       => '',
            ],
            'modules' => [],
        ];
    }
}
