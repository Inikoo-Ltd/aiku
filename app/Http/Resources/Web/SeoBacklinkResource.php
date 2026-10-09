<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Actions\Web\Seo\UI\IndexSeoBacklinks;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $source_url
 * @property string $source_domain
 * @property string|null $source_title
 * @property int|null $domain_rank
 * @property bool $is_own_website
 * @property string $target_url
 * @property string $target_path
 * @property string|null $target_webpage_code
 * @property string|null $anchor
 * @property string|null $link_type
 * @property bool $is_dofollow
 * @property bool $is_broken
 * @property int|null $target_status_code
 * @property \Illuminate\Support\Carbon|null $first_seen
 * @property \Illuminate\Support\Carbon|null $last_seen
 * @property \Illuminate\Support\Carbon|null $lost_at
 */
class SeoBacklinkResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                  => $this->id,
            'source_url'          => $this->source_url,
            'source_domain'       => $this->source_domain,
            'source_title'        => $this->source_title,
            'domain_rank'         => $this->domain_rank,
            'is_own_website'      => $this->is_own_website,
            'target_url'          => $this->target_url,
            'target_path'         => $this->target_path,
            'target_webpage_code' => $this->target_webpage_code,
            'anchor'              => $this->anchor,
            'link_type'           => $this->link_type,
            'is_dofollow'         => $this->is_dofollow,
            'is_broken'           => $this->is_broken,
            'target_status_code'  => $this->target_status_code,
            'first_seen'          => $this->first_seen?->toDateString(),
            'last_seen'           => $this->last_seen?->toDateString(),
            'lost_at'             => $this->lost_at?->toDateString(),
            'is_new'              => $this->lost_at === null && $this->first_seen?->gte(now()->subDays(IndexSeoBacklinks::RECENT_DAYS)),
        ];
    }
}
