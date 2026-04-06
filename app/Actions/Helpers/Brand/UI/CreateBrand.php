<?php

namespace App\Actions\Helpers\Brand\UI;

use App\Actions\GrpAction;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateBrand extends GrpAction
{
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
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __('New Brand'),
                'pageHead'    => [
                    'title'   => __('New brand'),
                    'actions' => [
                        [
                            'type'   => 'button',
                            'style'  => 'cancel',
                            'label'  => __('Cancel'),
                            'route'  => [
                                'name'       => 'grp.trade_units.brands.index',
                                'parameters' => []
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
                                    'type'     => 'input',
                                    'label'    => __('Reference'),
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'required' => true
                                ],
                            ]
                        ]
                    ],
                    'route' => [
                        'name'       => 'grp.models.brand.store',
                        'parameters' => []
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexBrands::make()->getBreadcrumbs(
                routeName: 'grp.trade_units.brands.index',
                routeParameters: [],
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating brand'),
                    ]
                ]
            ]
        );
    }
}
