<?php

namespace App\Actions\Helpers\Brand\UI;

use App\Actions\GrpAction;
use App\Models\Helpers\Brand;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditBrand extends GrpAction
{
    public function asController(Brand $brand, ActionRequest $request): Response
    {
        $this->initialisation(group(), $request);

        return $this->handle($brand, $request);
    }

    public function handle(Brand $brand, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $brand,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('Edit Brand').' '.$brand->name,
                'pageHead'    => [
                    'title'   => __('Edit Brand'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Exit edit'),
                            'route' => [
                                'name'       => 'grp.trade_units.brands.show',
                                'parameters' => [
                                    'brand' => $brand->slug
                                ]
                            ],
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'label'  => __('Brand'),
                            'icon'   => 'fa-light fa-copyright',
                            'title'  => __('Brand'),
                            'fields' => [
                                'reference' => [
                                    'type'  => 'input',
                                    'label' => __('Reference'),
                                    'value' => $brand->reference
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('Name'),
                                    'value' => $brand->name
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.brand.update',
                            'parameters' => [
                                'brand' => $brand->id
                            ]
                        ]
                    ],
                ]
            ]
        );
    }

    public function getBreadcrumbs(Brand $brand, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexBrands::make()->getBreadcrumbs(
                routeName: 'grp.trade_units.brands.index',
                routeParameters: [],
                suffix: '('.__('Editing').')'
            ),
            []
        );
    }
}
