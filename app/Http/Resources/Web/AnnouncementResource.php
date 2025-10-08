<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jul 2023 14:08:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use App\Models\Announcement;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {

        /** @var Announcement $announcement */
        $announcement = $this;

        $extractedSettings = $announcement->extractSettings($announcement->settings);

        return [
            'ulid'                           => $announcement->ulid,
            'code'                           => $announcement->code,
            'name'                           => $announcement->name,
            'created_at'                     => $announcement->created_at,
            'status'                         => $announcement->status->statusIcon()[$announcement->status->value],
            'show_pages'                     => $extractedSettings['show_pages'],
            'hide_pages'                     => $extractedSettings['hide_pages'],
        ];
    }
}
