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
 * @property int $id
 * @property string $path
 * @property int $hits
 * @property int|null $backlinks
 * @property string|null $last_referrer
 * @property bool $is_ignored
 * @property bool $is_fixed
 * @property \Illuminate\Support\Carbon $first_seen_at
 * @property \Illuminate\Support\Carbon $last_seen_at
 */
class WebsiteNotFoundPathResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'path'          => $this->path,
            'hits'          => $this->hits,
            'backlinks'     => (int) $this->backlinks,
            'last_referrer' => $this->last_referrer,
            'is_ignored'    => (bool) $this->is_ignored,
            'is_fixed'      => (bool) $this->is_fixed,
            'first_seen_at' => $this->first_seen_at,
            'last_seen_at'  => $this->last_seen_at,
            'ignore_route'  => [
                'name'       => 'grp.models.website_not_found_path.ignored.update',
                'parameters' => [$this->id],
                'method'     => 'patch',
            ],
        ];
    }
}
