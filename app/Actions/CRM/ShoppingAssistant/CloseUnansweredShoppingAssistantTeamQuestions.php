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
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A customer never waits for an answer that will not come: a question nobody answered within
 * CLOSE_AFTER_HOURS is closed as if everybody had said they did not want to answer, so the
 * customer is told to contact customer services. Runs hourly and closes only in the
 * organisation's working hours, so the email or the customer services task lands when people work.
 *
 * ponytail: a flat 24 hours, so a Friday evening question closes on Monday morning without a
 * working day for an answer; count working hours when that shows up in the closed questions.
 */
class CloseUnansweredShoppingAssistantTeamQuestions
{
    use AsAction;

    public const int CLOSE_AFTER_HOURS = 24;

    public string $commandSignature = 'shopping-assistant:close-unanswered-questions';

    public string $commandDescription = 'Close the questions for our team nobody answered within a day and tell the customers';

    public function handle(): int
    {
        $closed = 0;

        StaffTask::where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)
            ->whereNull('data->answered_at')
            ->where('created_at', '<', now()->subHours(self::CLOSE_AFTER_HOURS))
            ->get()
            ->each(function (StaffTask $task) use (&$closed) {
                $organisation = Organisation::find(data_get($task->data, 'organisation_id'));
                if ($organisation && !RemindShoppingAssistantSalesLeads::make()->isWorkingTime($organisation)) {
                    return;
                }

                DeclineShoppingAssistantTeamQuestion::make()->close($task, GetAikuAssistant::run($task->group_id), __('Nobody answered within a day: the customer was asked to contact customer services.'));
                $closed++;
            });

        return $closed;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info('Closed '.$this->handle().' unanswered questions');

        return 0;
    }
}
