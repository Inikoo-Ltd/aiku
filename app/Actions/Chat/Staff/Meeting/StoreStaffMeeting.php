<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStaffMeeting
{
    use AsAction;

    /**
     * A meeting link made ahead of time, like a Zoom room: no call runs until the first person arrives.
     */
    public function handle(User $user, ?string $title = null): StaffMeeting
    {
        $open = StaffMeeting::where('created_by_user_id', $user->id)
            ->whereNull('ended_at')
            ->where('last_used_at', '>', now()->subDays(StaffMeeting::IDLE_DAYS))
            ->count();

        if ($open >= StaffMeeting::MAX_OPEN_PER_USER) {
            throw ValidationException::withMessages(['title' => __('You already have :count open meeting links. Open one and end it first.', ['count' => $open])]);
        }

        return StaffMeeting::create([
            'group_id'           => $user->group_id,
            'title'              => $title !== null && trim($title) !== '' ? trim($title) : null,
            'created_by_user_id' => $user->id,
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return (bool) $request->user()->status;
    }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'nullable', 'string', 'max:120']];
    }

    /**
     * @return array{url: string, code: string, title: string|null}
     */
    public function asController(ActionRequest $request): array
    {
        $meeting = $this->handle($request->user(), $request->validated('title'));

        return ['url' => $meeting->url(), 'code' => $meeting->code, 'title' => $meeting->title];
    }
}
