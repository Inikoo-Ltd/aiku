<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Json;

use App\Http\Resources\Chat\StaffConversationResource;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffConversations
{
    use AsAction;

    public function handle(User $user): Collection
    {
        return StaffConversation::query()
            ->join('staff_conversation_participants as me', function ($join) use ($user) {
                $join->on('me.staff_conversation_id', '=', 'staff_conversations.id')
                    ->where('me.user_id', $user->id)
                    ->whereNull('me.archived_at');
            })
            ->select('staff_conversations.*')
            ->addSelect([
                'unread_count' => StaffMessage::selectRaw('count(*)')
                    ->whereColumn('staff_conversation_id', 'staff_conversations.id')
                    ->where('user_id', '!=', $user->id)
                    ->whereRaw('staff_messages.created_at > coalesce(me.last_read_at, ?)', ['1970-01-01']),
                'last_message_body' => StaffMessage::select('body')
                    ->whereColumn('staff_conversation_id', 'staff_conversations.id')
                    ->latest('id')
                    ->limit(1),
            ])
            ->with('participants')
            ->orderByRaw('staff_conversations.last_message_at desc nulls last')
            ->limit(100)
            ->get();
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return StaffConversationResource::collection($this->handle($request->user()));
    }
}
