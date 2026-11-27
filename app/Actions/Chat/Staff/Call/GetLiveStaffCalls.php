<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Actions\Tasks\GetAikuAssistant;
use App\Models\Chat\StaffCall;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetLiveStaffCalls
{
    use AsAction;

    private const int RECENT_PEOPLE = 3;
    private const int QUICK_PEOPLE = 6;

    /**
     * Read once when a page of the app loads, so a call that is ringing or running survives a refresh. It never sweeps
     * stale calls itself (the minute schedule does), so a page load never waits on that.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user): array
    {
        $calls = StaffCall::whereNull('ended_at')
            ->where(fn ($query) => $query
                ->whereHas('conversation.activeParticipants', fn ($query) => $query->where('users.id', $user->id))
                ->orWhereHas('presentUsers', fn ($query) => $query->where('users.id', $user->id))
                ->orWhereHas('meeting', fn ($query) => $query->where('created_by_user_id', $user->id)))
            ->with(['conversation', 'meeting'])
            ->get();

        return $calls
            ->filter(fn (StaffCall $call) => $call->mayBeJoinedBy($user))
            ->map(fn (StaffCall $call) => $call->toBroadcast())
            ->values()
            ->all();
    }

    /**
     * Who the call button next to focus mode offers: the last people this person had a one-to-one call with, then the
     * colleagues they starred, active ones of the same group only.
     *
     * @return list<array{id: int, name: string, avatar: array<string, mixed>|null, starred: bool}>
     */
    public function quickPeople(User $user): array
    {
        $calledIds = StaffCall::query()
            ->whereNull('staff_meeting_id')
            ->whereHas('users', fn ($query) => $query->where('users.id', $user->id))
            ->whereHas('conversation', fn ($query) => $query->where('type', 'dm')->whereNull('context_type'))
            ->with('conversation.participants:id')
            ->latest('started_at')
            ->limit(30)
            ->get()
            ->map(fn (StaffCall $call) => $call->conversation->participants->firstWhere('id', '!=', $user->id)?->id)
            ->filter()
            ->unique()
            ->take(self::RECENT_PEOPLE)
            ->values();

        $starredIds = $user->teamMembers()->pluck('users.id');

        $people = User::query()
            ->whereIn('id', $calledIds->merge($starredIds)->unique())
            ->where('group_id', $user->group_id)
            ->where('status', true)
            ->where('username', '!=', GetAikuAssistant::USERNAME)
            ->get()
            ->keyBy('id');

        return $calledIds->merge($starredIds->sortBy(fn (int $id) => $people->get($id)?->chatName()))
            ->unique()
            ->map(fn (int $id) => $people->get($id))
            ->filter()
            ->take(self::QUICK_PEOPLE)
            ->map(fn (User $person) => [
                'id'      => $person->id,
                'name'    => $person->chatName(),
                'avatar'  => $person->image_id ? User::chatAvatarOf($person->imageSources(0, 64)) : null,
                'starred' => $starredIds->contains($person->id),
            ])
            ->values()
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return true;
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, quick_people: list<array<string, mixed>>, ping_seconds: int, ring_seconds: int}
     */
    public function asController(ActionRequest $request): array
    {
        return [
            'data'         => $this->handle($request->user()),
            'quick_people' => $this->quickPeople($request->user()),
            'ping_seconds' => StaffCall::PING_SECONDS,
            'ring_seconds' => StaffCall::RING_SECONDS,
        ];
    }
}
