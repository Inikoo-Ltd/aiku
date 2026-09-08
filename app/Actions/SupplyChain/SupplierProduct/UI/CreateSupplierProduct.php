<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-15h-46m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateSupplierProduct extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(Supplier $supplier, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = array_values($request->route()->originalParameters());

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplier,
                    $routeName,
                    $request->route()->originalParameters()
                ),
                'title'       => __("New Supplier's Product"),
                'pageHead'    => [
                    'title'   => __("New Supplier's Product"),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => str_replace('create', 'index', $routeName),
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->getBlueprint($supplier),
                    'route'     => $this->getStoreRoute($supplier),
                ],
            ]
        );
    }

    public function asController(Supplier $supplier, ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($supplier, $request);
    }

    protected function getBlueprint(Supplier $supplier): array
    {
        return [
            [
                'title'  => __('ID/Description'),
                'icon'   => 'fal fa-box',
                'fields' => [
                    'code' => [
                        'type'        => 'input',
                        'label'       => __('Code'),
                        'information' => __("The supplier's own code, used when ordering"),
                        'value'       => '',
                        'required'    => true,
                    ],
                    'name' => [
                        'type'     => 'input',
                        'label'    => __('Name'),
                        'value'    => '',
                        'required' => true,
                    ],
                ],
            ],
            [
                'title'  => __('Cost'),
                'icon'   => 'fal fa-money-bill',
                'fields' => [
                    'cost'        => [
                        'type'     => 'input',
                        'label'    => __('Unit Cost').' ('.$supplier->currency->code.')',
                        'value'    => '',
                        'required' => true,
                    ],
                    'extra_costs' => [
                        'type'        => 'input',
                        'label'       => __('Extra Costs (%)'),
                        'information' => __('Extra percentage added on top of the unit cost'),
                        'value'       => 0,
                    ],
                ],
            ],
            [
                'title'  => __('Packing'),
                'icon'   => 'fal fa-boxes-stacked',
                'fields' => [
                    'units_per_pack'   => [
                        'type'     => 'input',
                        'label'    => __('Units Per Pack'),
                        'value'    => 1,
                        'required' => true,
                    ],
                    'units_per_carton' => [
                        'type'        => 'input',
                        'label'       => __('Units Per Carton'),
                        'information' => __('Packs per carton multiplied by units per pack'),
                        'value'       => '',
                        'required'    => true,
                    ],
                    'cbm'              => [
                        'type'  => 'input',
                        'label' => __('Carton CBM (m³)'),
                        'value' => '',
                    ],
                ],
            ],
            [
                'title'  => __('Ordering'),
                'icon'   => 'fal fa-cart-plus',
                'fields' => [
                    'minimum_carton_order' => [
                        'type'  => 'input',
                        'label' => __('Minimum Order (Cartons)'),
                        'value' => 1,
                    ],
                    'delivery_time'        => [
                        'type'        => 'input',
                        'label'       => __('Delivery Time (Days)'),
                        'information' => __("Defaults to the supplier's delivery time"),
                        'value'       => Arr::get($supplier->data, 'delivery_time', ''),
                    ],
                ],
            ],
            [
                'title'  => __('Trade Units'),
                'icon'   => 'fal fa-atom',
                'fields' => [
                    'trade_units' => [
                        'type'                => 'select_infinite',
                        'label'               => __('Trade Units'),
                        'information'         => __('The quantity of each trade unit follows units per pack'),
                        'placeholder'         => __('Select trade units'),
                        'mode'                => 'tags',
                        'searchable'          => true,
                        'valueProp'           => 'id',
                        'labelProp'           => 'code',
                        'labelAdditionalProp' => 'name',
                        'fetchRoute'          => [
                            'name' => 'grp.json.master_product_category.all_trade_units',
                        ],
                        'options'             => [],
                        'value'               => [],
                    ],
                ],
            ],
        ];
    }

    protected function getStoreRoute(Supplier $supplier): array
    {
        return [
            'name'       => 'grp.models.supplier.supplier-product.store',
            'parameters' => ['supplier' => $supplier->id],
        ];
    }

    public function getBreadcrumbs(Supplier $supplier, string $routeName, array $routeParameters): array
    {
        return array_merge(
            IndexSupplierProducts::make()->getBreadcrumbs(
                routeName: str_replace('create', 'index', $routeName),
                routeParameters: $routeParameters,
                scope: $supplier
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("Creating Supplier's Product"),
                    ],
                ],
            ]
        );
    }
}
