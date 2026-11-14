<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Tasks\GetAikuAssistant;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer never waits for an answer that will not come: a question nobody answered is closed as
 * if everybody had said they did not want to answer, so the customer is told to contact customer
 * services. It closes CLOSE_AFTER_HOURS after the gurus first had it in their morning round
 * (round_first_at, SendShoppingAssistantMorningRound), so a Friday evening question gets Monday as
 * a full working day (a question the customer adds to the task starts that clock again); one the round then raised to the managers (managers_told_at) gets another
 * CLOSE_AFTER_HOURS from then, and one that never reached a round closes after NO_ROUND_CLOSE_AFTER_HOURS. Runs
 * hourly and closes only in the organisation's working hours, so the email or the customer services
 * task lands when people work.
 */
class CloseUnansweredShoppingAssistantTeamQuestions
{
    use AsAction;

    public const int CLOSE_AFTER_HOURS = 24;

    public const int NO_ROUND_CLOSE_AFTER_HOURS = 72;

    public string $commandSignature = 'shopping-assistant:close-unanswered-questions';

    public string $commandDescription = 'Close the questions for our team nobody answered within a day and tell the customers';

    public function handle(): int
    {
        $closed = 0;

        StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)
            ->whereNull('data->answered_at')
            ->where('created_at', '<', now()->subHours(self::CLOSE_AFTER_HOURS))
            ->get()
            ->filter(fn (StaffTask $task) => $task->status->isOpen() && self::isDue($task))
            ->each(function (StaffTask $task) use (&$closed) {
                $organisation = Organisation::find(data_get($task->data, 'organisation_id'));
                if ($organisation && !RemindShoppingAssistantSalesLeads::make()->isWorkingTime($organisation)) {
                    return;
                }

                DeclineShoppingAssistantTeamQuestion::make()->close($task, GetAikuAssistant::run($task->group_id), __('Nobody answered within a working day: the customer was asked to contact customer services.'));
                $closed++;
            });

        return $closed;
    }

    public static function isDue(StaffTask $task): bool
    {
        $managersTold = data_get($task->data, 'managers_told_at');
        if ($managersTold) {
            return CarbonImmutable::parse($managersTold)->lt(now()->subHours(self::CLOSE_AFTER_HOURS));
        }

        $firstRound = data_get($task->data, 'round_first_at');

        return $firstRound
            ? CarbonImmutable::parse($firstRound)->lt(now()->subHours(self::CLOSE_AFTER_HOURS))
            : CarbonImmutable::parse(collect(data_get($task->data, 'more_questions', []))->pluck('asked_at')->filter()->last() ?? $task->created_at)->lt(now()->subHours(self::NO_ROUND_CLOSE_AFTER_HOURS));
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info('Closed '.$this->handle().' unanswered questions');

        return 0;
    }
}
