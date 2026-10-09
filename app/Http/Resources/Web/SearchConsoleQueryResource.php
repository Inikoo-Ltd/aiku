<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $query
 * @property int $clicks
 * @property int $impressions
 * @property string|null $ctr
 * @property string|null $position
 * @property int $pages
 */
class SearchConsoleQueryResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'query'       => $this->query,
            'clicks'      => (int) $this->clicks,
            'impressions' => (int) $this->impressions,
            'ctr'         => (float) $this->ctr,
            'position'    => $this->position !== null ? (float) $this->position : null,
            'pages'       => (int) $this->pages,
        ];
    }
}
