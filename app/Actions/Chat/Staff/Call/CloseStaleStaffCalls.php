<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class CloseStaleStaffCalls
{
    use AsAction;

    public string $commandSignature = 'staff-calls:close-stale';

    public string $commandDescription = 'Take silent browsers out of staff calls, mark unanswered calls missed and end calls nobody is in';

    /**
     * Runs every minute, and for one conversation before a call is started or read, so a conversation is never stuck in a
     * call whose browsers were closed. A person is out once their browser has not pinged for StaffCall::STALE_SECONDS or they may no longer be in the
     * conversation; either way the media server drops them too, so a browser that stops talking to aiku cannot keep listening.
     */
    public function handle(?StaffConversation $conversation = null): int
    {
        $staleBefore = now()->subSeconds(StaffCall::STALE_SECONDS);

        $calls = StaffCall::whereNull('ended_at')
            ->when($conversation, fn ($query) => $query->where('staff_conversation_id', $conversation->id))
            ->get();

        $closed = 0;
        foreach ($calls as $call) {
            foreach ($call->presentUsers()->get() as $user) {
                if (Carbon::parse($user->pivot->last_seen_at)->lt($staleBefore) || !$call->mayBeJoinedBy($user)) {
                    LeaveStaffCall::run($call, $user);
                    RemoveFromLiveKitRoom::dispatch($call, $user->id);
                }
            }
            $call->refresh();
            if ($call->ended_at) {
                $closed++;
                continue;
            }

            $nobodyIn       = !$call->presentUsers()->exists();
            $ringingTooLong = $call->answered_at === null
                && $call->conversation->type === 'dm'
                && $call->started_at->lt(now()->subSeconds(StaffCall::RING_SECONDS));

            if ($nobodyIn || $ringingTooLong) {
                EndStaffCall::run($call);
                $closed++;
            }
        }

        return $closed;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $command->info('Closed '.$this->handle().' calls');

        return 0;
    }
}
