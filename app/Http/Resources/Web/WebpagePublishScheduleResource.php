<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Wed, 11 Nov 2026
 * Copyright (c) 2026
 */

namespace App\Http\Resources\Web;

use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Webpage $resource
 */
class WebpagePublishScheduleResource extends JsonResource
{
    /**
     * @return array{scheduled_at: string|null, scheduled_by: string|null}
     */
    public function toArray($request): array
    {
        $webpage     = $this->resource;
        $scheduledBy = $webpage->publishScheduledBy;

        return [
            'scheduled_at' => $webpage->publish_scheduled_at?->toIso8601String(),
            'scheduled_by' => $webpage->publish_scheduled_at ? ($scheduledBy?->contact_name ?: $scheduledBy?->username) : null,
        ];
    }
}
