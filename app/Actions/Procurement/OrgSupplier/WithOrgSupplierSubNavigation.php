<?php

/*
 * author Arya Permana - Kirin
 * created on 12-11-2024-14h-35m
 * github: https://github.com/KirinZero0
 * copyright 2024
 */

namespace App\Actions\Procurement\OrgSupplier;

use App\Actions\GoodsIn\StockDelivery\UI\IndexStockDeliveries;
use App\Actions\Procurement\OrgSupplier\UI\ShowSupplierDraftsToSend;
use App\Actions\SupplyChain\SupplierProduct\RepairSupplierProductsSupplier;
use App\Actions\Traits\Authorisations\SupplierPortalPermissions;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\OrgSupplierStats;
use App\Models\SysAdmin\Organisation;
use App\Support\AgentOrganisationSelection;

trait WithOrgSupplierSubNavigation
{
    /**
     * On the agent's one page per supplier in All, the purchase order is for a buying organisation the user
     * picks first: one button per organisation.
     */
    protected function getOrgSupplierPurchaseOrderAction(OrgSupplier $orgSupplier): array
    {
        $selection = AgentOrganisationSelection::fromRequest();
        if ($selection && !$selection->selected && $selection->rows->first() instanceof OrgSupplier) {
            return [
                'type'   => 'buttonGroup',
                'key'    => 'purchase-order-organisation',
                'button' => $selection->rows->map(fn (OrgSupplier $row) => [
                    ...$this->purchaseOrderAction($row),
                    'label'   => __('Purchase Order').' '.$row->organisation->code,
                    'tooltip' => __('New purchase order for :organisation', ['organisation' => $row->organisation->name]),
                ])->values()->all(),
            ];
        }

        return $this->purchaseOrderAction($orgSupplier);
    }

    private function purchaseOrderAction(OrgSupplier $orgSupplier): array
    {
        return [
            'label' => __('Purchase Order'),
            'type'  => 'button',
            'style' => 'create',
            'route' => [
                'method'     => 'post',
                'name'       => 'grp.models.org-supplier.purchase-order.store',
                'parameters' => [
                    'orgSupplier' => $orgSupplier->id,
                ],
            ],
        ];
    }

    public static function orgSupplierRoute(string $organisationSlug, OrgSupplier $orgSupplier, string $page, ?OrgAgent $orgAgent = null): array
    {
        $selection = AgentOrganisationSelection::fromRequest();
        if (!$orgAgent && $selection) {
            return $selection->route("grp.org.procurement.org_suppliers.$page", ['organisation' => $organisationSlug, 'supplier' => $orgSupplier->supplier->slug]);
        }

        if ($orgAgent) {
            return [
                'name'       => "grp.org.procurement.org_agents.show.suppliers.$page",
                'parameters' => [$organisationSlug, $orgAgent->slug, $orgSupplier->slug],
            ];
        }

        return [
            'name'       => "grp.org.procurement.org_suppliers.$page",
            'parameters' => [$organisationSlug, $orgSupplier->slug],
        ];
    }

    /**
     * An agent organisation sees a supplier's purchase orders and shipments for every buyer, so its
     * counts come from the agent, not from the one buyer's org supplier the page is opened through.
     *
     * On the agent's one page per supplier, the figures are those of the organisation picked in the
     * selector, or the totals of every buying organisation in All.
     *
     * @return array{products: int|null, purchase_orders: int, open_purchase_orders: int, stock_deliveries: int}|null
     */
    public static function agentSupplierNumbers(Organisation $organisation, OrgSupplier $orgSupplier): ?array
    {
        $agent = $organisation->type === OrganisationTypeEnum::AGENT ? $organisation->agent : null;
        if (!$agent) {
            return null;
        }

        $selection       = AgentOrganisationSelection::fromRequest();
        $organisationIds = $selection?->selected ? [$selection->selected->id] : null;

        $purchaseOrders = PurchaseOrder::where('agent_id', $agent->id)
            ->where('supplier_id', $orgSupplier->supplier_id)
            ->where('state', '!=', PurchaseOrderStateEnum::IN_PROCESS)
            ->when($organisationIds, fn ($query) => $query->whereIn('organisation_id', $organisationIds));

        return [
            'products'             => $selection ? (int) OrgSupplierStats::whereIn('org_supplier_id', $selection->selectedRows()->pluck('id'))->sum('number_current_org_supplier_products') : null,
            'purchase_orders'      => (clone $purchaseOrders)->count(),
            'open_purchase_orders' => (clone $purchaseOrders)->whereIn('state', PurchaseOrderStateEnum::openValues())->count(),
            'stock_deliveries'     => StockDelivery::where(fn ($query) => IndexStockDeliveries::whereAgentSupplierDelivery($query, $agent, $orgSupplier->supplier_id))
                ->when($organisationIds, fn ($query) => $query->whereIn('stock_deliveries.organisation_id', $organisationIds))
                ->count(),
        ];
    }

    protected function procurementOrgAgent(): ?OrgAgent
    {
        $route    = request()->route();
        $orgAgent = $route?->parameter('orgAgent');

        return $orgAgent instanceof OrgAgent && str_starts_with((string) $route->getName(), 'grp.org.procurement.org_agents.') ? $orgAgent : null;
    }

    protected function getOrgSupplierNavigation(OrgSupplier $parent): array
    {
        $routeParameters = [$this->organisation->slug, $parent->slug];
        $orgAgent        = $this->procurementOrgAgent();
        $agentNumbers    = self::agentSupplierNumbers($this->organisation, $parent);

        return [
            [
                'label'    => $parent->supplier->code,
                'route'    => self::orgSupplierRoute($this->organisation->slug, $parent, 'show', $orgAgent),
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-person-dolly'],
                    'tooltip' => __('Org Supplier'),
                ],
                'isAnchor' => true,
            ],
            [
                'label'    => __('Products'),
                'route'    => self::orgSupplierRoute($this->organisation->slug, $parent, 'show.supplier_products.index', $orgAgent),
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-box-usd'],
                    'tooltip' => __('Products'),
                ],
                'number'   => $agentNumbers['products'] ?? $parent->stats->number_current_org_supplier_products,
            ],
            ...($parent->org_agent_id ? [] : [
                [
                    'label'    => __('Shopping'),
                    'route'    => [
                        'name'       => 'grp.org.procurement.org_suppliers.show.shopping.dashboard',
                        'parameters' => $routeParameters,
                    ],
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-shopping-basket'],
                        'tooltip' => __('Shopping'),
                    ],
                ],
                [
                    'label'    => __('Shopping List'),
                    'route'    => [
                        'name'       => 'grp.org.procurement.org_suppliers.show.shopping_list.index',
                        'parameters' => $routeParameters,
                    ],
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-list'],
                        'tooltip' => __('Shopping List'),
                    ],
                ],
                [
                    'label'    => __('POs to submit'),
                    'route'    => [
                        'name'       => 'grp.org.procurement.org_suppliers.show.shopping.drafts.index',
                        'parameters' => $routeParameters,
                    ],
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-paper-plane'],
                        'tooltip' => __('Purchase orders to submit'),
                    ],
                    'number'   => ShowSupplierDraftsToSend::drafts($parent)->count(),
                ],
            ]),
            [
                'label'    => __('Purchase Orders'),
                'route'    => self::orgSupplierRoute($this->organisation->slug, $parent, 'show.purchase_orders.index', $orgAgent),
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-clipboard'],
                    'tooltip' => __('Purchase Orders'),
                ],
                'number'   => $agentNumbers['purchase_orders'] ?? $parent->stats->number_purchase_orders,
            ],
            [
                'label'    => __('Stock Deliveries'),
                'route'    => self::orgSupplierRoute($this->organisation->slug, $parent, 'show.stock_deliveries.index', $orgAgent),
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-truck-container'],
                    'tooltip' => __('Stock Deliveries'),
                ],
                'number'   => $agentNumbers['stock_deliveries'] ?? $parent->stats->number_stock_deliveries,
            ],
            ...(!$orgAgent && in_array($parent->supplier->code, RepairSupplierProductsSupplier::REPAIRABLE_SUPPLIER_CODES, true) ? [
                [
                    'label'    => __('Repair'),
                    'route'    => self::orgSupplierRoute($this->organisation->slug, $parent, 'show.repair_supplier_products.index'),
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-tools'],
                        'tooltip' => __('Move these products to their real supplier'),
                    ],
                ],
            ] : []),
            ...$this->supplyChainSupplierNavigation($parent),
        ];
    }

    /**
     * Sales live in supply chain, for supply chain staff only. The portal is a supply chain page too, except for the agent organisation the supplier is bought through, which has its own under the supplier page.
     */
    protected function supplyChainSupplierNavigation(OrgSupplier $orgSupplier): array
    {
        $user     = request()->user();
        $supplier = $orgSupplier->supplier;

        if (SupplierPortalPermissions::agentOrganisationBuysThrough($this->organisation, $supplier)) {
            return $user?->authTo("procurement.{$this->organisation->id}.view") ? [
                $this->supplierPortalNavigationItem(self::orgSupplierRoute($this->organisation->slug, $orgSupplier, 'show.supplier_portal')),
            ] : [];
        }

        if (!$user?->authTo(['supply-chain.view', 'compliance.view'])) {
            return [];
        }

        return [
            [
                'label'    => __('Sales'),
                'route'    => [
                    'name'       => 'grp.supply-chain.suppliers.sales',
                    'parameters' => [$supplier->slug],
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-chart-line'],
                    'tooltip' => __('Sales'),
                ],
            ],
            $this->supplierPortalNavigationItem([
                'name'       => 'grp.supply-chain.suppliers.share',
                'parameters' => [$supplier->slug],
            ]),
        ];
    }

    /**
     * @param  array{name: string, parameters: array<int|string, mixed>}  $route
     */
    private function supplierPortalNavigationItem(array $route): array
    {
        return [
            'label'    => __('Supplier portal'),
            'route'    => $route,
            'leftIcon' => [
                'icon'    => ['fal', 'fa-share-alt'],
                'tooltip' => __('Supplier portal'),
            ],
        ];
    }
}
