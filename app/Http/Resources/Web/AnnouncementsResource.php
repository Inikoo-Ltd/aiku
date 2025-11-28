<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jul 2023 14:08:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Enums\Announcement\AnnouncementStatusEnum;
use App\Models\Announcement;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementsResource extends JsonResource
{
    public function toArray($request): array
    {
        $announcement = Announcement::find($this->id);

        $extractedSettings = $announcement->extractSettings($announcement->settings);

        return [
            'ulid'                           => $announcement->ulid,
            'code'                           => $announcement->code,
            'name'                           => $announcement->name,
            'created_at'                     => $announcement->created_at,
            'live_at'                        => $announcement->live_at,
            'status'                         => AnnouncementStatusenum::statusIcon()[$announcement->status->value],
            'show_pages'                     => $extractedSettings['show_pages'],
            'hide_pages'                     => $extractedSettings['hide_pages'],
            'publisher_name'                 => $announcement->liveSnapshot?->publisher?->contact_name,
            'position'                       => $announcement->settings['position'] ?? 'top-bar'
        ];
    }
}
