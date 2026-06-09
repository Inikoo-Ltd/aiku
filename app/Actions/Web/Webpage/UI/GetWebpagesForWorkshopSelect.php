<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Feb 2024 14:48:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\Web\Webpage\WithWebpageSubNavigation;
use App\Actions\Web\Website\UI\ShowWebsite;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Http\Resources\Web\WebpagesForWorkshopSelectResource;
use App\Http\Resources\Web\WebpagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetWebpagesForWorkshopSelect extends OrgAction
{
    use WithWebAuthorisation;


    public function asController(Website $website, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($website->shop, $request);


        return $this->handle($website);
    }


    public function handle(Website $website): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('webpages.code', $value)
                    ->orWhereWith('webpages.url', $value);
            });
        });


        $queryBuilder = QueryBuilder::for(Webpage::class);

        $queryBuilder->where('webpages.website_id', $website->id);


        $queryBuilder->orderByRaw(
            "CASE
            WHEN webpages.sub_type = 'storefront' THEN 1
            WHEN webpages.sub_type = 'department' THEN 2
            WHEN webpages.sub_type = 'sub_department' THEN 3
            WHEN webpages.sub_type = 'family' THEN 4
            WHEN webpages.sub_type = 'catalogue' THEN 5
            WHEN webpages.sub_type = 'product' THEN 6
            ELSE 7
            END, webpages.sub_type ASC"
        );



        return $queryBuilder
            ->defaultSort('webpages.level')
            ->select([
                'webpages.code',
                'webpages.id',
                'webpages.type',
                'webpages.slug',
                'webpages.level',
                'webpages.state',
                'webpages.title',
                'webpages.sub_type',
                'webpages.url',
                'webpages.canonical_url',
                'websites.domain as website_url',
                'websites.slug as website_slug'
            ])
            ->allowedSorts(['code', 'type', 'level', 'url', 'state', 'title'])
            ->allowedFilters([$globalSearch])
            ->withPaginator(null, tableName: request()->route()->getName())
            ->withQueryString();
    }


    public function jsonResponse(LengthAwarePaginator $webpages): AnonymousResourceCollection
    {
        return WebpagesForWorkshopSelectResource::collection($webpages);
    }


}
