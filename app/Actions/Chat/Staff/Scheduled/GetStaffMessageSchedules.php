<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 15:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Scheduled;

use App\Http\Resources\Chat\StaffMessageScheduleResource;
use App\Models\Chat\StaffMessageSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The person's own Scheduled list: what is still waiting, soonest first, then what failed in the last week so a
 * refusal stays in sight after the bell.
 */
class GetStaffMessageSchedules
{
    use AsAction;

    public const int FAILED_SHOWN_DAYS = 7;

    public function asController(Request $request): AnonymousResourceCollection
    {
        $schedules = StaffMessageSchedule::where('user_id', $request->user()->id)
            ->where(fn ($query) => $query
                ->whereIn('state', [StaffMessageSchedule::PENDING, StaffMessageSchedule::SENDING])
                ->orWhere(fn ($query) => $query->where('state', StaffMessageSchedule::FAILED)->where('updated_at', '>', now()->subDays(self::FAILED_SHOWN_DAYS))))
            ->with(['user', 'slackChannel', 'staffConversation.participants'])
            ->orderByRaw("state = 'failed' desc")
            ->orderBy('send_at')
            ->limit(ScheduleStaffMessage::MAX_PENDING_PER_PERSON + 20)
            ->get();

        return StaffMessageScheduleResource::collection($schedules);
    }
}
