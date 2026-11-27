<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Actions\Tasks\GetAikuAssistant;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use App\Notifications\ShoppingAssistantSalesLeadNotification;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Tells the sales leads managers of a big order lead, each on the channels they chose for sales
 * leads. Each recipient fails on their own: one bad email address never stops the others.
 */
class AlertShoppingAssistantSalesLead
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function handle(StaffTask $task, bool $isReminder): int
    {
        $organisation = Organisation::find(data_get($task->data, 'organisation_id'));
        if (!$organisation) {
            return 0;
        }

        $told = 0;
        foreach (StoreShoppingAssistantSalesLead::managersOf($organisation, GetAikuAssistant::run($task->group_id)) as $manager) {
            try {
                $notification = new ShoppingAssistantSalesLeadNotification($task, $isReminder);
                NotifyUserOnChosenChannels::run($manager, UserNotificationEnum::SALES_LEAD, $notification, $notification->title(), $notification->summary(), $notification->url());
                $told++;
            } catch (Throwable $exception) {
                Log::warning('Sales lead '.$task->reference.' alert to user '.$manager->id.' failed: '.$exception->getMessage());
            }
        }

        return $told;
    }
}
