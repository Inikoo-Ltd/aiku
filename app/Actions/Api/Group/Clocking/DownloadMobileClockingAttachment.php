<?php

/*
 * Author Louis Perez
 * Created on 11-11-2026-11h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Clocking;

use App\Actions\Helpers\Media\UI\DownloadAttachment;
use App\Models\Helpers\Media;
use App\Models\HumanResources\AttendanceAdjustment;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadMobileClockingAttachment
{
    use AsController;

    /**
     * @return array<int, array{id: int, name: string, url: string}>
     */
    public function attachments(Leave|AttendanceAdjustment $model): array
    {
        return $model->getMedia('attachments')
            ->map(fn (Media $media) => [
                'id'   => $media->id,
                'name' => $media->file_name,
                'url'  => route('grp.api.mobile.clocking.attachments.download', ['media' => $media->ulid]),
            ])
            ->values()
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        /** @var Media $media */
        $media = $request->route('media');
        /** @var User $user */
        $user  = $request->user();
        $owner = $media->model;

        return ($owner instanceof Leave || $owner instanceof AttendanceAdjustment)
            && $user->employees()->whereKey($owner->employee_id)->exists();
    }

    public function asController(Media $media, ActionRequest $request): BinaryFileResponse
    {
        return DownloadAttachment::make()->handle($media, $request->boolean('inline'));
    }
}
