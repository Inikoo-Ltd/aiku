<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RemoveFromLiveKitRoom
{
    use AsAction;

    /**
     * Takes one person out of the media room (they lost the right to be in the call), or closes the room for everyone
     * when the call ended. The database already says they are out; this makes the media server agree, so a browser that
     * ignores aiku cannot keep listening. Queued, so a slow or unreachable call server never holds up a hang-up or a ping;
     * best effort, the next sweep tries again for anyone still in.
     */
    public int $jobTries = 5;

    /** @return array<int, int> */
    public function getJobBackoff(): array
    {
        return [5, 15, 60, 120];
    }

    /**
     * Queued: a removal the call server did not confirm is tried again, so someone taken out cannot keep listening
     * because the server was slow once.
     */
    public function asJob(StaffCall $call, int|string|null $identity = null): void
    {
        if (!$this->handle($call, $identity)) {
            throw new \RuntimeException('LiveKit did not confirm the removal from room '.$call->id);
        }
    }

    public function handle(StaffCall $call, int|string|null $identity = null): bool
    {
        $method = $identity !== null ? 'RemoveParticipant' : 'DeleteRoom';
        $body   = $identity !== null ? ['room' => $call->room, 'identity' => (string) $identity] : ['room' => $call->room];

        try {
            $response = Http::timeout(3)
                ->withToken($this->adminToken($call))
                ->post($this->apiUrl().'/twirp/livekit.RoomService/'.$method, $body);

            return $response->successful() || $response->status() === 404;
        } catch (Throwable) {
            return false;
        }
    }

    private function apiUrl(): string
    {
        return rtrim(preg_replace('#^ws(s?)://#', 'http$1://', (string) config('services.livekit.url')), '/');
    }

    private function adminToken(StaffCall $call): string
    {
        return JWT::encode([
            'iss'   => config('services.livekit.api_key'),
            'nbf'   => time() - 5,
            'exp'   => time() + 60,
            'video' => ['room' => $call->room, 'roomAdmin' => true, 'roomCreate' => true],
        ], config('services.livekit.api_secret'), 'HS256');
    }
}
