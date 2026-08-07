<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 May 2024 12:06:23 British Summer Time, Plane Manchester-Malaga
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgAgent\UI\ShowOrgAgent;
use App\Actions\Procurement\OrgSupplier\UI\ShowOrgSupplier;
use App\Actions\Procurement\PurchaseOrder\UI\IndexPurchaseOrders;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Enums\UI\Procurement\OrgSupplierProductTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\Procurement\PurchaseOrderResource;
use App\Http\Resources\SupplyChain\SupplierProductResource;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgSupplierProduct extends OrgAction
{
    use WithAgentOrganisation;

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

        return $request->user()->authTo("procurement.{$this->organisation->id}.view");
    }

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
                'title'       => __('Supplier Product'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $orgSupplierProduct,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
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
                    fn () => PurchaseOrderResource::collection(IndexPurchaseOrders::run($orgSupplierProduct))
                    : Inertia::optional(fn () => PurchaseOrderResource::collection(IndexPurchaseOrders::run($orgSupplierProduct))),

                OrgSupplierProductTabsEnum::HISTORY->value => $this->tab == OrgSupplierProductTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($orgSupplierProduct))),
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

    private function getTabs(): array
    {
        return [
            OrgSupplierProductTabsEnum::SHOWCASE->value,
            OrgSupplierProductTabsEnum::PURCHASE_ORDERS->value,
            OrgSupplierProductTabsEnum::HISTORY->value,
        ];
    }
}
