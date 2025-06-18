<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\WebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetWebpagesInCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;
    private mixed $bucket;

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->bucket = 'all';
        $this->scope  = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop->website);
    }

    public function handle(Website $parent, $prefix = null, $bucket = null): LengthAwarePaginator
    {
        if ($bucket) {
            $this->bucket = $bucket;
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('webpages.code', $value)
                    ->orWhereStartWith('webpages.url', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Webpage::class);

        $queryBuilder->where('webpages.website_id', $parent->id);

        if ($this->bucket == 'catalogue') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::CATALOGUE);
        } elseif ($this->bucket == 'content') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::CONTENT);
        } elseif ($this->bucket == 'info') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::INFO);
        } elseif ($this->bucket == 'operations') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::OPERATIONS);
        } elseif ($this->bucket == 'blog') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::BLOG);
        } elseif ($this->bucket == 'storefront') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::STOREFRONT);
        }

        if (isset(request()->query()['json']) && request()->query()['json'] === 'true' || (function_exists('request') && request() && request()->expectsJson())) {
            $queryBuilder->orderByRaw("CASE 
            WHEN webpages.sub_type = 'storefront' THEN 1
            WHEN webpages.sub_type = 'department' THEN 2
            WHEN webpages.sub_type = 'sub_department' THEN 3
            WHEN webpages.sub_type = 'family' THEN 4
            WHEN webpages.sub_type = 'catalogue' THEN 5
            WHEN webpages.sub_type = 'product' THEN 6
            ELSE 7
            END, webpages.sub_type ASC");
        }

        $queryBuilder->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');

        return $queryBuilder
            ->defaultSort('webpages.level')
            ->select([
                'webpages.code',
                'webpages.id',
                'webpages.type',
                'webpages.slug',
                'webpages.level',
                'webpages.sub_type',
                'webpages.url',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.name as organisation_name',
                'websites.domain as website_url',
                'websites.slug as website_slug'
            ])
            ->allowedSorts(['code', 'type', 'level', 'url'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $webpages): AnonymousResourceCollection
    {
        return WebpagesResource::collection($webpages);
    }
}
