<?php

namespace Amplisio\AIO\Services\Scheduler;

use Amplisio\AIO\Modules\ModuleManager;

class ActionSchedulerService
{
    public const HOOK = 'amplisio_aio_scheduled_run';

    public function __construct(private ModuleManager $module_manager)
    {
    }

    public function register(): void
    {
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [$this, 'handle']);
    }

    public function schedule(): void
    {
        if ( ! function_exists('as_has_scheduled_action') ) {
            return;
        }

        if ( ! as_has_scheduled_action(self::HOOK) ) {
            as_schedule_recurring_action(time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK);
        }
    }

    public function handle(): void
    {
        $this->module_manager->run_scheduled_events();
    }
}
