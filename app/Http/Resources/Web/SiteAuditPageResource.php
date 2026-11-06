<?php

/*
 * Created: Fri, 06 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $url
 * @property int|null $status_code
 * @property string|null $redirect_to
 * @property string|null $title
 * @property string|null $meta_description
 * @property string|null $h1
 * @property string|null $canonical
 * @property bool $is_indexable
 * @property bool $is_in_sitemap
 * @property int|null $link_depth
 * @property int|null $inlinks
 * @property int|null $response_ms
 * @property int|null $word_count
 * @property string|null $webpage_slug
 * @property string|null $webpage_code
 * @property int $issues
 */
class SiteAuditPageResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'url'              => $this->url,
            'status_code'      => $this->status_code,
            'redirect_to'      => $this->redirect_to,
            'title'            => $this->title,
            'meta_description' => $this->meta_description,
            'h1'               => $this->h1,
            'canonical'        => $this->canonical,
            'is_indexable'     => (bool) $this->is_indexable,
            'is_in_sitemap'    => (bool) $this->is_in_sitemap,
            'link_depth'       => $this->link_depth,
            'inlinks'          => (int) $this->inlinks,
            'response_ms'      => $this->response_ms,
            'word_count'       => $this->word_count,
            'webpage_slug'     => $this->webpage_slug,
            'webpage_code'     => $this->webpage_code,
            'issues'           => (int) $this->issues,
        ];
    }
}
