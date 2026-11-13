<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Events\BroadcastPersonalNotification;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use App\Notifications\ShoppingAssistantTeamQuestionNotification;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Puts a question pop-up with an answer box on one person's open aiku tabs, and tells them on the
 * channels they chose. The backup is told this way after a delay, only if nobody has answered.
 */
class AlertShoppingAssistantTeamQuestion
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public function handle(StaffTask $task, User $user): bool
    {
        $task->refresh();
        if (data_get($task->data, 'answered_at') || in_array($user->id, data_get($task->data, 'declined_by', []), true)) {
            return false;
        }

        $notification = new ShoppingAssistantTeamQuestionNotification($task);

        try {
            BroadcastPersonalNotification::dispatch($user->id, [
                'id'    => null,
                'kind'  => 'assistant_question',
                'title' => $notification->title(),
                'body'  => (string) data_get($task->data, 'question'),
                'route' => $notification->url(),
                'question' => StoreShoppingAssistantTeamQuestion::popup($task),
            ]);
            NotifyUserOnChosenChannels::run($user, UserNotificationEnum::ASSISTANT_QUESTION, $notification, $notification->title(), $notification->summary(), $notification->url());
        } catch (Throwable $exception) {
            Log::warning('Assistant question '.$task->reference.' alert to user '.$user->id.' failed: '.$exception->getMessage());

            return false;
        }

        return true;
    }
}
