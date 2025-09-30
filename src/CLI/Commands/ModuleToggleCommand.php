<?php

namespace Amplisio\AIO\CLI\Commands;

use Amplisio\AIO\Modules\ModuleManager;
use WP_CLI_Command;

class ModuleToggleCommand extends WP_CLI_Command
{
    public function __construct(private ModuleManager $modules)
    {
    }

    /**
     * Toggle module state.
     *
     * ## OPTIONS
     *
     * <action>
     * : Action to perform (enable or disable).
     *
     * <module>
     * : Module identifier.
     */
    public function __invoke(string $action, string $module_id): void
    {
        $module_id = sanitize_key($module_id);
        $modules   = $this->modules->get_modules();

        if ( ! isset($modules[$module_id]) ) {
            \WP_CLI::error(sprintf('Module "%s" is not registered.', $module_id));
            return;
        }

        $enable = 'enable' === strtolower($action);

        $this->modules->set_module_status($module_id, $enable);

        \WP_CLI::success(sprintf('%s %s', $enable ? 'Enabled' : 'Disabled', $modules[$module_id]->get_name()));
    }
}
