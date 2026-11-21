<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StartStaffCall
{
    use AsAction;

    /**
     * Starting a call where one is already live joins that one: two people pressing Call at once meet in the same room.
     *
     * @return array{call: StaffCall, token: string, url: string}
     */
    public function handle(StaffConversation $conversation, User $user, string $kind = StaffCall::KIND_VOICE): array
    {
        CloseStaleStaffCalls::make()->handle($conversation);

        $live = StaffCall::where('staff_conversation_id', $conversation->id)->whereNull('ended_at')->first();
        if ($live) {
            return JoinStaffCall::run($live, $user);
        }

        try {
            $call = StaffCall::create([
                'group_id'              => $conversation->group_id,
                'staff_conversation_id' => $conversation->id,
                'started_by_user_id'    => $user->id,
                'kind'                  => in_array($kind, StaffCall::KINDS, true) ? $kind : StaffCall::KIND_VOICE,
            ]);
        } catch (UniqueConstraintViolationException) {
            return JoinStaffCall::run(StaffCall::where('staff_conversation_id', $conversation->id)->whereNull('ended_at')->firstOrFail(), $user);
        }

        return JoinStaffCall::run($call, $user);
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return StaffCall::userMayCallIn($request->route('staffConversation'), $request->user());
    }

    public function rules(): array
    {
        return ['kind' => ['sometimes', 'nullable', 'string', 'in:'.implode(',', StaffCall::KINDS)]];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffConversation $staffConversation, ActionRequest $request): array
    {
        $result = $this->handle($staffConversation, $request->user(), $request->validated('kind') ?? StaffCall::KIND_VOICE);

        return ['call' => $result['call']->toBroadcast(), 'token' => $result['token'], 'url' => $result['url']];
    }
}
