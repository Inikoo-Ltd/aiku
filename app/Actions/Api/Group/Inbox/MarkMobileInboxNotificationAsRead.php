<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inbox;

use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class MarkMobileInboxNotificationAsRead
{
    use AsController;
    use WithMobileInbox;

    public function handle(DatabaseNotification $notification): DatabaseNotification
    {
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification;
    }

    public function asController(string $notification, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->inboxRow($user, $this->handle($this->inboxNotification($user, $notification)))]);
    }
}
