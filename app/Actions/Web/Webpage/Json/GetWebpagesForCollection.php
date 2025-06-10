<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Http\Resources\Web\WebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetWebpagesForCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    private Shop $parent;

    public function handle(Collection $collection, $prefix = null): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(Webpage::class);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('webpages.code', $value)
                    ->orWhereStartWith('webpages.url', $value);
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder->whereIn('webpages.sub_type', [WebpageSubTypeEnum::SUB_DEPARTMENT, WebpageSubTypeEnum::DEPARTMENT, WebpageSubTypeEnum::STOREFRONT]);
        $queryBuilder->where('webpages.shop_id', $collection->shop_id)
            ->where('webpages.organisation_id', $collection->organisation_id);
        $queryBuilder->orderByRaw("CASE
            WHEN webpages.sub_type = 'storefront' THEN 1
            WHEN webpages.sub_type = 'department' THEN 2
            WHEN webpages.sub_type = 'sub_department' THEN 3
            ELSE 4
            END");

        $queryBuilder
            ->defaultSort('webpages.id')
            ->select([
                'webpages.id',
                'webpages.code',
                'webpages.slug',
                'webpages.created_at',
                'webpages.updated_at',
            ]);

        $queryBuilder->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');

        return $queryBuilder
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
            ])->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(Shop $shop, Collection $collection, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);
        return $this->handle($collection);
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return WebpagesResource::collection($collections);
    }

}
