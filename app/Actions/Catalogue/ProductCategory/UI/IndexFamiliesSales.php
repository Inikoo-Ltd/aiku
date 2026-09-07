<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Catalogue\WithCatalogueIndexSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Catalogue\FamiliesResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexFamiliesSales extends OrgAction
{
    use WithCatalogueAuthorisation;
    use WithCatalogueIndexSubNavigation;

    public const string PREFIX = 'sales';

    private Shop $parent;

    public function handle(Shop $shop): LengthAwarePaginator
    {
        return IndexFamilies::make()->handle($shop, self::PREFIX);
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return FamiliesResource::collection($families);
    }

    public function htmlResponse(LengthAwarePaginator $families, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Catalogue/Families',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [],
                'title'       => __('Families sales'),
                'pageHead'    => [
                    'title'         => __('Families'),
                    'model'         => '',
                    'icon'          => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('Families')
                    ],
                    'afterTitle'    => [
                        'label' => __('Sales')
                    ],
                    'iconRight'     => [
                        'icon' => 'fal fa-money-bill-wave',
                    ],
                    'actions'       => [],
                    'subNavigation' => $this->getFamiliesIndexSubNavigation($this->parent),
                ],
                'data'        => FamiliesResource::collection($families),
                'tabs'        => [
                    'current'    => self::PREFIX,
                    'navigation' => [],
                ],
                self::PREFIX  => FamiliesResource::collection($families),
            ]
        )->table(
            IndexFamilies::make()->tableStructure(parent: $this->parent, prefix: self::PREFIX, sales: true)
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return IndexFamilies::make()->getBreadcrumbs(
            $this->parent,
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
