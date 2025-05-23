<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Feb 2024 14:48:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\Webpage\WithWebpageSubNavigation;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexChangesWebpages extends OrgAction
{
    use WithWebAuthorisation;
    use WithWebpageSubNavigation;


    private mixed $bucket;


    protected function getElementGroups(Webpage $parent): array
    {
        return [
            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    WebpageStateEnum::labels(),
                    WebpageStateEnum::count($parent)
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('webpages.state', $elements);
                }

            ],

        ];
    }


    public function handle(Webpage $webpage, $prefix = null, $bucket = null): LengthAwarePaginator
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

        $listChangesWebpages = [];
        $query = Webpage::where('website_id', $webpage->website_id)
            ->with("unpublishedSnapshot");

        $query->chunk(100, function ($webpages) use (&$listChangesWebpages) {
            foreach ($webpages as $webpage) {
                if (!$webpage->published_layout && !$webpage?->unpublishedSnapshot?->layout) {
                    continue;
                }

                if (!$webpage?->published_layout && $webpage?->unpublishedSnapshot?->layout) {
                    $listChangesWebpages[] = $webpage->id;
                    continue;
                }

                if ($webpage->unpublishedSnapshot->layout != $webpage?->published_layout) {
                    $listChangesWebpages[] = $webpage->id;
                }
            }
        });

        $queryBuilder = QueryBuilder::for(Webpage::class)
        ->whereIn('webpages.id', $listChangesWebpages);

        if ($bucket == 'catalogue') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::CATALOGUE);
        } elseif ($bucket == 'content') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::CONTENT);
        } elseif ($bucket == 'info') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::INFO);
        } elseif ($bucket == 'operations') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::OPERATIONS);
        } elseif ($bucket == 'blog') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::BLOG);
        } elseif ($bucket == 'storefront') {
            $queryBuilder->where('webpages.type', WebpageTypeEnum::STOREFRONT);
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

}
