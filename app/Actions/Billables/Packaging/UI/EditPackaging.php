<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 17:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use App\Http\Resources\Helpers\ImageResource;
use App\Models\Billables\Packaging;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditPackaging extends OrgAction
{
    use WithCatalogueAuthorisation;

    public function handle(Packaging $packaging, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Billables/EditPackaging',
            [
                'breadcrumbs'  => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'        => __('Edit packaging'),
                'pageHead'     => [
                    'title'   => $packaging->code,
                    'model'   => __('Edit packaging'),
                    'icon'    => [
                        'icon'  => ['fal', 'fa-box-open'],
                        'title' => __('Packaging')
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.billables.packagings.index',
                                'parameters' => [
                                    $packaging->organisation->slug,
                                    $packaging->shop->slug,
                                ]
                            ],
                        ]
                    ]
                ],
                'packaging'    => [
                    'id'          => $packaging->id,
                    'slug'        => $packaging->slug,
                    'family_code' => $packaging->family_code,
                    'type'        => $packaging->type->value,
                    'code'        => $packaging->code,
                    'name'        => $packaging->name,
                    'price'       => (float) $packaging->price,
                    'width'       => $packaging->width,
                    'height'      => $packaging->height,
                    'depth'       => $packaging->depth,
                    'state'       => $packaging->state->value,
                    'image'       => $packaging->image ? ImageResource::make($packaging->image)->resolve() : null,
                ],
                'typeOptions'  => Options::forEnum(PackagingTypeEnum::class),
                'stateOptions' => Options::forEnum(PackagingStateEnum::class),
                'currencyCode' => $packaging->shop->currency->code,
                'updateRoute'  => [
                    'name'       => 'grp.models.billables.packagings.update',
                    'parameters' => [
                        'packaging' => $packaging->id
                    ]
                ],
            ]
        );
    }

    public function asController(Organisation $organisation, Shop $shop, Packaging $packaging, ActionRequest $request): Response
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($packaging, $request);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            ShowPackagings::make()->getBreadcrumbs(
                routeName: preg_replace('/edit$/', 'index', $routeName),
                routeParameters: Arr::only($routeParameters, ['organisation', 'shop']),
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Editing packaging'),
                    ]
                ]
            ]
        );
    }
}
