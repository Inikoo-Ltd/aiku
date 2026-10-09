<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Http\Resources\Web;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $title
 * @property mixed $url
 * @property mixed $canonical_url
 * @property mixed $type
 * @property mixed $state
 * @property mixed $organisation_slug
 * @property mixed $shop_slug
 * @property mixed $website_slug
 * @property mixed $visitors
 * @property mixed $page_views
 * @property mixed $avg_time_on_page
 * @property mixed $add_to_baskets
 * @property mixed $conversion_rate
 * @property mixed $checkouts
 * @property mixed $purchases
 * @property mixed $entrances
 * @property mixed $search_clicks
 * @property mixed $search_impressions
 * @property mixed $search_position
 * @property mixed $search_queries
 * @property mixed $backlinks
 * @property mixed $referring_domains
 * @property mixed $previous_visitors
 * @property mixed $previous_page_views
 * @property mixed $previous_search_clicks
 * @property mixed $previous_search_impressions
 * @property mixed $previous_search_position
 */
class WebpagePerformanceResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'               => $this->code,
            'title'              => $this->title,
            'url'                => $this->url,
            'canonical_url'      => $this->canonical_url,
            'type'               => $this->type,
            'typeIcon'           => $this->type->stateIcon()[$this->type->value] ?? ['fal', 'fa-browser'],
            'state'              => $this->state,
            'route'              => [
                'name'       => 'grp.org.shops.show.seo.visitors.webpage',
                'parameters' => [$this->organisation_slug, $this->shop_slug, $this->slug],
            ],
            'visitors'           => (int) $this->visitors,
            'page_views'         => (int) $this->page_views,
            'entrances'          => (int) $this->entrances,
            'avg_time_on_page'   => (int) $this->avg_time_on_page,
            'add_to_baskets'     => (int) $this->add_to_baskets,
            'conversion_rate'    => (float) $this->conversion_rate,
            'checkouts'          => (int) $this->checkouts,
            'purchases'          => (int) $this->purchases,
            'search_clicks'      => (int) $this->search_clicks,
            'search_impressions' => (int) $this->search_impressions,
            'search_position'    => $this->search_position !== null ? (float) $this->search_position : null,
            'search_queries'     => (int) $this->search_queries,
            'backlinks'          => (int) $this->backlinks,
            'referring_domains'  => (int) $this->referring_domains,
            'previous'           => [
                'visitors'           => $this->previous_visitors !== null ? (int) $this->previous_visitors : null,
                'page_views'         => $this->previous_page_views !== null ? (int) $this->previous_page_views : null,
                'search_clicks'      => $this->previous_search_clicks !== null ? (int) $this->previous_search_clicks : null,
                'search_impressions' => $this->previous_search_impressions !== null ? (int) $this->previous_search_impressions : null,
                'search_position'    => $this->previous_search_position !== null ? (float) $this->previous_search_position : null,
            ],
        ];
    }
}
