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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class MarkAllMobileInboxNotificationsAsRead
{
    use AsController;
    use WithMobileInbox;

    public function handle(User $user): int
    {
        return $this->inboxQuery($user)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->handle($user);

        return response()->json(['data' => ['unread' => 0]]);
    }
}
