<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeetingGuest;
use App\Models\SysAdmin\User;
use Firebase\JWT\JWT;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class IssueStaffCallToken
{
    use AsAction;

    /**
     * A LiveKit access token for one person in one room: join, talk, show camera and screen, and hear the others.
     * Nothing else (no room admin, create, list or recording grants). It only has to be valid when the browser connects;
     * LiveKit refreshes it for a connected participant.
     */
    public function handle(StaffCall $call, User $user): string
    {
        return $this->sign($call, (string) $user->id, $user->chatName());
    }

    /** The same room grants for a guest someone let in, under an identity that can never be a user id. */
    public function forGuest(StaffCall $call, StaffMeetingGuest $guest): string
    {
        return $this->sign($call, $guest->identity(), $guest->name);
    }

    private function sign(StaffCall $call, string $identity, string $name): string
    {
        $now = time();

        return JWT::encode([
            'iss'   => config('services.livekit.api_key'),
            'sub'   => $identity,
            'name'  => $name,
            'jti'   => (string) Str::uuid(),
            'nbf'   => $now - 5,
            'exp'   => $now + StaffCall::TOKEN_TTL_SECONDS,
            'video' => [
                'room'              => $call->room,
                'roomJoin'          => true,
                'canPublish'        => true,
                'canSubscribe'      => true,
                'canPublishData'    => false,
                'canPublishSources' => ['microphone', 'camera', 'screen_share', 'screen_share_audio'],
            ],
        ], config('services.livekit.api_secret'), 'HS256');
    }
}
