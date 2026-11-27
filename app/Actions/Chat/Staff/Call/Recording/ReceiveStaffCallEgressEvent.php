<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Models\Chat\StaffCallRecording;
use App\Models\Chat\StaffCallRecordingTrack;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveStaffCallEgressEvent
{
    use AsAction;

    /** Egress statuses after which the file was written (complete, or cut at a size or time limit). */
    private const array WRITTEN = ['EGRESS_COMPLETE', 'EGRESS_LIMIT_REACHED', 3, 6];

    /**
     * LiveKit's egress_ended for one track: the file is in the bucket (or the egress failed). The track keeps when the
     * file really starts, so the speakers line up in the transcript. Arrives after the call has ended, so it is handled
     * before the webhook looks at the call.
     *
     * @param array<string, mixed> $info the webhook's egressInfo, in camelCase or snake_case
     */
    public function handle(array $info): void
    {
        $egressId = (string) ($info['egressId'] ?? $info['egress_id'] ?? '');
        $track    = $egressId !== '' ? StaffCallRecordingTrack::where('egress_id', $egressId)->first() : null;
        if (!$track || in_array($track->status, [StaffCallRecordingTrack::STATUS_ENDED, StaffCallRecordingTrack::STATUS_FAILED], true)) {
            return;
        }

        $stopNeverConfirmed = $track->status === StaffCallRecordingTrack::STATUS_ACTIVE;
        $file      = ($info['fileResults'] ?? $info['file_results'] ?? [])[0] ?? $info['file'] ?? [];
        $startedAt = $this->time($file['startedAt'] ?? $file['started_at'] ?? $info['startedAt'] ?? $info['started_at'] ?? null);

        $track->update([
            'status'   => in_array($info['status'] ?? null, self::WRITTEN, true) ? StaffCallRecordingTrack::STATUS_ENDED : StaffCallRecordingTrack::STATUS_FAILED,
            'ended_at' => now(),
        ] + ($startedAt ? ['started_at' => $startedAt] : []));

        $recording = $track->recording;
        if ($recording->audio_deleted_at !== null) {
            Storage::disk(StaffCallRecording::DISK)->delete($track->path);

            return;
        }

        if ($stopNeverConfirmed && $recording->status === StaffCallRecording::STATUS_STOPPED && $recording->call->ended_at === null
            && $recording->tracks()->where('status', StaffCallRecordingTrack::STATUS_ACTIVE)->exists() === false) {
            StopStaffCallRecording::run($recording->call, null, true);
        }

        ProcessStaffCallRecording::make()->whenTracksAreIn($recording->refresh());
    }

    /** LiveKit times are Unix nanoseconds, sent as strings. */
    private function time(mixed $value): ?Carbon
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return Carbon::createFromTimestampMs(intdiv((int) $value, 1_000_000));
    }
}
