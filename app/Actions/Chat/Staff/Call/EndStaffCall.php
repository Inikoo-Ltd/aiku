<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Events\StaffCallUpdated;
use App\Events\StaffMessageSent;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeetingGuest;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class EndStaffCall
{
    use AsAction;

    /**
     * Ends a call once, however many paths ask at the same time (hang up, decline, webhook, timeout), and leaves one entry
     * in the conversation. Unanswered, it is missed once it rang for StaffCall::RING_SECONDS, cancelled before that.
     */
    public function handle(StaffCall $call, ?string $outcome = null, ?User $declinedBy = null): StaffCall
    {
        $now = now();
        $call->refresh();
        $outcome ??= match (true) {
            $call->answered_at !== null                                         => StaffCall::OUTCOME_ANSWERED,
            $call->started_at->diffInSeconds($now) >= StaffCall::RING_SECONDS => StaffCall::OUTCOME_MISSED,
            default                                                             => StaffCall::OUTCOME_CANCELLED,
        };

        $ended = StaffCall::whereKey($call->id)
            ->whereNull('ended_at')
            ->when($outcome === StaffCall::OUTCOME_DECLINED, fn ($query) => $query->whereNull('answered_at'))
            ->update([
            'ended_at'            => $now,
            'outcome'             => $outcome,
            'declined_by_user_id' => $declinedBy?->id,
            'updated_at'          => $now,
        ]);
        if (!$ended) {
            return $call->refresh();
        }

        $call->users()->newPivotStatement()->where('staff_call_id', $call->id)->whereNull('left_at')->update(['left_at' => $now, 'updated_at' => $now]);
        $call->guests()->where('status', StaffMeetingGuest::STATUS_ADMITTED)->update(['status' => StaffMeetingGuest::STATUS_LEFT, 'left_at' => $now, 'updated_at' => $now]);
        $call->refresh()->unsetRelation('users');

        if ($call->meeting) {
            $call->meeting->update(['last_used_at' => $now] + ($call->meeting->for_one_call ? ['ended_at' => $now] : ['locked_at' => null]));
            if ($call->meeting->for_one_call) {
                $call->meeting->guests()->where('status', StaffMeetingGuest::STATUS_WAITING)->update(['status' => StaffMeetingGuest::STATUS_LEFT, 'left_at' => $now, 'updated_at' => $now]);
            }
        }

        if ($call->conversation) {
            $this->writeEntry($call);
        }
        StaffCallUpdated::dispatch($call);
        RemoveFromLiveKitRoom::dispatch($call);

        return $call;
    }

    private function writeEntry(StaffCall $call): void
    {
        $conversation = $call->conversation;
        $summary      = $call->entrySummary();

        $message = $conversation->messages()->create([
            'user_id'       => $call->started_by_user_id,
            'body'          => StaffCall::entryMarker($summary['outcome'], $summary['duration_seconds'], $summary['kind']),
            'language_id'   => $call->startedBy->language_id,
            'staff_call_id' => $call->id,
            'mentions'      => [],
        ]);

        $conversation->update(['last_message_at' => $message->created_at]);
        $conversation->participants()->newPivotStatement()->where('staff_conversation_id', $conversation->id)->update(['archived_at' => null]);
        $conversation->participants()->updateExistingPivot($call->started_by_user_id, ['last_read_at' => $message->created_at->format(StaffConversation::PRECISE_DATE_FORMAT)]);

        StaffMessageSent::dispatch($message);
    }
}
