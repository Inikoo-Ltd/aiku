<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Apr 2025 13:06:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop\UI;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreateMasterShop extends GrpAction
{
    use WithMastersEditAuthorisation;

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation(group(), $request);

        return $this->handle($request);
    }

    public function handle(ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('New master shop'),
                'pageHead'    => [
                    'title'   => __('New master shop'),
                    'icon'    => [
                        'title' => __('Master shop'),
                        'icon'  => 'fal fa-store-alt'
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.masters.master_shops.index',
                                'parameters' => []
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Master shop'),
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'required' => true,
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true,
                                ],
                                'type' => [
                                    'type'        => 'select',
                                    'label'       => __('Type'),
                                    'placeholder' => __('Select one option'),
                                    'options'     => Options::forEnum(ShopTypeEnum::class),
                                    'required'    => true,
                                    'mode'        => 'single',
                                    'searchable'  => true
                                ],
                            ]
                        ]
                    ],
                    'route'     => [
                        'name'       => 'grp.models.master_shop.store',
                        'parameters' => []
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexMasterShops::make()->getBreadcrumbs(),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating master shop'),
                    ]
                ]
            ]
        );
    }
}
