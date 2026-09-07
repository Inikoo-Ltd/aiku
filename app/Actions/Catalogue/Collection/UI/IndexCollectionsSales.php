<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\UI;

use App\Actions\Catalogue\WithCollectionsSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\CollectionsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexCollectionsSales extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCollectionsSubNavigation;

    public const string PREFIX = 'sales';

    private Shop $parent;

    public function handle(Shop $shop): LengthAwarePaginator
    {
        return IndexCollections::make()->handle($shop, self::PREFIX);
    }

    public function jsonResponse(LengthAwarePaginator $collections): AnonymousResourceCollection
    {
        return CollectionsResource::collection($collections);
    }

    public function htmlResponse(LengthAwarePaginator $collections, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Collections',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [],
                'title'       => __('Collections sales'),
                'pageHead'    => [
                    'title'         => __('Collections'),
                    'model'         => '',
                    'icon'          => [
                        'icon'  => ['fal', 'fa-album-collection'],
                        'title' => __('Collections')
                    ],
                    'afterTitle'    => [
                        'label' => __('Sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'actions'       => [],
                    'subNavigation' => $this->getCollectionsSubNavigation($this->parent),
                ],
                'data'        => CollectionsResource::collection($collections),
                'tabs'        => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX  => CollectionsResource::collection($collections),
            ]
        )->table(
            IndexCollections::make()->tableStructure($this->parent, prefix: self::PREFIX, sales: true)
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return IndexCollections::make()->getBreadcrumbs(
            $routeName,
            $routeParameters,
            '('.__('Sales').')'
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
