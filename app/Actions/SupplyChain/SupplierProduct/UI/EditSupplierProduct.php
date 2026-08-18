<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditSupplierProduct extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    public function handle(SupplierProduct $supplierProduct): SupplierProduct
    {
        return $supplierProduct;
    }

    public function asController(SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplier(Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inSupplierInAgent(Agent $agent, Supplier $supplier, SupplierProduct $supplierProduct, ActionRequest $request): SupplierProduct
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplierProduct);
    }

    public function htmlResponse(SupplierProduct $supplierProduct, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit supplier product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplierProduct,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'   => $supplierProduct->code,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData'    => [
                    'blueprint' => [
                        [
                            'title'  => __('Supplier product'),
                            'icon'   => 'fal fa-box-usd',
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('Code'),
                                    'value'    => $supplierProduct->code,
                                    'required' => true,
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('Name'),
                                    'value'    => $supplierProduct->name,
                                    'required' => true,
                                ],
                                'cost' => [
                                    'type'     => 'input',
                                    'label'    => trim(__('Unit cost').' '.($supplierProduct->supplier?->currency?->code ?? '')),
                                    'value'    => $supplierProduct->cost,
                                    'required' => true,
                                ],
                                'units_per_pack' => [
                                    'type'  => 'input',
                                    'label' => __('Units per pack'),
                                    'value' => $supplierProduct->units_per_pack,
                                ],
                                'units_per_carton' => [
                                    'type'  => 'input',
                                    'label' => __('Units per carton'),
                                    'value' => $supplierProduct->units_per_carton,
                                ],
                                'cbm' => [
                                    'type'  => 'input',
                                    'label' => __('CBM'),
                                    'value' => $supplierProduct->cbm,
                                ],
                                'extra_costs' => [
                                    'type'  => 'input',
                                    'label' => __('Extra costs (%)'),
                                    'value' => $supplierProduct->extra_costs,
                                ],
                                'minimum_carton_order' => [
                                    'type'    => 'input',
                                    'label'   => __('Minimum order (cartons)'),
                                    'value'   => Arr::get($supplierProduct->data, 'minimum_carton_order'),
                                    'options' => ['inputType' => 'number']
                                ],
                                'delivery_time' => [
                                    'type'    => 'input',
                                    'label'   => __('Average delivery time (days)'),
                                    'value'   => Arr::get($supplierProduct->data, 'delivery_time'),
                                    'options' => ['inputType' => 'number']
                                ],
                                'unit_expense' => [
                                    'type'    => 'input',
                                    'label'   => __('Unit expense'),
                                    'value'   => Arr::get($supplierProduct->data, 'unit_expense'),
                                    'options' => ['inputType' => 'number']
                                ],
                                'is_available' => [
                                    'type'  => 'toggle',
                                    'label' => __('Available'),
                                    'value' => $supplierProduct->is_available,
                                ],
                            ]
                        ],
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.supplier-product.update',
                            'parameters' => $supplierProduct->id
                        ],
                    ]
                ],
            ]
        );
    }

    public function getBreadcrumbs(SupplierProduct $supplierProduct, string $routeName, array $routeParameters): array
    {
        return ShowSupplierProduct::make()->getBreadcrumbs(
            supplierProduct: $supplierProduct,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
