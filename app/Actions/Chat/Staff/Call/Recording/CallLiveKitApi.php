<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class CallLiveKitApi
{
    use AsAction;

    /**
     * One Twirp call to the calls server, signed with a token that lives a minute and carries only the grant asked for
     * (roomAdmin for a room, roomRecord for Egress). Null when the server is unreachable or refuses.
     *
     * @param array<string, mixed> $body
     * @param array<string, mixed> $grant
     * @return array<string, mixed>|null
     */
    public function handle(string $method, array $body, array $grant): ?array
    {
        $token = JWT::encode([
            'iss'   => config('services.livekit.api_key'),
            'nbf'   => time() - 5,
            'exp'   => time() + 60,
            'video' => $grant,
        ], config('services.livekit.api_secret'), 'HS256');

        try {
            $response = Http::timeout(5)->withToken($token)->post($this->apiUrl().'/twirp/'.$method, $body);
        } catch (Throwable) {
            return null;
        }

        return $response->successful() ? ($response->json() ?? []) : null;
    }

    private function apiUrl(): string
    {
        return rtrim(preg_replace('#^ws(s?)://#', 'http$1://', (string) config('services.livekit.url')), '/');
    }
}
