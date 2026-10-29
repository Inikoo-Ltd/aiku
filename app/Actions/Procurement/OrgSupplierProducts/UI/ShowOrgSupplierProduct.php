<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Traits\Authorisations\WithAgentProductsAuthorisation;
use App\Actions\Procurement\ProcurementNote\UI\IndexProcurementNotes;
use App\Actions\Procurement\SupplierAgreedTerm\UI\GetAgreement;
use App\Actions\Procurement\SupplierAgreedTerm\UI\IndexSupplierAgreedTerms;
use App\Http\Resources\Procurement\ProcurementNoteResource;
use App\Http\Resources\Procurement\SupplierAgreedTermResource;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\PackingSpec\UI\GetOrgSupplierProductPackingSpec;
use App\Enums\SysAdmin\Authorisation\GroupPermissionsEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Enums\UI\Procurement\OrgSupplierProductTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\PurchaseOrdersResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgSupplierProduct extends OrgAction
{
    use WithAgentProductsAuthorisation;
    use WithAgentOrganisation;

    public function handle(OrgSupplierProduct $orgSupplierProduct): OrgSupplierProduct
    {
        return $orgSupplierProduct;
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->initialisation($organisation, $request)->withTab($this->getTabs());
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct);
    }

    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        abort_unless($orgSupplierProduct->org_agent_id === $orgAgent->id, 404);

        $this->initialisation($organisation, $request)->withTab($this->getTabs());
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct);
    }

    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        abort_unless($orgSupplierProduct->org_supplier_id === $orgSupplier->id, 404);

        $this->initialisation($organisation, $request)->withTab($this->getTabs());
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct);
    }

    public function htmlResponse(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): Response
    {
        $forAgent     = $this->organisation->type === OrganisationTypeEnum::AGENT || $orgSupplierProduct->organisation_id !== $this->organisation->id;
        $supplierCode = $orgSupplierProduct->supplierProduct->code;
        $internalCode = $this->organisation->type === OrganisationTypeEnum::AGENT ? $orgSupplierProduct->supplierProduct->stocks->first()?->code : null;

        return Inertia::render(
            'Procurement/OrgSupplierProduct',
            [
                'title'       => '(' . $orgSupplierProduct->supplierProduct->code . ') ' . __('Supplier Product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgSupplierProduct,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($orgSupplierProduct, $request),
                    'next'     => $this->getNext($orgSupplierProduct, $request),
                ],
                'pageHead'    => [
                    'title'      => $internalCode ?? $supplierCode,
                    'afterTitle' => ['label' => $internalCode ? $supplierCode.' · '.$orgSupplierProduct->supplierProduct->name : $orgSupplierProduct->supplierProduct->name],
                    'icon'  => [
                        'icon'  => ['fal', 'box-usd'],
                        'title' => __('Supplier Product'),
                    ],
                    'actions' => [
                        $this->organisationAgentSupplies($orgSupplierProduct) && $request->user()->authTo("procurement.{$this->organisation->id}.edit") ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'label' => __('Edit'),
                            'route' => [
                                'name'       => 'grp.org.procurement.org_supplier_products.edit',
                                'parameters' => [
                                    'organisation'       => $this->organisation->slug,
                                    'orgSupplierProduct' => $orgSupplierProduct->slug,
                                ],
                            ],
                        ] : false,
                        $request->user()->authTo(GroupPermissionsEnum::SUPPLY_CHAIN_EDIT->value) ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'key'     => 'edit-cost-and-units',
                            'tooltip' => __('Edit cost and units'),
                            'route' => [
                                'name'       => 'grp.supply-chain.supplier_products.edit',
                                'parameters' => [
                                    'supplierProduct' => $orgSupplierProduct->supplierProduct->slug,
                                ],
                            ],
                        ] : false,
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgSupplierProductTabsEnum::navigationOnly($this->getTabs()),
                ],

                OrgSupplierProductTabsEnum::SHOWCASE->value => $this->tab == OrgSupplierProductTabsEnum::SHOWCASE->value ?
                    fn () => $this->showcase($orgSupplierProduct)
                    : Inertia::optional(fn () => $this->showcase($orgSupplierProduct)),

                'agreement' => $this->hideProcurementDetails ? null : GetAgreement::run($orgSupplierProduct->supplierProduct, $orgSupplierProduct->organisation, $request->user(), $forAgent),

                ...($this->hideProcurementDetails ? [] : $this->procurementTabs($orgSupplierProduct, $request, $forAgent)),
            ],
        )->table(IndexSupplierAgreedTerms::make()->tableStructure(prefix: OrgSupplierProductTabsEnum::AGREED_TERMS->value))
            ->table(IndexProcurementNotes::make()->tableStructure(prefix: OrgSupplierProductTabsEnum::NOTES->value))
            ->table(IndexPurchaseOrders::make()->viewedFrom($this->organisation)->tableStructure(parent: $orgSupplierProduct, prefix: OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value))
            ->table(IndexHistory::make()->tableStructure(prefix: OrgSupplierProductTabsEnum::HISTORY->value));
    }

    public function jsonResponse(OrgSupplierProduct $orgSupplierProduct): SupplierProductResource
    {
        return new SupplierProductResource($orgSupplierProduct->supplierProduct);
    }

    public function getBreadcrumbs(OrgSupplierProduct $orgSupplierProduct, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (array $index, array $model) use ($orgSupplierProduct, $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $index,
                            'label' => __('Supplier Products'),
                        ],
                        'model' => [
                            'route' => $model,
                            'label' => $orgSupplierProduct->supplierProduct->name,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.procurement.org_supplier_products.show' =>
            array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs(Arr::only($routeParameters, 'organisation')),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_supplier_products.index',
                        'parameters' => Arr::only($routeParameters, 'organisation'),
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            'grp.org.procurement.org_agents.show.supplier_products.show' =>
            array_merge(
                ShowOrgAgent::make()->getBreadcrumbs($routeName, $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_agents.show.supplier_products.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'orgAgent']),
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            'grp.org.procurement.org_suppliers.show.supplier_products.show' =>
            array_merge(
                ShowOrgSupplier::make()->getBreadcrumbs(
                    'grp.org.procurement.org_suppliers.show',
                    Arr::only($routeParameters, ['organisation', 'orgSupplier'])
                ),
                $headCrumb(
                    [
                        'name'       => 'grp.org.procurement.org_suppliers.show.supplier_products.index',
                        'parameters' => Arr::only($routeParameters, ['organisation', 'orgSupplier']),
                    ],
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                ),
            ),
            default => [],
        };
    }

    public function getPrevious(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): ?array
    {
        $previous = $this->siblings($request)
            ->whereRaw('(supplier_products.code, org_supplier_products.id) < (?, ?)', [$orgSupplierProduct->supplierProduct->code, $orgSupplierProduct->id])
            ->orderBy('supplier_products.code', 'desc')
            ->orderBy('org_supplier_products.id', 'desc')
            ->first();

        return $this->getNavigation($previous, $request);
    }

    public function getNext(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): ?array
    {
        $next = $this->siblings($request)
            ->whereRaw('(supplier_products.code, org_supplier_products.id) > (?, ?)', [$orgSupplierProduct->supplierProduct->code, $orgSupplierProduct->id])
            ->orderBy('supplier_products.code')
            ->orderBy('org_supplier_products.id')
            ->first();

        return $this->getNavigation($next, $request);
    }

    /**
     * The same set the supplier products list shows for this route, in the same code order, so
     * stepping through them walks the list the user came from.
     */
    private function siblings(ActionRequest $request): Builder
    {
        $query = OrgSupplierProduct::query()
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->select('org_supplier_products.*', 'supplier_products.code', 'supplier_products.name');

        $orgAgent    = $request->route('orgAgent');
        $orgSupplier = $request->route('orgSupplier');
        $agent       = $this->getOrganisationAgent($this->organisation);

        if ($orgAgent instanceof OrgAgent) {
            $query->where('org_supplier_products.org_agent_id', $orgAgent->id);
        } elseif ($orgSupplier instanceof OrgSupplier) {
            $query->where('org_supplier_products.org_supplier_id', $orgSupplier->id);
        } elseif ($agent) {
            $query->whereIn('org_supplier_products.org_agent_id', OrgAgent::where('agent_id', $agent->id)->select('id'));
        } else {
            $query->where('org_supplier_products.organisation_id', $this->organisation->id);
        }

        return $query;
    }

    private function getNavigation(?OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): ?array
    {
        if (!$orgSupplierProduct) {
            return null;
        }

        return [
            'label' => $orgSupplierProduct->name,
            'route' => [
                'name'       => $request->route()->getName(),
                'parameters' => array_merge(
                    $request->route()->originalParameters(),
                    ['orgSupplierProduct' => $orgSupplierProduct->slug]
                ),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function procurementTabs(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request, bool $forAgent): array
    {
        return [
            OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value => $this->tab == OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value ?
                fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::make()->viewedFrom($this->organisation)->handle($orgSupplierProduct))
                : Inertia::optional(fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::make()->viewedFrom($this->organisation)->handle($orgSupplierProduct))),

            OrgSupplierProductTabsEnum::AGREED_TERMS->value => $this->tab == OrgSupplierProductTabsEnum::AGREED_TERMS->value ?
                fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($orgSupplierProduct->supplierProduct, OrgSupplierProductTabsEnum::AGREED_TERMS->value, $orgSupplierProduct->organisation, $forAgent))
                : Inertia::optional(fn () => SupplierAgreedTermResource::collection(IndexSupplierAgreedTerms::run($orgSupplierProduct->supplierProduct, OrgSupplierProductTabsEnum::AGREED_TERMS->value, $orgSupplierProduct->organisation, $forAgent))),

            OrgSupplierProductTabsEnum::NOTES->value => $this->tab == OrgSupplierProductTabsEnum::NOTES->value ?
                fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($orgSupplierProduct->supplierProduct, OrgSupplierProductTabsEnum::NOTES->value, $orgSupplierProduct->organisation, $forAgent))
                : Inertia::optional(fn () => ProcurementNoteResource::collection(IndexProcurementNotes::run($orgSupplierProduct->supplierProduct, OrgSupplierProductTabsEnum::NOTES->value, $orgSupplierProduct->organisation, $forAgent))),

            OrgSupplierProductTabsEnum::PACKING->value => $this->tab == OrgSupplierProductTabsEnum::PACKING->value ?
                fn () => GetOrgSupplierProductPackingSpec::run($orgSupplierProduct, $this->organisation, $request->user())
                : Inertia::optional(fn () => GetOrgSupplierProductPackingSpec::run($orgSupplierProduct, $this->organisation, $request->user())),

            OrgSupplierProductTabsEnum::HISTORY->value => $this->tab == OrgSupplierProductTabsEnum::HISTORY->value ?
                fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct, OrgSupplierProductTabsEnum::HISTORY->value))
                : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct, OrgSupplierProductTabsEnum::HISTORY->value))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function showcase(OrgSupplierProduct $orgSupplierProduct): array
    {
        $showcase = GetOrgSupplierProductShowcase::run($orgSupplierProduct);

        if ($this->hideProcurementDetails) {
            Arr::forget($showcase, ['costs.unit_cost', 'agent.key.cost', 'agent.key.moq.value', 'stats']);
        }

        return $showcase;
    }

    private function getTabs(): array
    {
        if ($this->hideProcurementDetails) {
            return [OrgSupplierProductTabsEnum::SHOWCASE->value];
        }

        return [
            OrgSupplierProductTabsEnum::SHOWCASE->value,
            OrgSupplierProductTabsEnum::PACKING->value,
            OrgSupplierProductTabsEnum::AGREED_TERMS->value,
            OrgSupplierProductTabsEnum::NOTES->value,
            OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value,
            OrgSupplierProductTabsEnum::HISTORY->value,
        ];
    }
}
