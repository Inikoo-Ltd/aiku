<?php

/*
 * author Arya Permana - Kirin
 * created on 10-06-2025-16h-22m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Webpage\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Web\WebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetWebpagesWithCollection extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Website $website, $prefix = null): LengthAwarePaginator
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
        $queryBuilder->where('webpages.website_id', $website->id);
        $queryBuilder->join('webpage_has_collections', 'webpages.id', '=', 'webpage_has_collections.webpage_id');
        $queryBuilder->distinct();

        $queryBuilder->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');

        return $queryBuilder
            ->defaultSort('webpages.id')
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

    public function asController(Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($website->shop, $request);
        return $this->handle($website);
    }

    public function jsonResponse(LengthAwarePaginator $webpages): AnonymousResourceCollection
    {
        return WebpagesResource::collection($webpages);
    }

}
