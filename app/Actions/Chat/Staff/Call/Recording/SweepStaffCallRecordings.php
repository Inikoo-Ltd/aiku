<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Models\Chat\StaffCallRecording;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Lorisleiva\Actions\Concerns\AsAction;

class SweepStaffCallRecordings
{
    use AsAction;

    public string $commandSignature = 'staff-calls:sweep-recordings {--retry= : ulid of a failed recording to process again}';

    public string $commandDescription = 'Stop recordings of ended calls, process those whose egress never reported, and delete audio past its keep date';

    /**
     * The safety net for lost webhooks and killed jobs: a call that ended without stopping its recording, a Stop whose
     * egresses did not all confirm (the mark stays on until they do), a stopped recording still waiting for a track
     * after the grace period (processed with the tracks it has), a job killed while processing (run again, keeping a
     * saved transcript), and kept audio whose time is up.
     */
    public function handle(): int
    {
        StaffCallRecording::where('status', StaffCallRecording::STATUS_RECORDING)
            ->whereHas('call', fn ($query) => $query->whereNotNull('ended_at'))
            ->with('call')
            ->each(fn (StaffCallRecording $recording) => StopStaffCallRecording::run($recording->call));

        StaffCallRecording::whereIn('status', [StaffCallRecording::STATUS_STOPPED, StaffCallRecording::STATUS_FAILED])
            ->where('stopped_at', '>=', now()->subHours(2))
            ->whereHas('call', fn ($query) => $query->whereNull('ended_at'))
            ->with('call')
            ->get()
            ->unique('staff_call_id')
            ->each(fn (StaffCallRecording $recording) => StopStaffCallRecording::run($recording->call, null, true));

        StaffCallRecording::where('status', StaffCallRecording::STATUS_STARTING)
            ->where('created_at', '<', now()->subMinutes(10))
            ->delete();

        StaffCallRecording::where('status', StaffCallRecording::STATUS_PROCESSING)
            ->where('updated_at', '<', now()->subMinutes(ProcessStaffCallRecording::STALE_PROCESSING_MINUTES))
            ->each(function (StaffCallRecording $recording) {
                if ($recording->attempts >= StaffCallRecording::MAX_ATTEMPTS) {
                    ProcessStaffCallRecording::markFailed($recording, 'Processing was stopped '.$recording->attempts.' times.');
                } else {
                    $recording->update(['status' => StaffCallRecording::STATUS_STOPPED]);
                }
            });

        foreach (glob(storage_path('app/tmp/call-recording-*'), GLOB_ONLYDIR) ?: [] as $leftover) {
            if (filemtime($leftover) < now()->subHours(3)->getTimestamp()) {
                File::deleteDirectory($leftover);
            }
        }

        StaffCallRecording::where('status', StaffCallRecording::STATUS_STOPPED)
            ->where('stopped_at', '<', now()->subMinutes(StaffCallRecording::TRACK_REPORT_GRACE_MINUTES))
            ->each(fn (StaffCallRecording $recording) => ProcessStaffCallRecording::make()->whenTracksAreIn($recording, true));

        $purged = 0;
        StaffCallRecording::whereNull('audio_deleted_at')
            ->where('delete_audio_at', '<=', now())
            ->whereIn('status', [StaffCallRecording::STATUS_DONE, StaffCallRecording::STATUS_FAILED])
            ->lazyById()
            ->each(function (StaffCallRecording $recording) use (&$purged) {
                ProcessStaffCallRecording::deleteAudio($recording);
                $purged++;
            });

        return $purged;
    }

    public function asCommand(Command $command): int
    {
        if ($ulid = $command->option('retry')) {
            $recording = StaffCallRecording::where('ulid', $ulid)->where('status', StaffCallRecording::STATUS_FAILED)->whereNull('audio_deleted_at')->first();
            if (!$recording) {
                $command->error('No failed recording with its audio still kept has that ulid');

                return 1;
            }
            $recording->update(['status' => StaffCallRecording::STATUS_STOPPED, 'error' => null, 'attempts' => 0]);
            ProcessStaffCallRecording::run($recording);
            $command->info('Now '.$recording->refresh()->status);

            return 0;
        }

        $command->info('Audio deleted for '.$this->handle().' recordings');

        return 0;
    }
}
