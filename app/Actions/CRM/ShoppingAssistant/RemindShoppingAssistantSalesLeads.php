<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\Tasks\StaffTaskStatusEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A big order lead or a restock question nobody has taken (no assignee, still to do) after
 * services.shopping_assistant.sales_lead_reminder_hours is raised again to whoever it went to (sales
 * leads managers and the Slack channel, or the restock question recipients), then once a day until someone takes it or it is closed.
 * Reminders go out only on working days between 09:00 and 18:00 in the organisation's time zone,
 * so a lead from a Friday night is raised on Monday morning rather than at 03:00 on Saturday.
 *
 * ponytail: working hours are fixed at 09:00-18:00 Monday to Friday; read them from the
 * organisation if one keeps other hours.
 */
class RemindShoppingAssistantSalesLeads
{
    use AsAction;

    public string $commandSignature = 'shopping-assistant:remind-sales-leads';

    public string $commandDescription = 'Raise again the big order leads and restock questions from the AI shopping assistant that nobody has taken';

    public const int WORKDAY_STARTS = 9;

    public const int WORKDAY_ENDS = 18;

    public function handle(): int
    {
        $reminded = 0;
        $dueBy    = now()->subHours((int) config('services.shopping_assistant.sales_lead_reminder_hours'));

        StaffTask::whereIn('data->kind', [StoreShoppingAssistantSalesLead::TASK_KIND, StoreShoppingAssistantRestockQuestion::TASK_KIND])
            ->where('status', StaffTaskStatusEnum::TODO->value)
            ->whereNull('assignee_id')
            ->where('created_at', '<=', $dueBy)
            ->get()
            ->each(function (StaffTask $task) use (&$reminded) {
                $organisation = Organisation::find(data_get($task->data, 'organisation_id'));
                if (!$organisation || !$this->isWorkingTime($organisation)) {
                    return;
                }

                $lastReminder = data_get($task->data, 'reminded_at');
                if ($lastReminder && CarbonImmutable::parse($lastReminder)->gt(now()->subDay())) {
                    return;
                }

                data_get($task->data, 'kind') === StoreShoppingAssistantRestockQuestion::TASK_KIND
                    ? AlertShoppingAssistantRestockQuestion::run($task, true)
                    : AlertShoppingAssistantSalesLead::run($task, true);
                $task->update(['data' => [...$task->data, 'reminded_at' => now()->toIso8601String(), 'reminders' => (int) data_get($task->data, 'reminders', 0) + 1]]);
                $reminded++;
            });

        return $reminded;
    }

    public function isWorkingTime(Organisation $organisation): bool
    {
        $local = now()->setTimezone($organisation->timezone?->name ?? 'UTC');

        return $local->isWeekday() && $local->hour >= self::WORKDAY_STARTS && $local->hour < self::WORKDAY_ENDS;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info('Reminded '.$this->handle().' sales leads');

        return 0;
    }
}
