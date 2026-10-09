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
use App\Enums\SupplyChain\SupplierProduct\SupplierUnitEnum;
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
                                'supplier_unit' => [
                                    'type'        => 'select',
                                    'label'       => __('Supplier sells in'),
                                    'information' => __('Only when the supplier quotes and invoices in a unit that is not ours, e.g. incense by the kg that we count in 500 g bags. Leave empty when the supplier counts like us.'),
                                    'placeholder' => __('Same unit as ours'),
                                    'options'     => collect(SupplierUnitEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                                    'value'       => $supplierProduct->supplier_unit?->value,
                                    'mode'        => 'single',
                                ],
                                'units_per_supplier_unit' => [
                                    'type'        => 'input',
                                    'label'       => __('Our units in one supplier unit'),
                                    'information' => $this->supplierUnitInformation($supplierProduct),
                                    'value'       => $supplierProduct->units_per_supplier_unit === null ? null : (float) $supplierProduct->units_per_supplier_unit,
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
                                ...($supplierProduct->measured_lead_time_days !== null ? [] : [
                                    'estimated_lead_time_days' => [
                                        'type'    => 'input',
                                        'label'   => __('Estimated delivery time (days)'),
                                        'value'   => $supplierProduct->estimated_lead_time_days,
                                        'options' => ['inputType' => 'number']
                                    ],
                                ]),
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

    private function supplierUnitInformation(SupplierProduct $supplierProduct): string
    {
        $information = __('Quantities you order in the supplier unit are turned into our units with this, and the supplier price per unit is our unit cost times it.');

        if ($warning = $supplierProduct->supplierUnitWarning()) {
            return $information.' '.$warning;
        }

        $byWeight = $supplierProduct->unitsPerSupplierUnitByWeight(SupplierUnitEnum::KG);
        if ($byWeight !== null) {
            return $information.' '.__('By the weight of the trade unit, one kg holds :units units.', ['units' => (float) $byWeight]);
        }

        return $information;
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
