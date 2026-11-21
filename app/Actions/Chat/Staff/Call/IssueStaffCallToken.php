<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
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
        $now = time();

        return JWT::encode([
            'iss'   => config('services.livekit.api_key'),
            'sub'   => (string) $user->id,
            'name'  => $user->chatName(),
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
