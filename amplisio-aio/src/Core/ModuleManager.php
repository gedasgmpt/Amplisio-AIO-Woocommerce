<?php

namespace AmplisioAIO\Core;

use AmplisioAIO\Modules\ModuleInterface;
use AmplisioAIO\Core\Helpers\OptionsHelper;

class ModuleManager
{
    private ServiceContainer $container;

    /**
     * @var array<string, ModuleInterface>
     */
    private array $modules = [];

    private OptionsHelper $options;

    public function __construct( ServiceContainer $container, string $option_name, ?OptionsHelper $options = null )
    {
        $this->container = $container;
        $this->options   = $options ?: new OptionsHelper( $option_name );
    }

    public function register_module( ModuleInterface $module ): void
    {
        $slug = $module->get_slug();
        $this->modules[ $slug ] = $module;

        if ( $this->is_enabled( $slug ) ) {
            $module->enable( $this->container );
        }
    }

    public function all(): array
    {
        $statuses = $this->options->get_all();
        $result   = [];

        foreach ( $this->modules as $slug => $module ) {
            $enabled = $statuses[ $slug ] ?? $module->is_enabled_by_default();
            $result[] = [
                'slug'        => $slug,
                'name'        => $module->get_name(),
                'description' => $module->get_description(),
                'enabled'     => (bool) $enabled,
            ];
        }

        return $result;
    }

    public function is_enabled( string $slug ): bool
    {
        $statuses = $this->options->get_all();

        if ( isset( $statuses[ $slug ] ) ) {
            return (bool) $statuses[ $slug ];
        }

        if ( ! isset( $this->modules[ $slug ] ) ) {
            return false;
        }

        return $this->modules[ $slug ]->is_enabled_by_default();
    }

    public function enable( string $slug ): bool
    {
        if ( ! isset( $this->modules[ $slug ] ) ) {
            return false;
        }

        $statuses            = $this->options->get_all();
        $statuses[ $slug ]   = true;
        $this->options->replace( $statuses );

        $this->modules[ $slug ]->enable( $this->container );

        return true;
    }

    public function disable( string $slug ): bool
    {
        if ( ! isset( $this->modules[ $slug ] ) ) {
            return false;
        }

        $statuses            = $this->options->get_all();
        $statuses[ $slug ]   = false;
        $this->options->replace( $statuses );

        $this->modules[ $slug ]->disable( $this->container );

        return true;
    }

    public function get( string $slug ): ?ModuleInterface
    {
        return $this->modules[ $slug ] ?? null;
    }
}
