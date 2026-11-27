<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Actions\Chat\Staff\UI\ShowStaffMessaging;
use App\Models\Chat\StaffCallRecording;
use App\Models\Chat\StaffCallRecordingTrack;
use App\Models\SysAdmin\User;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowStaffCallRecording
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(StaffCallRecording $recording): array
    {
        $recording->loadMissing(['call', 'startedBy']);
        $call = $recording->call;

        return [
            'ulid'             => $recording->ulid,
            'status'           => $recording->status,
            'title'            => $call->conversation?->name ?? $call->meeting?->title,
            'started_by'       => $recording->startedBy->chatName(),
            'started_at'       => $recording->started_at->toIso8601ZuluString(),
            'duration_seconds' => $recording->stopped_at ? (int) $recording->started_at->diffInSeconds($recording->stopped_at) : null,
            'people'           => $recording->peopleRecorded()->get()->map(fn (User $user) => $user->chatName())->values()->all(),
            'summary'          => $recording->summary,
            'transcript'       => $recording->transcript ?? [],
            'audio'            => $recording->audio_deleted_at ? 'deleted' : ($recording->delete_audio_at ? 'kept' : 'pending'),
            'delete_audio_at'  => $recording->delete_audio_at?->toIso8601ZuluString(),
            'missing_speakers' => $recording->tracks()->where('status', StaffCallRecordingTrack::STATUS_FAILED)->pluck('speaker')->unique()->values()->all(),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffCallRecording')->isVisibleTo($request->user());
    }

    public function asController(StaffCallRecording $staffCallRecording): StaffCallRecording
    {
        return $staffCallRecording;
    }

    public function htmlResponse(StaffCallRecording $recording): Response
    {
        $title = __('Call summary');

        return Inertia::render('Chat/StaffCallRecording', [
            'breadcrumbs' => ShowStaffMessaging::make()->getBreadcrumbs(null),
            'title'       => $title,
            'pageHead'    => [
                'title' => $title,
                'icon'  => ['icon' => ['fal', 'fa-microphone'], 'title' => $title],
            ],
            'recording'   => $this->handle($recording),
        ]);
    }
}
