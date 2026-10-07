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
 * @property int $website_visitor_id
 * @property int|null $webpage_id
 * @property string $page_path
 * @property string|null $page_type
 * @property int $duration_seconds
 * @property \Illuminate\Support\Carbon $created_at
 * @property string|null $webpage_code
 * @property string|null $webpage_slug
 * @property string|null $session_id
 * @property string|null $device_type
 * @property string|null $country_code
 * @property string|null $city
 */
class WebsitePageViewResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'viewed_at'          => $this->created_at,
            'page_path'          => $this->page_path,
            'webpage_code'       => $this->webpage_code,
            'webpage_slug'       => $this->webpage_slug,
            'page_type'          => $this->page_type,
            'duration_seconds'   => (int) $this->duration_seconds,
            'website_visitor_id' => $this->website_visitor_id,
            'visitor'            => $this->session_id ? substr($this->session_id, 0, 8) : null,
            'device_type'        => $this->device_type ? ucfirst($this->device_type) : null,
            'country_code'       => $this->country_code,
            'city'               => $this->city,
        ];
    }
}
