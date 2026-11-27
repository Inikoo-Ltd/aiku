<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Actions\Chat\Staff\Call\Recording\ReceiveStaffCallEgressEvent;
use App\Actions\Chat\Staff\Call\Recording\RecordStaffCallTrack;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeetingGuest;
use App\Models\SysAdmin\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class ReceiveLiveKitWebhook
{
    use AsAction;

    /**
     * LiveKit signs each webhook with a JWT in the Authorization header, made with our API secret, whose sha256 claim is
     * the base64 SHA-256 of the raw body. Anything else is refused before the body is read. A person who enters the room
     * without a seat taken through JoinStaffCall, or who may no longer be in the conversation, is removed at once: this
     * is what keeps a kick durable when a modified browser rejoins with a token it still holds. A participant leaving
     * ends nothing here, because LiveKit also reports it on a reconnect or a second tab; the heartbeat decides that.
     *
     * @param array{event?: string, room?: array{name?: string}, participant?: array{identity?: string}} $payload
     */
    public function handle(array $payload): void
    {
        $event = $payload['event'] ?? '';
        if ($event === 'egress_ended') {
            ReceiveStaffCallEgressEvent::run($payload['egressInfo'] ?? $payload['egress_info'] ?? []);

            return;
        }

        $call = StaffCall::where('room', $payload['room']['name'] ?? '')->first();
        if (!$call) {
            return;
        }
        if ($call->ended_at) {
            if ($event === 'participant_joined') {
                RemoveFromLiveKitRoom::dispatch($call);
            }

            return;
        }

        if ($event === 'room_finished') {
            EndStaffCall::run($call);

            return;
        }

        if ($event === 'track_published') {
            RecordStaffCallTrack::make()->forPublishedTrack($call, $payload);

            return;
        }

        $identity = (string) ($payload['participant']['identity'] ?? '');
        if ($event !== 'participant_joined' || $identity === '' || self::isRecorder($payload['participant'])) {
            return;
        }

        $guestUlid = StaffMeetingGuest::ulidFromIdentity($identity);
        if ($guestUlid !== null) {
            $guest = StaffMeetingGuest::where('ulid', $guestUlid)->first();
            if (!$guest || !$guest->isInCall($call)) {
                RemoveFromLiveKitRoom::dispatch($call, $identity);

                return;
            }
            $guest->update(['last_seen_at' => now(), 'joined_at' => $guest->joined_at ?? now()]);

            return;
        }

        $user      = ctype_digit($identity) ? User::find((int) $identity) : null;
        $wasSeated = $user && $call->presentUsers()->where('users.id', $user->id)->exists();

        if (!$wasSeated || !$call->mayBeJoinedBy($user)) {
            RemoveFromLiveKitRoom::dispatch($call, $identity);

            return;
        }

        $call->users()->newPivotStatement()
            ->where('staff_call_id', $call->id)
            ->where('user_id', $user->id)
            ->update(['last_seen_at' => now(), 'updated_at' => now()]);
    }

    /**
     * Egress joins the room to record it (INI-586). Its kind is set by LiveKit itself, never by a token aiku issued, so a
     * browser cannot pass as one.
     *
     * @param array{kind?: string|int} $participant
     */
    private static function isRecorder(array $participant): bool
    {
        return in_array($participant['kind'] ?? null, ['EGRESS', 2], true);
    }

    public static function isSigned(string $authorization, string $body): bool
    {
        try {
            $claims = JWT::decode(preg_replace('/^Bearer\s+/i', '', $authorization), new Key((string) config('services.livekit.api_secret'), 'HS256'));
        } catch (Throwable) {
            return false;
        }

        return ($claims->iss ?? null) === config('services.livekit.api_key')
            && hash_equals(base64_encode(hash('sha256', $body, true)), (string) ($claims->sha256 ?? ''));
    }

    public function asController(Request $request): Response
    {
        abort_unless(StaffCall::isEnabled(), 404);
        abort_unless(self::isSigned((string) $request->header('Authorization'), $request->getContent()), 401);

        $this->handle(json_decode($request->getContent(), true) ?: []);

        return response()->noContent();
    }
}
