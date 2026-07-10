<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging\UI;

use App\Actions\Billables\Leaflet\UI\IndexLeaflets;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\UI\Catalogue\PackagingsTabsEnum;
use App\Http\Resources\Catalogue\LeafletsResource;
use App\Http\Resources\Catalogue\PackagingsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPackagings extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Shop
    {
        $this->initialisationFromShop($shop, $request)->withTab(PackagingsTabsEnum::values());

        return $shop;
    }

    public function htmlResponse(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/Packagings',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Packagings'),
                'pageHead'    => [
                    'title'   => __('Packagings'),
                    'model'   => $shop->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-box-open'],
                        'title' => __('Packagings')
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'    => 'button',
                            'style'   => 'create',
                            'tooltip' => __('New packaging'),
                            'label'   => __('Packaging'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.billables.packagings.create',
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,
                    ]
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PackagingsTabsEnum::navigation(),
                ],

                PackagingsTabsEnum::PACKAGINGS->value => $this->tab == PackagingsTabsEnum::PACKAGINGS->value ?
                    fn () => PackagingsResource::collection(IndexPackagings::run($shop, PackagingsTabsEnum::PACKAGINGS->value))
                    : Inertia::optional(fn () => PackagingsResource::collection(IndexPackagings::run($shop, PackagingsTabsEnum::PACKAGINGS->value))),

                PackagingsTabsEnum::LEAFLETS->value => $this->tab == PackagingsTabsEnum::LEAFLETS->value ?
                    fn () => LeafletsResource::collection(IndexLeaflets::run($shop, PackagingsTabsEnum::LEAFLETS->value))
                    : Inertia::optional(fn () => LeafletsResource::collection(IndexLeaflets::run($shop, PackagingsTabsEnum::LEAFLETS->value))),
            ]
        )->table(IndexPackagings::make()->tableStructure(shop: $shop, prefix: PackagingsTabsEnum::PACKAGINGS->value, canEdit: $this->canEdit))
            ->table(IndexLeaflets::make()->tableStructure(shop: $shop, prefix: PackagingsTabsEnum::LEAFLETS->value, canEdit: $this->canEdit));
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, ?string $suffix = null): array
    {
        return array_merge(
            ShowShop::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Packagings'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ]
        );
    }
}
