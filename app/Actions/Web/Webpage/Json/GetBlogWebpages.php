<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
*/

namespace App\Actions\Web\Webpage\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\Web\WebpagesResource;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetBlogWebpages extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Website $website): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('webpages.title', $value)
                    ->orWhereStartWith('webpages.code', $value);
            });
        });

        return QueryBuilder::for(Webpage::class)
            ->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'webpages.website_id', '=', 'websites.id')
            ->where('webpages.website_id', $website->id)
            ->where('webpages.type', WebpageTypeEnum::BLOG)
            ->where('webpages.state', WebpageStateEnum::LIVE)
            ->whereIn('webpages.sub_type', WebpageSubTypeEnum::blogCategoryValues())
            ->select([
                'webpages.id',
                'webpages.slug',
                'webpages.level',
                'webpages.code',
                'webpages.url',
                'webpages.title',
                'webpages.canonical_url',
                'webpages.type',
                'webpages.sub_type',
                'webpages.state',
                'webpages.created_at',
                'webpages.updated_at',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'websites.slug as website_slug',
            ])
            ->defaultSort('-webpages.live_at')
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Shop $shop, Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($website);
    }

    public function jsonResponse(LengthAwarePaginator $webpages): AnonymousResourceCollection
    {
        return WebpagesResource::collection($webpages);
    }
}
