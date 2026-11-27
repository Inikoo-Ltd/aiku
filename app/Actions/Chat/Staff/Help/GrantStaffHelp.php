<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Help;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffHelpGrant;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GrantStaffHelp
{
    use AsAction;

    /**
     * Only the person being helped grants, to the other person of a direct conversation they are both still in. A new
     * grant ends the granter's previous one, so one colleague at a time.
     */
    public function handle(StaffConversation $conversation, User $granter, int $minutes): StaffHelpGrant
    {
        $helper = self::helperIn($conversation, $granter);
        abort_unless($helper, 403);

        $grant = DB::transaction(function () use ($conversation, $granter, $helper, $minutes) {
            DB::select('SELECT pg_advisory_xact_lock(?, ?)', [496, $granter->id]);
            StaffHelpGrant::where('granter_user_id', $granter->id)->whereNull('ended_at')->lockForUpdate()->get()
                ->each(fn (StaffHelpGrant $open) => $open->end(StaffHelpGrant::REPLACED));

            return StaffHelpGrant::create([
                'group_id'              => $granter->group_id,
                'granter_user_id'       => $granter->id,
                'helper_user_id'        => $helper->id,
                'staff_conversation_id' => $conversation->id,
                'minutes'               => $minutes,
                'granted_at'            => now(),
                'expires_at'            => now()->addMinutes($minutes),
            ]);
        });

        SendStaffMessage::make()->handle($conversation, $granter, [
            'body' => __(':helper, I have let you help me in aiku for :minutes minutes. Press Start in the amber bar at the top of aiku; I can end it at any time.', [
                'helper'  => $helper->chatName(),
                'minutes' => $minutes,
            ]),
        ]);

        return $grant;
    }

    public static function helperIn(StaffConversation $conversation, User $granter): ?User
    {
        if (!StaffCall::canBeHeldIn($conversation) || $conversation->isBotDirect() || !$granter->status
            || $granter->group_id !== $conversation->group_id || !$conversation->isActiveParticipant($granter)) {
            return null;
        }

        return $conversation->activeParticipants()
            ->where('users.id', '!=', $granter->id)
            ->where('users.status', true)
            ->where('users.group_id', $granter->group_id)
            ->first();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->permissionsLender() === null;
    }

    public function rules(): array
    {
        return ['minutes' => ['sometimes', 'integer', 'in:'.implode(',', StaffHelpGrant::MINUTES)]];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffConversation $staffConversation, ActionRequest $request): array
    {
        return $this->handle($staffConversation, $request->user(), (int) ($request->validated('minutes') ?? StaffHelpGrant::DEFAULT_MINUTES))->toBanner();
    }
}
