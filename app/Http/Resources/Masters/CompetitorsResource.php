<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use App\Actions\Masters\Competitor\ImportCompetitorFeed;
use App\Enums\Masters\Competitor\CompetitorSellsToEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The feed link, login and cookies never reach the browser: only whether they exist and what they mean.
 *
 * @property \App\Models\Masters\Competitor $resource
 */
class CompetitorsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'website'           => $this->website,
            'master_shop_slug'  => $this->master_shop_slug,
            'master_shop_name'  => $this->master_shop_name,
            'sells_to'          => CompetitorSellsToEnum::labels()[$this->sells_to->value],
            'currency_code'     => $this->currency_code,
            'has_login'         => (bool) $this->has_login,
            'has_search'        => (bool) $this->has_search,
            'read_method'       => static::readMethod($this->feed_url, (bool) $this->has_search),
            'status'            => $this->status?->value,
            'last_error'        => $this->last_error,
            'fetched_at'        => $this->fetched_at,
            'number_products'   => $this->number_products,
            'number_confirmed'  => (int) $this->number_confirmed,
            'median_difference' => $this->median_difference === null ? null : (int) $this->median_difference,
        ];
    }

    public static function readMethod(?string $feedUrl, bool $hasSearch): string
    {
        return match (true) {
            $feedUrl && ImportCompetitorFeed::isShopifyFeed($feedUrl)     => 'shopify',
            $feedUrl && ImportCompetitorFeed::isWooCommerceFeed($feedUrl) => 'woocommerce',
            (bool) $feedUrl                                               => 'feed',
            $hasSearch                                                    => 'search',
            default                                                       => 'none',
        };
    }
}
