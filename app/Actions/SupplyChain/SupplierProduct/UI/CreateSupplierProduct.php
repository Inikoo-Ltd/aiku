<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-15h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\GrpAction;
use App\Models\SupplyChain\Supplier;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateSupplierProduct extends GrpAction
{
    public function handle(Supplier $supplier, ActionRequest $request): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplier,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title' => __('New supplier'),
                'pageHead' => [
                    'title' => __('New supplier'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name' => preg_replace('/create/', 'index', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint' => [
                        [
                            'title' => __('Create Supplier Product'),
                            'fields' => [
                                'code' => [
                                    'type' => 'input',
                                    'label' => __('code'),
                                    'required' => true,
                                ],
                                'name' => [
                                    'type' => 'input',
                                    'label' => __('name'),
                                    'required' => true,
                                ],
                                'cost' => [
                                    'type' => 'input',
                                    'label' => __('cost'),
                                    'required' => true,
                                ],
                                'units_per_pack' => [
                                    'type' => 'input',
                                    'label' => __('units per pack'),
                                    'required' => true,
                                ],
                                'units_per_carton' => [
                                    'type' => 'input',
                                    'label' => __('units per carton'),
                                    'required' => true,
                                ],
                                'cbm' => [
                                    'type' => 'input',
                                    'label' => __('cbm'),
                                    'required' => true,
                                ],
                            ],
                        ],
                    ],
                    'route' => [
                        'name' => 'grp.models.supplier.supplier-product.store',
                        'parameters' => [
                            'supplier' => $supplier->id,
                        ],
                    ],
                ],
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('supply-chain.edit');
    }

    public function asController(Supplier $supplier, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisation($group, $request);

        return $this->handle($supplier, $request);
    }

    public function getBreadcrumbs(Supplier $supplier, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexSupplierProducts::make()->getBreadcrumbs(
                routeName: preg_replace('/create$/', 'index', $routeName),
                routeParameters: $routeParameters,
                scope: $supplier
            ),
            [
                [
                    'type' => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Invoice Category'),
                    ],
                ],
            ]
        );
    }
}
