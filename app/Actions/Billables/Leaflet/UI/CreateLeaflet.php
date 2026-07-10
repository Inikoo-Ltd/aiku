<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 14:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Leaflet\UI;

use App\Actions\Billables\Packaging\UI\ShowPackagings;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateLeaflet extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Shop $shop, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/CreateLeaflet',
            [
                'breadcrumbs'      => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'            => __('New leaflet'),
                'pageHead'         => [
                    'title'   => __('New leaflet'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-file-alt'],
                        'title' => __('Leaflet')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.billables.packagings.index',
                                'parameters' => [
                                    'organisation' => $shop->organisation->slug,
                                    'shop'         => $shop->slug,
                                    'tab'          => 'leaflets',
                                ]
                            ],
                        ]
                    ]
                ],
                'typeOptions'       => Options::forEnum(LeafletTypeEnum::class),
                'familyCodeOptions' => $this->getFamilyCodeOptions($shop),
                'currencyCode'     => $shop->currency->code,
                'storeRoute'       => [
                    'name'       => 'grp.models.billables.leaflets.store',
                    'parameters' => [
                        'shop' => $shop->id
                    ]
                ],
            ]
        );
    }

    /** @return array<int, array{label: string, value: string, packagings: array<int, string>}> */
    private function getFamilyCodeOptions(Shop $shop): array
    {
        return Packaging::where('shop_id', $shop->id)
            ->orderBy('code')
            ->get()
            ->groupBy('family_code')
            ->map(fn ($packagings, $familyCode) => [
                'label'      => "{$familyCode} ({$packagings->count()})",
                'value'      => $familyCode,
                'packagings' => $packagings->map(fn (Packaging $packaging) => "{$packaging->code} — {$packaging->name}")->values()->all(),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowPackagings::make()->getBreadcrumbs(
                routeName: 'grp.org.shops.show.billables.packagings.index',
                routeParameters: $routeParameters,
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating leaflet'),
                    ]
                ]
            ]
        );
    }
}
