<?php

/*
 * author Arya Permana - Kirin
 * created on 17-10-2024-10h-13m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\UI\Grp\Layout;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetAgentOrganisationNavigation
{
    use AsAction;
    use WithLayoutNavigation;

    public function handle(User $user, Organisation $organisation): array
    {
        $navigation = [];

        if ($user->authTo("procurement.$organisation->id.view")) {
            $topMenus = [
                'agent_incoming_purchase_orders' => [],
                'agent_suppliers' => [
                    $this->procurementSubSection(__('Suppliers'), 'fa-person-dolly', 'grp.org.agent.org_suppliers.', 'grp.org.agent.org_suppliers.index', $organisation),
                ],
                'agent_products' => [
                    $this->procurementSubSection(__('Products'), 'fa-box-usd', 'grp.org.agent.org_supplier_products.', 'grp.org.agent.org_supplier_products.index', $organisation),
                    $this->procurementSubSection(__('Labels'), 'fa-tags', 'grp.org.agent.agent_labels.', 'grp.org.agent.agent_labels.index', $organisation),
                    $this->procurementSubSection(__('Barcodes'), 'fa-barcode', 'grp.org.agent.agent_barcodes.', 'grp.org.agent.agent_barcodes.index', $organisation),
                ],
                'agent_sales' => [
                    $this->procurementSubSection(__('Dashboard'), 'fa-chart-network', 'grp.org.agent.sales.dashboard', 'grp.org.agent.sales.dashboard', $organisation),
                    $this->procurementSubSection(__('Suppliers'), 'fa-person-dolly', 'grp.org.agent.sales.suppliers.', 'grp.org.agent.sales.suppliers.index', $organisation),
                    $this->procurementSubSection(__('Products'), 'fa-box-usd', 'grp.org.agent.sales.products.', 'grp.org.agent.sales.products.index', $organisation),
                ],
                'agent_purchase_orders' => [
                    $this->procurementSubSection(__('Dashboard'), 'fa-chart-network', 'grp.org.agent.purchase_orders.dashboard', 'grp.org.agent.purchase_orders.dashboard', $organisation),
                    $this->procurementSubSection(__('List'), 'fa-clipboard-list', 'grp.org.agent.purchase_orders.index', 'grp.org.agent.purchase_orders.index', $organisation),
                    $this->procurementSubSection(__('Shared suppliers'), 'fa-people-carry', 'grp.org.agent.purchase_orders.shared_suppliers', 'grp.org.agent.purchase_orders.shared_suppliers', $organisation),
                    $this->procurementSubSection(__('Board'), 'fa-columns', 'grp.org.agent.purchase_orders.board', 'grp.org.agent.purchase_orders.board', $organisation),
                    $this->procurementSubSection(__('Reports'), 'fa-chart-line', 'grp.org.agent.purchase_orders.reports', 'grp.org.agent.purchase_orders.reports', $organisation),
                ],
                'agent_containers' => [
                    $this->procurementSubSection(__('Current shipments'), 'fa-box-open', 'grp.org.agent.stock_deliveries.current', 'grp.org.agent.stock_deliveries.current', $organisation),
                    $this->procurementSubSection(__('Board'), 'fa-columns', 'grp.org.agent.stock_deliveries.board', 'grp.org.agent.stock_deliveries.board', $organisation),
                    $this->procurementSubSection(__('Past shipments'), 'fa-history', 'grp.org.agent.stock_deliveries.past', 'grp.org.agent.stock_deliveries.past', $organisation),
                    $this->procurementSubSection(__('Invoices'), 'fa-file-invoice', 'grp.org.agent.accounting.invoices.', 'grp.org.agent.accounting.invoices.index', $organisation),
                ],
                'agent_accounting' => [
                    $this->procurementSubSection(__('Dashboard'), 'fa-chart-network', 'grp.org.agent.accounting.dashboard', 'grp.org.agent.accounting.dashboard', $organisation),
                    $this->procurementSubSection(__('Ledger'), 'fa-book', 'grp.org.agent.accounting.ledger.', 'grp.org.agent.accounting.ledger.index', $organisation),
                    $this->procurementSubSection(__('Deposits'), 'fa-hand-holding-usd', 'grp.org.agent.accounting.deposits.', 'grp.org.agent.accounting.deposits.index', $organisation),
                    $this->procurementSubSection(__('Money requests'), 'fa-money-check-alt', 'grp.org.agent.accounting.deposit_requests.', 'grp.org.agent.accounting.deposit_requests.index', $organisation),
                    $this->procurementSubSection(__('Invoices'), 'fa-file-invoice', 'grp.org.agent.accounting.invoices.', 'grp.org.agent.accounting.invoices.index', $organisation),
                ],
            ];

            foreach (
                [
                    'agent_incoming_purchase_orders' => [__('Incoming POs'), 'fa-paper-plane', 'grp.org.agent.incoming_purchase_orders'],
                    'agent_suppliers'       => [__('Suppliers'), 'fa-person-dolly', 'grp.org.agent.org_suppliers'],
                    'agent_products'        => [__('Products'), 'fa-box-usd', 'grp.org.agent.org_supplier_products'],
                    'agent_sales'           => [__('Sales'), 'fa-chart-line', 'grp.org.agent.sales'],
                    'agent_purchase_orders' => [__('Purchase Orders'), 'fa-clipboard-list', 'grp.org.agent.purchase_orders'],
                    'agent_containers'      => [__('Shipments'), 'fa-truck-container', 'grp.org.agent.stock_deliveries'],
                    'agent_accounting'      => [__('Accounting'), 'fa-file-invoice-dollar', 'grp.org.agent.accounting'],
                ] as $key => [$label, $icon, $root]
            ) {
                if ($key === 'agent_containers') {
                    $navigation['agent_warehouse_navigation'] = [
                        'warehouse'     => $this->procurementSubSection(__('Warehouse'), 'fa-warehouse-alt', 'grp.org.agent.warehouse.index', 'grp.org.agent.warehouse.index', $organisation),
                        'in_production' => $this->procurementSubSection(__('In production'), 'fa-industry-alt', 'grp.org.agent.warehouse.in_production', 'grp.org.agent.warehouse.in_production', $organisation),
                        'received'      => array_merge(
                            $this->procurementSubSection(__('Received'), 'fa-inbox-in', 'grp.org.agent.warehouse.received.', 'grp.org.agent.warehouse.received.index', $organisation),
                            [
                                'topMenu' => [
                                    'subSections' => [
                                        $this->procurementSubSection(__('Received'), 'fa-inbox-in', 'grp.org.agent.warehouse.received.index', 'grp.org.agent.warehouse.received.index', $organisation),
                                        $this->procurementSubSection(__('Goods receipts'), 'fa-clipboard-check', 'grp.org.agent.warehouse.received.goods_receipts.', 'grp.org.agent.warehouse.received.goods_receipts.index', $organisation),
                                    ],
                                ],
                            ]
                        ),
                    ];
                }

                $navigation[$key] = [
                    'root'    => $root.'.',
                    'label'   => $label,
                    'icon'    => ['fal', $icon],
                    'route'   => [
                        'name'       => match ($key) {
                            'agent_sales', 'agent_purchase_orders', 'agent_accounting' => $root.'.dashboard',
                            'agent_containers'      => $root.'.current',
                            default                 => $root.'.index',
                        },
                        'parameters' => [$organisation->slug],
                    ],
                    'topMenu' => [
                        'subSections' => $topMenus[$key]
                    ]
                ];
            }
        }

        $navigation = $this->getHumanResourcesNavs($user, $organisation, $navigation);

        if ($user->authTo(['org-admin.'.$organisation->id, 'org-supervisor.'.$organisation->id.'.procurement'])) {
            $navigation['agent_settings'] = [
                'root'    => 'grp.org.agent.settings.',
                'label'   => __('Agent settings'),
                'icon'    => ['fal', 'fa-cog'],
                'route'   => [
                    'name'       => 'grp.org.agent.settings.edit',
                    'parameters' => [$organisation->slug],
                ],
                'topMenu' => [
                    'subSections' => [],
                ],
            ];
        }

        return $navigation;
    }

    private function procurementSubSection(string $label, string $icon, string $root, string $routeName, Organisation $organisation): array
    {
        return [
            'label' => $label,
            'icon'  => ['fal', $icon],
            'root'  => $root,
            'route' => [
                'name'       => $routeName,
                'parameters' => [$organisation->slug],
            ]
        ];
    }
}
