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
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum as Column;
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
            'SupplyChain/SupplierProductCreate',
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
                'supplier'    => [
                    'name'     => $supplier->name,
                    'currency' => $supplier->currency?->code,
                ],
                'sections'    => $this->getSections($supplier),
                'routes'      => [
                    'check' => [
                        'name'       => 'grp.models.supplier.supplier-product.check_form',
                        'parameters' => ['supplier' => $supplier->id],
                    ],
                    'store' => [
                        'name'       => 'grp.models.supplier.supplier-product.store_from_form',
                        'parameters' => ['supplier' => $supplier->id],
                    ],
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

    /**
     * The supplier product upload columns, grouped for the form. Labels are the sheet headings so the
     * findings, which name the headings, point at the right field.
     *
     * @return list<array{title: string, fields: list<array{key: string, label: string, required: bool, placeholder: ?string, value: mixed}>}>
     */
    protected function getSections(Supplier $supplier): array
    {
        $sections = [
            __('Product')            => [Column::FAMILY, Column::PART_REFERENCE, Column::UNIT_NAME, Column::SUPPLIER_CODE, Column::UNIT_LABEL, Column::UNIT_BARCODE, Column::MATERIALS, Column::TARIFF_CODE],
            __('Packing and ordering') => [Column::UNITS_PER_SKO, Column::SKOS_PER_OUTER, Column::SKOS_PER_CARTON, Column::MINIMUM_ORDER_CARTONS, Column::DELIVERY_DAYS],
            __('Cost and prices')    => [Column::UNIT_COST, Column::UNIT_EXPENSE, Column::EXTRA_COSTS, Column::RECOMMENDED_PRICE, Column::RECOMMENDED_RRP, Column::RECOMMENDED_PRICE_EUR, Column::RECOMMENDED_RRP_EUR],
            __('Weights and sizes')  => [Column::UNIT_WEIGHT, Column::UNIT_DIMENSIONS, Column::SKO_WEIGHT, Column::SKO_DIMENSIONS, Column::CARTON_WEIGHT, Column::CARTON_CBM],
        ];

        $placeholders = [
            Column::FAMILY->value          => __('Existing family code, or a new one'),
            Column::SUPPLIER_CODE->value   => __('Empty = Part reference'),
            Column::UNIT_LABEL->value      => __('piece, bag, jar…'),
            Column::UNIT_BARCODE->value    => __('EAN-13, or "auto" for the next barcode from our pool'),
            Column::TARIFF_CODE->value     => '4202.12.9990',
            Column::EXTRA_COSTS->value     => '40%',
            Column::UNIT_WEIGHT->value     => 'kg',
            Column::SKO_WEIGHT->value      => 'kg',
            Column::CARTON_WEIGHT->value   => 'kg',
            Column::UNIT_DIMENSIONS->value => '20x10x5',
            Column::SKO_DIMENSIONS->value  => '20x10x5',
            Column::CARTON_CBM->value      => 'm³',
        ];

        $values = [
            Column::DELIVERY_DAYS->value => Arr::get($supplier->data, 'delivery_time'),
        ];

        $currency = $supplier->currency?->code ?? __('supplier currency');

        return collect($sections)->map(fn (array $columns, string $title) => [
            'title'  => $title,
            'fields' => array_map(fn (Column $column) => [
                'key'         => $column->value,
                'label'       => str_replace('Sup Cur', $currency, $column->heading()),
                'required'    => $column->isRequired() && !in_array($column, [Column::SUPPLIER_CODE, Column::UNIT_EXPENSE, Column::UNIT_BARCODE], true),
                'placeholder' => $placeholders[$column->value] ?? null,
                'value'       => $values[$column->value] ?? null,
            ], $columns),
        ])->values()->all();
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
