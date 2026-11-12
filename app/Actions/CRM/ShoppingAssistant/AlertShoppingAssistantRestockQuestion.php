<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Tasks\StaffTask;
use App\Notifications\ShoppingAssistantRestockQuestionNotification;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Tells the organisation's restock question recipients (chosen people, else its buyers) of a
 * question, each on the channels they chose; one failing recipient never stops the others.
 */
class AlertShoppingAssistantRestockQuestion
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
        foreach (StoreShoppingAssistantRestockQuestion::recipientsOf($organisation) as $recipient) {
            try {
                $notification = new ShoppingAssistantRestockQuestionNotification($task, $isReminder);
                NotifyUserOnChosenChannels::run($recipient, UserNotificationEnum::RESTOCK_QUESTION, $notification, $notification->title(), $notification->summary(), $notification->url());
                $told++;
            } catch (Throwable $exception) {
                Log::warning('Restock question '.$task->reference.' alert to user '.$recipient->id.' failed: '.$exception->getMessage());
            }
        }

        return $told;
    }
}
