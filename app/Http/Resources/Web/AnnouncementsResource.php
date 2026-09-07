<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jul 2023 14:08:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Announcement\AnnouncementPositionEnum;
use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Web\Announcement;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementsResource extends JsonResource
{
    public function toArray($request): array
    {
        $announcement = Announcement::find($this->id);

        $extractedSettings = $announcement->extractSettings($announcement->settings);

        return [
            'ulid'           => $announcement->ulid,
            'code'           => $announcement->code,
            'name'           => $announcement->name,
            'created_at'     => $announcement->created_at,
            'live_at'        => $announcement->live_at,
            'closed_at'      => $announcement->closed_at,
            'status'         => AnnouncementStatusenum::statusIcon()[$announcement->status->value],
            'show_pages'     => $extractedSettings['show_pages'],
            'hide_pages'     => $extractedSettings['hide_pages'],
            'publisher_name' => $announcement->liveSnapshot?->publisher?->contact_name,
            'position'       => AnnouncementPositionEnum::labels()[$announcement->getPosition()]
                                ?? $announcement->getPosition(),
            'paused_note'    => $this->getPausedNote($announcement),
            'is_expired'     => $announcement->status === AnnouncementStatusEnum::ACTIVE
                                && $announcement->schedule_finish_at?->isPast()
        ];
    }

    protected function getPausedNote(Announcement $announcement): ?string
    {
        if (!$announcement->paused_by_announcement_id) {
            return null;
        }

        if (!$announcement->paused_until) {
            return __('Paused by :name, turn it back on when you want it', ['name' => $announcement->pausedBy?->name]);
        }

        return __('Paused by :name, back on :date', [
            'name' => $announcement->pausedBy?->name,
            'date' => $announcement->paused_until->format('d M Y H:i')
        ]);
    }
}
