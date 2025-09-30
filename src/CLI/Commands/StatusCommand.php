<?php

namespace Amplisio\AIO\CLI\Commands;

use Amplisio\AIO\Modules\ModuleManager;
use Amplisio\AIO\Repositories\OptionRepository;
use WP_CLI_Command;

class StatusCommand extends WP_CLI_Command
{
    public function __construct(private ModuleManager $modules, private OptionRepository $options)
    {
    }

    public function __invoke(): void
    {
        \WP_CLI::log('Amplisio AIO v' . AMPLISIO_AIO_VERSION);
        \WP_CLI::log('Active modules:');

        foreach ($this->modules->get_modules() as $module) {
            $status = $this->options->is_module_enabled($module->get_id()) ? 'enabled' : 'disabled';
            \WP_CLI::log(sprintf('- %s (%s)', $module->get_name(), $status));
        }
    }
}
