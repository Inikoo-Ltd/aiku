<?php

/*
 * author Louis Perez
 * created on 05-05-2026-08h-37m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Web\Announcement;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementIrisResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Announcement $announcement */
        $announcement = $this;

        $extractedSettings = $announcement->extractSettings($announcement->settings);

        return [
            'ulid'                 => $announcement->ulid,
            'code'                 => $announcement->code,
            'name'                 => $announcement->name,
            'status'               => $announcement->status->statusIcon()[$announcement->status->value],
            'state_icon'           => $announcement->state->stateIcon()[$announcement->state->value],
            'show_pages'           => $extractedSettings['show_pages'],
            'hide_pages'           => $extractedSettings['hide_pages'],
            'container_properties' => $announcement->container_properties,
            'created_at'           => $announcement->created_at,
            'fields'               => $announcement->fields,
            'id'                   => $announcement->id,
            'icon'                 => $announcement->icon,
            'schedule_at'          => $announcement->schedule_at,
            'schedule_finish_at'   => $announcement->schedule_finish_at,
            'settings'             => $announcement->settings,
            'state'                => $announcement->state,
            'template_code'        => $announcement->template_code,
            'ready_at'             => $announcement->ready_at
        ];
    }
}
