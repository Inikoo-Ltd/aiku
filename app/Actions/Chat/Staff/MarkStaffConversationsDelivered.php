<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Events\StaffConversationDelivered;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkStaffConversationsDelivered
{
    use AsAction;

    public const int MAX_CONVERSATIONS = 50;

    /**
     * The receiving browser acknowledges, in one request per burst, the conversations whose messages it has got. Only a
     * conversation with a message from someone else newer than what was already acknowledged (or read) is written and
     * broadcast, so a reload or a repeat changes nothing and costs no broadcast.
     *
     * @param array<int, string> $conversationUlids
     *
     * @return array<int, string> the conversations that moved
     */
    public function handle(User $user, array $conversationUlids): array
    {
        $deliveredAt = now();
        $moved       = [];

        $conversations = StaffConversation::whereIn('ulid', array_slice(array_unique($conversationUlids), 0, self::MAX_CONVERSATIONS))
            ->where('group_id', $user->group_id)
            ->get();

        foreach ($conversations as $conversation) {
            $pivot = DB::table('staff_conversation_participants')
                ->where('staff_conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->whereNull('left_at')
                ->first(['last_delivered_at', 'last_read_at']);
            if (!$pivot) {
                continue;
            }

            $acknowledged = max((string) $pivot->last_delivered_at, (string) $pivot->last_read_at) ?: '1970-01-01';
            $hasNew       = StaffMessage::where('staff_conversation_id', $conversation->id)
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>', $acknowledged)
                ->exists();
            if (!$hasNew) {
                continue;
            }

            $conversation->participants()->newPivotStatement()
                ->where('staff_conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->update(['last_delivered_at' => $deliveredAt->format(StaffConversation::PRECISE_DATE_FORMAT)]);

            StaffConversationDelivered::dispatch($conversation, $user, $deliveredAt->toIso8601ZuluString('microsecond'));
            $moved[] = $conversation->ulid;
        }

        return $moved;
    }

    public function rules(): array
    {
        return [
            'conversation_ulids'   => ['required', 'array', 'min:1', 'max:'.self::MAX_CONVERSATIONS],
            'conversation_ulids.*' => ['string', 'max:26'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        return ['delivered' => $this->handle($request->user(), $request->validated('conversation_ulids'))];
    }
}
