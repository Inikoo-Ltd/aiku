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
 * @property array|null $details
 * @property string $url
 * @property int|null $status_code
 * @property int $inlinks
 * @property int|null $response_ms
 * @property string|null $title
 * @property bool $is_in_sitemap
 * @property string|null $webpage_slug
 * @property string|null $webpage_code
 */
class SiteAuditIssuePageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'url'           => $this->url,
            'status_code'   => $this->status_code,
            'inlinks'       => (int) $this->inlinks,
            'response_ms'   => $this->response_ms,
            'title'         => $this->title,
            'is_in_sitemap' => (bool) $this->is_in_sitemap,
            'webpage_slug'  => $this->webpage_slug,
            'webpage_code'  => $this->webpage_code,
            'details'       => $this->details ?? [],
        ];
    }
}
