<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
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
    use WithProcurementAuthorisation;
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

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->initialisation($organisation, $request)->withTab($this->getTabs());
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgSupplier(Organisation $organisation, OrgSupplier $orgSupplier, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): OrgSupplierProduct
    {
        $this->initialisation($organisation, $request)->withTab($this->getTabs());
        $this->authorizeProcurementRecord($orgSupplierProduct);

        return $this->handle($orgSupplierProduct);
    }

    public function htmlResponse(OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): Response
    {
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
                    'title' => $orgSupplierProduct->supplierProduct->name,
                    'model' => __('Supplier Product'),
                    'icon'  => [
                        'icon'  => ['fal', 'box-usd'],
                        'title' => __('Supplier Product'),
                    ],
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => OrgSupplierProductTabsEnum::navigationOnly($this->getTabs()),
                ],

                OrgSupplierProductTabsEnum::SHOWCASE->value => $this->tab == OrgSupplierProductTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgSupplierProductShowcase::run($orgSupplierProduct)
                    : Inertia::optional(fn () => GetOrgSupplierProductShowcase::run($orgSupplierProduct)),

                OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value => $this->tab == OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value ?
                    fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($orgSupplierProduct))
                    : Inertia::optional(fn () => PurchaseOrdersResource::collection(IndexPurchaseOrders::run($orgSupplierProduct))),

                OrgSupplierProductTabsEnum::HISTORY->value => $this->tab == OrgSupplierProductTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct, OrgSupplierProductTabsEnum::HISTORY->value))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct, OrgSupplierProductTabsEnum::HISTORY->value))),
            ],
        )->table(IndexPurchaseOrders::make()->tableStructure(parent: $orgSupplierProduct, prefix: OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value))
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

    private function getTabs(): array
    {
        return [
            OrgSupplierProductTabsEnum::SHOWCASE->value,
            OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value,
            OrgSupplierProductTabsEnum::HISTORY->value,
        ];
    }
}
