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
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Billables\Leaflet;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditLeaflet extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Leaflet $leaflet, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/EditLeaflet',
            [
                'breadcrumbs'      => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'            => __('Edit leaflet'),
                'pageHead'         => [
                    'title'   => $leaflet->name,
                    'model'   => __('Edit leaflet'),
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
                                    'organisation' => $leaflet->organisation->slug,
                                    'shop'         => $leaflet->shop->slug,
                                    'tab'          => 'leaflets',
                                ]
                            ],
                        ]
                    ]
                ],
                'leaflet'          => [
                    'id'           => $leaflet->id,
                    'name'         => $leaflet->name,
                    'type'         => $leaflet->type->value,
                    'price'        => (float) $leaflet->price,
                    'family_codes' => $leaflet->family_codes ?? [],
                    'state'        => $leaflet->state->value,
                ],
                'typeOptions'       => Options::forEnum(LeafletTypeEnum::class),
                'stateOptions'      => Options::forEnum(LeafletStateEnum::class),
                'familyCodeOptions' => $this->getFamilyCodeOptions($leaflet->shop),
                'currencyCode'     => $leaflet->shop->currency->code,
                'updateRoute'      => [
                    'name'       => 'grp.models.billables.leaflets.update',
                    'parameters' => [
                        'leaflet' => $leaflet->id
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

    public function asController(Organisation $organisation, Shop $shop, Leaflet $leaflet, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($leaflet, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowPackagings::make()->getBreadcrumbs(
                routeName: 'grp.org.shops.show.billables.packagings.index',
                routeParameters: Arr::only($routeParameters, ['organisation', 'shop']),
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing leaflet'),
                    ]
                ]
            ]
        );
    }
}
