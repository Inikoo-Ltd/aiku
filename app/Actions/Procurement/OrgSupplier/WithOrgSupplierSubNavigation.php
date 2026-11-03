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
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;

trait WithOrgSupplierSubNavigation
{
    protected function getOrgSupplierPurchaseOrderAction(OrgSupplier $orgSupplier): array
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
     * @return array{purchase_orders: int, stock_deliveries: int}|null
     */
    public static function agentSupplierNumbers(Organisation $organisation, OrgSupplier $orgSupplier): ?array
    {
        $agent = $organisation->type === OrganisationTypeEnum::AGENT ? $organisation->agent : null;
        if (!$agent) {
            return null;
        }

        $purchaseOrders = PurchaseOrder::where('agent_id', $agent->id)
            ->where('supplier_id', $orgSupplier->supplier_id)
            ->where('state', '!=', PurchaseOrderStateEnum::IN_PROCESS);

        return [
            'purchase_orders'  => $purchaseOrders->count(),
            'stock_deliveries' => StockDelivery::where(fn ($query) => IndexStockDeliveries::whereAgentSupplierDelivery($query, $agent, $orgSupplier->supplier_id))->count(),
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
                'number'   => $parent->stats->number_current_org_supplier_products,
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
                    'route'    => [
                        'name'       => $this->organisation->type === OrganisationTypeEnum::AGENT
                            ? 'grp.org.agent.org_suppliers.show.repair_supplier_products.index'
                            : 'grp.org.procurement.org_suppliers.show.repair_supplier_products.index',
                        'parameters' => $routeParameters,
                    ],
                    'leftIcon' => [
                        'icon'    => ['fal', 'fa-tools'],
                        'tooltip' => __('Move these products to their real supplier'),
                    ],
                ],
            ] : []),
        ];
    }
}
