<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffCallRecording;
use App\Models\Chat\StaffCallRecordingTrack;
use App\Models\Chat\StaffMeetingGuest;
use App\Models\SysAdmin\User;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class RecordStaffCallTrack
{
    use AsAction;

    /** LiveKit leaves the type out when it is AUDIO (enum zero), and sends names or numbers. */
    private const array NOT_AUDIO = ['VIDEO', 'DATA', 1, 2];

    private const array SCREEN_AUDIO = ['SCREEN_SHARE_AUDIO', 4];

    /**
     * Starts one track egress for one audio track, written as Ogg to the private bucket configured on the calls box
     * (the request carries a path only, never storage keys). The same track is recorded once per recording. Null when
     * it is not audio; a track row with status failed when the calls box refused.
     *
     * @param array{sid?: string, type?: string|int, source?: string|int} $track
     */
    public function handle(StaffCallRecording $recording, StaffCall $call, string $identity, array $track): ?StaffCallRecordingTrack
    {
        $trackSid = (string) ($track['sid'] ?? '');
        if ($recording->status !== StaffCallRecording::STATUS_RECORDING || $trackSid === '' || $identity === '' || in_array($track['type'] ?? null, self::NOT_AUDIO, true)) {
            return null;
        }

        $speaker = $this->speaker($call, $identity);
        $row     = StaffCallRecordingTrack::firstOrCreate(
            ['staff_call_recording_id' => $recording->id, 'track_sid' => $trackSid],
            [
                'identity'   => Str::limit($identity, 60, ''),
                'speaker'    => Str::limit(in_array($track['source'] ?? null, self::SCREEN_AUDIO, true) ? __(':name (screen)', ['name' => $speaker]) : $speaker, 115),
                'path'       => $recording->audioDirectory().'/'.Str::random(32).'.ogg',
                'status'     => StaffCallRecordingTrack::STATUS_STARTING,
                'started_at' => now(),
            ]
        );
        if (!$row->wasRecentlyCreated) {
            return $row;
        }
        if (StaffCallRecording::whereKey($recording->id)->value('status') !== StaffCallRecording::STATUS_RECORDING) {
            $row->update(['status' => StaffCallRecordingTrack::STATUS_FAILED, 'ended_at' => now()]);

            return $row;
        }

        $egress = CallLiveKitApi::run('livekit.Egress/StartTrackEgress', [
            'room_name' => $call->room,
            'track_id'  => $trackSid,
            'file'      => ['filepath' => $row->path],
        ], ['room' => $call->room, 'roomRecord' => true]);

        $egressId = $egress['egress_id'] ?? $egress['egressId'] ?? null;
        $row->update($egressId
            ? ['egress_id' => $egressId, 'status' => StaffCallRecordingTrack::STATUS_ACTIVE]
            : ['status' => StaffCallRecordingTrack::STATUS_FAILED, 'ended_at' => now()]);

        return $row;
    }

    /**
     * Everyone already in the room when Record is pressed. False when the room could not be read, or when there was
     * audio and not one track could be recorded (Egress down): then nothing must look recorded.
     */
    public function everyoneIn(StaffCallRecording $recording): bool
    {
        $call  = $recording->call;
        $reply = CallLiveKitApi::run('livekit.RoomService/ListParticipants', ['room' => $call->room], ['room' => $call->room, 'roomAdmin' => true]);
        if ($reply === null) {
            return false;
        }

        $tracks = collect($reply['participants'] ?? [])
            ->flatMap(fn (array $participant) => collect($participant['tracks'] ?? [])->map(fn (array $track) => $this->handle($recording, $call, (string) ($participant['identity'] ?? ''), $track)))
            ->filter();

        return $tracks->isEmpty() || $tracks->contains(fn (StaffCallRecordingTrack $track) => $track->status === StaffCallRecordingTrack::STATUS_ACTIVE);
    }

    /**
     * Someone joins, or publishes a new microphone or their screen's sound while recording: LiveKit's track_published.
     *
     * @param array{participant?: array{identity?: string}, track?: array{sid?: string, type?: string|int, source?: string|int}} $payload
     */
    public function forPublishedTrack(StaffCall $call, array $payload): void
    {
        StaffCallRecording::lockFor($call, function () use ($call, $payload) {
            $recording = StaffCallRecording::where('staff_call_id', $call->id)->where('status', StaffCallRecording::STATUS_RECORDING)->first();
            if ($recording) {
                $this->handle($recording, $call, (string) ($payload['participant']['identity'] ?? ''), $payload['track'] ?? []);
            }
        });
    }

    /**
     * Named by who the identity is, never by the name a browser shows: a guest typing a colleague's name stays a guest.
     */
    private function speaker(StaffCall $call, string $identity): string
    {
        $guestUlid = StaffMeetingGuest::ulidFromIdentity($identity);
        if ($guestUlid !== null) {
            $guest = StaffMeetingGuest::where('ulid', $guestUlid)->where('staff_call_id', $call->id)->first();

            return __(':name (guest)', ['name' => $guest?->name ?? __('Guest')]);
        }

        $user = ctype_digit($identity) ? User::where('group_id', $call->group_id)->find((int) $identity) : null;

        return $user?->chatName() ?? __('Unknown');
    }
}
