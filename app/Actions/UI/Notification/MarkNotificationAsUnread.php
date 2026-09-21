<?php

/*
 * Author: Louis Perez
 * Created: Mon, 21 September 2026 10:15
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Notification;

use App\Actions\Traits\WithActionUpdate;
use App\Models\Notifications\Notification;
use Lorisleiva\Actions\ActionRequest;

class MarkNotificationAsUnread
{
    use WithActionUpdate;

    private bool $asAction = false;

    public function handle(Notification $notification): Notification
    {
        return $this->update($notification, [
            'read_at' => null
        ]);
    }

    public function asController(Notification $notification, ActionRequest $request): Notification
    {
        abort_unless($this->belongsToRequester($notification, $request), 403);

        return $this->handle($notification);
    }

    /**
     * The list only ever shows somebody their own, but this takes an id, and returns the row it
     * touched: without this, any signed in user could read the title and body of anybody else's
     * notification by counting upwards, and mark it read behind their back.
     */
    private function belongsToRequester(Notification $notification, ActionRequest $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === (int) $user->id;
    }
}
