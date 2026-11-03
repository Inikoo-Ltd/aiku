<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 12:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\OrgSupplierProduct\OrgSupplierProductStateEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgSupplierProduct extends OrgAction
{
    use WithAgentOrganisation;
    use WithProcurementEditAuthorisation;

    public function handle(OrgSupplierProduct $orgSupplierProduct): OrgSupplierProduct
    {
        return $orgSupplierProduct;
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct);
    }

    public function htmlResponse(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): Response
    {
        $supplierProduct = $orgSupplierProduct->supplierProduct;

        return Inertia::render(
            'EditModel',
            [
                'title'       => '(' . $supplierProduct->code . ') ' . __('Edit supplier product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgSupplierProduct,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'model' => __('Supplier product'),
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
                'formData'    => $this->organisationAgentSupplies($orgSupplierProduct)
                    ? $this->getAgentFormData($orgSupplierProduct, $supplierProduct)
                    : $this->getOrganisationFormData($orgSupplierProduct),
            ]
        );
    }

    /**
     * @return array{blueprint: array<int, array<string, mixed>>, args: array<string, mixed>}
     */
    private function getOrganisationFormData(OrgSupplierProduct $orgSupplierProduct): array
    {
        return [
            'blueprint' => [
                [
                    'title'  => __('Supplier product'),
                    'icon'   => 'fal fa-box-usd',
                    'fields' => [
                        'status' => [
                            'type'  => 'toggle',
                            'label' => __('Status'),
                            'value' => $orgSupplierProduct->status,
                        ],
                        'is_available' => [
                            'type'  => 'toggle',
                            'label' => __('Available'),
                            'value' => $orgSupplierProduct->is_available,
                        ],
                        'state' => [
                            'type'        => 'select',
                            'label'       => __('State'),
                            'information' => __('For this organisation only. A discontinued product is left out when preparing an order.'),
                            'options'     => collect(OrgSupplierProductStateEnum::labels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all(),
                            'value'       => $orgSupplierProduct->state,
                            'required'    => true,
                            'mode'        => 'single',
                        ],
                    ]
                ],
            ],
            'args' => [
                'updateRoute' => [
                    'name'       => 'grp.models.org_supplier_product.update',
                    'parameters' => $orgSupplierProduct->id
                ],
            ]
        ];
    }

    /**
     * What the agent knows first hand about a product it supplies: how it is packed and how long it takes.
     *
     * @return array{blueprint: array<int, array<string, mixed>>, args: array<string, mixed>}
     */
    private function getAgentFormData(OrgSupplierProduct $orgSupplierProduct, SupplierProduct $supplierProduct): array
    {
        return [
            'blueprint' => [
                [
                    'label'  => __('Ordering'),
                    'icon'   => 'fal fa-box-usd',
                    'fields' => [
                        'minimum_carton_order' => [
                            'type'        => 'input',
                            'label'       => __('Minimum order (cartons)'),
                            'information' => __('The fewest cartons that can be ordered at once. Leave empty when there is no minimum.'),
                            'value'       => $supplierProduct->minimum_carton_order,
                            'options'     => ['inputType' => 'number']
                        ],
                        ...($supplierProduct->measured_lead_time_days !== null ? [] : [
                            'estimated_lead_time_days' => [
                                'type'    => 'input',
                                'label'   => __('Estimated delivery time (days)'),
                                'value'   => $supplierProduct->estimated_lead_time_days,
                                'options' => ['inputType' => 'number']
                            ],
                        ]),
                    ]
                ],
                [
                    'label'  => __('Packing details'),
                    'icon'   => 'fal fa-box',
                    'fields' => [
                        'units_per_pack' => [
                            'type'    => 'input',
                            'label'   => __('Units per pack'),
                            'value'   => $supplierProduct->units_per_pack,
                            'options'     => ['inputType' => 'number'],
                            'readonly'    => $supplierProduct->skoPackedIn() !== null,
                            'information' => __('Comes from the SKO composition. To change it, edit the composition of the SKO.'),
                        ],
                        'units_per_carton' => [
                            'type'    => 'input',
                            'label'   => __('Units per carton'),
                            'value'   => $supplierProduct->units_per_carton,
                            'options' => ['inputType' => 'number']
                        ],
                        'cbm' => [
                            'type'  => 'input',
                            'label' => __('CBM'),
                            'value' => $supplierProduct->cbm,
                        ],
                    ]
                ],
            ],
            'args' => [
                'updateRoute' => [
                    'name'       => 'grp.models.org.org_supplier_product.agent_details.update',
                    'parameters' => [
                        'organisation'       => $this->organisation->id,
                        'orgSupplierProduct' => $orgSupplierProduct->id,
                    ],
                ],
            ]
        ];
    }

    public function getBreadcrumbs(OrgSupplierProduct $orgSupplierProduct, string $routeName, array $routeParameters): array
    {
        return ShowOrgSupplierProduct::make()->getBreadcrumbs(
            orgSupplierProduct: $orgSupplierProduct,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
