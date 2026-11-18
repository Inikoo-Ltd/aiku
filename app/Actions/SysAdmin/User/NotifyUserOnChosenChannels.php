<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SysAdmin\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Tells a user inside aiku, and also by email, Slack or browser as they chose for this event on their profile.
 */
class NotifyUserOnChosenChannels
{
    use AsAction;

    /**
     * @param Notification&object{byEmail: bool} $notification
     */
    public function handle(User $user, UserNotificationEnum $event, Notification $notification, string $title, string $body, string $url): void
    {
        $channels = $event->deliverTo($user);

        $notification->byEmail = in_array('email', $channels, true) && (bool) $user->email;
        $user->notify($notification);

        if (in_array('slack', $channels, true) && $user->slack_user_id) {
            SendTicketSlackDirectMessage::dispatch($user, '*'.$title."*\n".$body.' <'.$url.'|'.__('Open in aiku').'>');
        }

        if (in_array('browser', $channels, true) && $user->pushSubscriptions()->exists()) {
            SendUserPushNotification::dispatch($user, [
                'title' => $title,
                'body'  => Str::limit($body, 200),
                'url'   => $url,
                'tag'   => $event->value,
            ]);
        }
    }
}
