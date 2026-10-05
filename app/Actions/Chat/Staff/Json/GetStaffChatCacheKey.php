<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\Staff\Json;

use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The key a browser encrypts its saved copy of colleague chats with. It is derived, never stored, and only ever
 * handed to the person it belongs to, so a copy of the browser storage is unreadable without their session.
 */
class GetStaffChatCacheKey
{
    use AsAction;

    public function handle(User $user): string
    {
        return base64_encode(hash_hmac('sha256', 'staff-chat-cache|'.$user->id, (string) config('app.key'), true));
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        return response()
            ->json(['key' => $this->handle($request->user())])
            ->header('Cache-Control', 'no-store, private');
    }
}
