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

            'container_properties'           => $announcement->container_properties,
            'created_at'                     => $announcement->created_at,
            'fields'                         => $announcement->fields,
            'id'                             => $announcement->id,
            'icon'                           => $announcement->icon,
            'name'                           => $announcement->name,
            'schedule_at'                    => $announcement->schedule_at,
            'schedule_finish_at'             => $announcement->schedule_finish_at,
            'settings'                       => $announcement->settings,
            'state'                          => $announcement->state,
            'status'                         => $announcement->status,
            'ulid'                           => $announcement->ulid,
            'template_code'                  => 'announcement-promo-2-countdown',
            'updated_at'                     => $announcement->updated_at,
        ];
    }
}
