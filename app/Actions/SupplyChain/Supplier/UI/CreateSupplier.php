<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jan 2024 09:36:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\Helpers\Country\UI\GetCountriesOptions;
use App\Actions\Helpers\Currency\UI\GetCurrenciesOptions;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgSupplier\UI\IndexOrgSuppliers;
use App\Actions\SupplyChain\SupplierProduct\CopySupplierProductsToSupplier;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Http\Resources\Helpers\AddressFormFieldsResource;
use App\Models\Helpers\Address;
use App\Models\Helpers\Currency;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreateSupplier extends OrgAction
{
    use WithSupplyChainEditAuthorisation;

    private const INCOTERMS = ['EXW', 'FCA', 'FAS', 'FOB', 'CFR', 'CIF', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP'];

    private ?Agent $agent = null;

    private ?Organisation $scopeOrganisation = null;

    private ?int $copiedProductsCurrencyId = null;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->scopeOrganisation) {
            return $request->user()->authTo("procurement.{$this->scopeOrganisation->id}.edit");
        }

        if ($this->agent && $request->user()->authTo("procurement.{$this->agent->organisation_id}.edit")) {
            return true;
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    public function handle(Group|Agent $parent, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = array_values($request->route()->originalParameters());

        $copiedSupplierProductIds    = $this->getCopiedSupplierProductIds($parent, $request);
        $copiedOrgSupplierProductIds = $copiedSupplierProductIds ? [] : $this->getCopiedOrgSupplierProductIds($parent, $request);
        $numberCopiedProducts        = count($copiedSupplierProductIds) ?: count($copiedOrgSupplierProductIds);
        $copiedProductsBody          = $copiedSupplierProductIds
            ? ['supplier_products' => $copiedSupplierProductIds]
            : ['org_supplier_products' => $copiedOrgSupplierProductIds];

        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $request->route()->originalParameters()
                ),
                'title'       => __('New Supplier'),
                'pageHead'    => [
                    'title'      => __('New Supplier'),
                    'afterTitle' => $numberCopiedProducts
                        ? ['label' => trans_choice('with :count selected product|with :count selected products', $numberCopiedProducts, ['count' => $numberCopiedProducts])]
                        : null,
                    'actions'    => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => preg_replace('/create\w*$/', 'index', $routeName),
                                'parameters' => $routeParameters,
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => $this->getBlueprint($parent),
                    'route'     => $this->getStoreRoute($parent) + ($numberCopiedProducts ? ['body' => $copiedProductsBody] : []),
                ],
            ]
        );
    }

    public function asController(ActionRequest $request): Response
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group, $request);
    }

    public function inAgent(Agent $agent, ActionRequest $request): Response
    {
        $this->agent = $agent;
        $this->initialisationFromGroup($agent->group, $request);

        return $this->handle($agent, $request);
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): Response
    {
        $agent = Agent::where('organisation_id', $organisation->id)->first();

        if ($agent) {
            $this->agent = $agent;
            $this->initialisationFromGroup($agent->group, $request);

            return $this->handle($agent, $request);
        }

        $this->scopeOrganisation = $organisation;
        $this->initialisationFromGroup($organisation->group, $request);

        return $this->handle($organisation->group, $request);
    }

    /**
     * The group Supplier Products page selects supplier products, not the rows of one organisation.
     *
     * @return array<int, int>
     */
    private function getCopiedSupplierProductIds(Group|Agent $parent, ActionRequest $request): array
    {
        $supplierProductIds = array_filter(array_map('intval', (array) $request->query('supplier_products', [])));

        if ($this->scopeOrganisation || !$supplierProductIds) {
            return [];
        }

        $copySupplierProducts = CopySupplierProductsToSupplier::make();

        $supplierProducts = $copySupplierProducts
            ->copyableSupplierProducts(
                $copySupplierProducts->getAccessibleAgents($request->user())->pluck('id'),
                $copySupplierProducts->canEditFreeSuppliers($request->user())
            )
            ->where('group_id', $request->user()->group_id)
            ->whereIn('id', $supplierProductIds)
            ->get(['id', 'agent_id', 'currency_id']);

        $parentAgentId = $parent instanceof Agent ? $parent->id : null;

        if ($supplierProducts->isNotEmpty() && $supplierProducts->first()->agent_id === $parentAgentId) {
            $this->copiedProductsCurrencyId = $supplierProducts->first()->currency_id;
        }

        return $supplierProducts->pluck('id')->all();
    }

    /**
     * @return array<int, int>
     */
    private function getCopiedOrgSupplierProductIds(Group|Agent $parent, ActionRequest $request): array
    {
        $orgSupplierProductIds = array_filter(array_map('intval', (array) $request->query('org_supplier_products', [])));

        if (!$parent instanceof Agent || !$orgSupplierProductIds) {
            return [];
        }

        $copySupplierProducts = CopySupplierProductsToSupplier::make();
        $supplierProducts     = $copySupplierProducts->getAgentsSupplierProducts(
            $copySupplierProducts->getAccessibleAgents($request->user())->pluck('id')->all(),
            $orgSupplierProductIds
        );
        $supplierProductIds   = $supplierProducts->pluck('id');

        if ($supplierProducts->first()?->agent_id === $parent->id) {
            $this->copiedProductsCurrencyId = $supplierProducts->first()->currency_id;
        }

        return OrgSupplierProduct::whereIn('id', $orgSupplierProductIds)
            ->whereIn('supplier_product_id', $supplierProductIds)
            ->pluck('id')
            ->all();
    }

    protected function getBlueprint(Group|Agent $parent): array
    {
        $isInAgent = $parent instanceof Agent;

        return array_values(array_filter([
            $isInAgent ? null : [
                'title'  => __('Type'),
                'icon'   => 'fal fa-truck',
                'fields' => [
                    'delivery_type' => [
                        'type'        => 'select',
                        'label'       => __('Delivery Type'),
                        'value'       => null,
                        'mode'        => 'single',
                        'options'     => [
                            ['label' => __('Container'), 'value' => 'container'],
                            ['label' => __('Parcel'), 'value' => 'parcel'],
                        ],
                    ],
                ],
            ],
            $isInAgent ? null : [
                'title'  => __('Delivery Terms'),
                'icon'   => 'fal fa-ship',
                'fields' => [
                    'incoterm'       => [
                        'type'    => 'select',
                        'label'   => __('Incoterm'),
                        'value'   => null,
                        'mode'    => 'single',
                        'options' => array_map(fn ($code) => ['label' => $code, 'value' => $code], self::INCOTERMS),
                        'showIf'  => ['field' => 'delivery_type', 'value' => 'container'],
                    ],
                    'port_of_export' => [
                        'type'   => 'input',
                        'label'  => __('Port of Export'),
                        'value'  => '',
                        'showIf' => ['field' => 'delivery_type', 'value' => 'container'],
                    ],
                    'port_of_import' => [
                        'type'   => 'input',
                        'label'  => __('Port of Import'),
                        'value'  => '',
                        'showIf' => ['field' => 'delivery_type', 'value' => 'container'],
                    ],
                ],
            ],
            [
                'title'  => __('ID/Contact Details'),
                'icon'   => 'fal fa-address-book',
                'fields' => [
                    'code'            => [
                        'type'      => 'input',
                        'label'     => __('Code'),
                        'value'     => '',
                        'uppercase'   => true,
                        'required'    => true,
                        'takenValues' => $this->getTakenCodes(),
                    ],
                    'name'            => [
                        'type'     => 'input',
                        'label'    => __('Name'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'contact_name'    => [
                        'type'     => 'input',
                        'label'    => __('Contact Name'),
                        'value'    => '',
                        'required' => true,
                    ],
                    'company_name'    => [
                        'type'     => 'input',
                        'label'    => __('Company Name'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'contact_website' => [
                        'type'     => 'input',
                        'label'    => __('Contact Website'),
                        'value'    => '',
                        'required' => false,
                    ],
                    'email'           => [
                        'type'    => 'input',
                        'label'   => __('Email'),
                        'value'   => '',
                        'options' => [
                            'inputType' => 'email',
                        ],
                    ],
                    'phone'           => [
                        'type'  => 'phone',
                        'label' => __('Phone'),
                        'value' => '',
                    ],
                    'address'         => [
                        'type'     => 'address',
                        'label'    => __('Address'),
                        'value'    => AddressFormFieldsResource::make(new Address(['country_id' => group()->country_id]))->getArray(),
                        'required' => true,
                        'options'  => [
                        ],
                    ],
                ],
            ],
            [
                'title'  => __("Supplier's Products Settings"),
                'icon'   => 'fal fa-cog',
                'fields' => [
                    'default_product_allow_on_demand' => [
                        'type'  => 'toggle',
                        'label' => __('Allow On Demand'),
                        'value' => false,
                    ],
                    'default_product_country_origin'  => [
                        'type'        => 'select',
                        'label'       => __("Product's Country of Origin"),
                        'options'     => GetCountriesOptions::run(),
                        'mode'        => 'single',
                        'searchable'  => true,
                    ],
                ],
            ],
            [
                'title'  => __("Waiting Times"),
                'icon'   => 'fal fa-clock',
                'fields' => [
                    'production_waiting_time' => [
                        'type'  => 'input',
                        'label' => __('Production Waiting Time (Days)'),
                        'value' => '',
                    ],
                    'delivery_time'           => [
                        'type'  => 'input',
                        'label' => __('Delivery Time (Days)'),
                        'value' => '',
                    ],
                ],
            ],
            [
                'title'  => __("Payment Settings"),
                'icon'   => 'fal fa-credit-card',
                'fields' => [
                    'currency_id'   => [
                        'type'       => 'select',
                        'label'      => __('Currency'),
                        'options'    => GetCurrenciesOptions::run(),
                        'value'      => $this->copiedProductsCurrencyId,
                        'required'   => true,
                        'mode'       => 'single',
                        'searchable' => true,
                    ],
                    'payment_terms' => [
                        'type'  => 'input',
                        'label' => __('Payment Terms'),
                        'value' => '',
                    ],
                ],
            ],
            $isInAgent ? null : [
                'title'  => __("Purchase Order Settings"),
                'icon'   => 'fal fa-file-invoice-dollar',
                'fields' => [
                    'minimum_order'       => [
                        'type'        => 'input',
                        'label'       => __('Minimum order value'),
                        'information' => __('The least the supplier accepts per order, as the total cost in the supplier\'s currency. The Pre-orders by supplier page shows each supplier\'s pre-order total against it in green or red. Purchase orders are not blocked below it. The minimum per product, in cartons, is set on each supplier product.'),
                        'value'       => '',
                        'labelFrom' => [
                            'field'   => 'currency_id',
                            'options' => Currency::pluck('code', 'id'),
                        ],
                    ],
                ],
            ],
        ]));
    }

    protected function getTakenCodes(): array
    {
        return Supplier::withTrashed()->where('group_id', $this->group->id)
            ->get(['code', 'name'])
            ->mapWithKeys(fn (Supplier $supplier) => [strtolower($supplier->code) => $supplier->name ?: $supplier->code])
            ->all();
    }

    protected function getStoreRoute(Group|Agent $parent): array
    {
        if ($parent instanceof Agent) {
            return [
                'name'       => 'grp.models.agent.supplier.store',
                'parameters' => [$parent->id],
            ];
        }

        if ($this->scopeOrganisation) {
            return [
                'name'       => 'grp.models.org.supplier.store',
                'parameters' => [$this->scopeOrganisation->id],
            ];
        }

        return [
            'name' => 'grp.models.supplier.store',
        ];
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        if (str_starts_with($routeName, 'grp.org.procurement.')) {
            return array_merge(
                IndexOrgSuppliers::make()->getBreadcrumbs('grp.org.procurement.org_suppliers.index', $routeParameters),
                [
                    [
                        'type'          => 'creatingModel',
                        'creatingModel' => [
                            'label' => __('Creating Supplier'),
                        ],
                    ],
                ]
            );
        }

        return array_merge(
            IndexSuppliers::make()->getBreadcrumbs($routeName, $routeParameters),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __('Creating Supplier'),
                    ],
                ],
            ]
        );
    }
}
