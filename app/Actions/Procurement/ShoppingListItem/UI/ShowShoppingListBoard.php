<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\UI\ShowProcurementDashboard;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\SupplyChain\Agent;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowShoppingListBoard extends OrgAction
{
    private ?Agent $scopedAgent = null;

    public function authorize(ActionRequest $request): bool
    {
        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            $this->canEdit = $request->user()->authTo("procurement.{$this->organisation->id}.edit");

            return $request->user()->authTo("procurement.{$this->organisation->id}.view");
        }

        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo('supply-chain.view');
    }

    public function handle(?Agent $agent = null): array
    {
        $query = DB::table('shopping_list_items')
            ->join('supplier_products', 'supplier_products.id', '=', 'shopping_list_items.supplier_product_id')
            ->join('suppliers', 'suppliers.id', '=', 'shopping_list_items.supplier_id')
            ->join('agents', 'agents.id', '=', 'shopping_list_items.agent_id')
            ->join('organisations', 'organisations.id', '=', 'shopping_list_items.organisation_id')
            ->whereIn('shopping_list_items.state', [
                ShoppingListItemStateEnum::OPEN->value,
                ShoppingListItemStateEnum::DISMISS_PROPOSED->value,
            ]);

        if ($agent) {
            $query->where('agents.id', $agent->id);
        }

        $rows = $query
            ->select([
                'agents.id as agent_id',
                'agents.code as agent_code',
                'agents.slug as agent_slug',
                'suppliers.id as supplier_id',
                'suppliers.code as supplier_code',
                'suppliers.slug as supplier_slug',
                'supplier_products.id as supplier_product_id',
                'supplier_products.code as supplier_product_code',
                'supplier_products.name as supplier_product_name',
                'supplier_products.units_per_carton',
                'supplier_products.minimum_carton_order',
                'supplier_products.cost',
                'organisations.code as organisation_code',
                'shopping_list_items.id',
                'shopping_list_items.quantity_units',
                'shopping_list_items.priority',
                'shopping_list_items.state',
                'shopping_list_items.dismiss_reason',
                'shopping_list_items.created_at',
            ])
            ->get();

        $agents = [];

        foreach ($rows->groupBy('agent_id') as $agentRows) {
            $agentRow = $agentRows->first();
            $suppliers = [];

            foreach ($agentRows->groupBy('supplier_id') as $supplierRows) {
                $supplierRow = $supplierRows->first();
                $products    = [];
                $supplierValue = 0;

                foreach ($supplierRows->groupBy('supplier_product_id') as $productRows) {
                    $productRow    = $productRows->first();
                    $totalUnits    = (float) $productRows->sum('quantity_units');
                    $unitsPerCarton = (int) ($productRow->units_per_carton ?? 0);
                    $cartons       = $unitsPerCarton > 0 ? round($totalUnits / $unitsPerCarton, 2) : null;
                    $moq           = $productRow->minimum_carton_order;
                    $moqProgress   = $moq && $cartons !== null ? round(min(($cartons / $moq) * 100, 999), 1) : null;

                    $supplierValue += $totalUnits * (float) $productRow->cost;

                    $products[] = [
                        'supplier_product_id'   => $productRow->supplier_product_id,
                        'code'                  => $productRow->supplier_product_code,
                        'name'                  => $productRow->supplier_product_name,
                        'total_units'           => $totalUnits,
                        'units_per_carton'      => $unitsPerCarton ?: null,
                        'total_cartons'         => $cartons,
                        'minimum_carton_order'  => $moq,
                        'moq_progress'          => $moqProgress,
                        'orgs'                  => $productRows->map(fn ($row) => [
                            'id'                 => $row->id,
                            'organisation_code'  => $row->organisation_code,
                            'units'              => (float) $row->quantity_units,
                            'priority'           => $row->priority,
                            'state'              => $row->state,
                            'dismiss_reason'     => $row->dismiss_reason,
                            'created_at'         => $row->created_at,
                        ])->sortBy('created_at')->values(),
                        'oldest_created_at'     => $productRows->min('created_at'),
                    ];
                }

                usort($products, fn ($a, $b) => ($b['moq_progress'] ?? -1) <=> ($a['moq_progress'] ?? -1));

                $suppliers[] = [
                    'supplier_id'    => $supplierRow->supplier_id,
                    'code'           => $supplierRow->supplier_code,
                    'slug'           => $supplierRow->supplier_slug,
                    'estimated_value' => round($supplierValue, 2),
                    'products'       => $products,
                ];
            }

            usort($suppliers, function ($a, $b) {
                $aMax = collect($a['products'])->max('moq_progress') ?? -1;
                $bMax = collect($b['products'])->max('moq_progress') ?? -1;

                return $bMax <=> $aMax;
            });

            $agents[] = [
                'agent_id'   => $agentRow->agent_id,
                'code'       => $agentRow->agent_code,
                'slug'       => $agentRow->agent_slug,
                'suppliers'  => $suppliers,
            ];
        }

        return ['agents' => $agents];
    }

    public function asGroupController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle();
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        $this->scopedAgent = Agent::where('organisation_id', $organisation->id)->first();

        abort_unless($this->scopedAgent, 404);

        return $this->handle($this->scopedAgent);
    }

    public function htmlResponse(array $data, ActionRequest $request): Response
    {
        if (str_starts_with($request->route()->getName(), 'grp.org.')) {
            return Inertia::render(
                'Procurement/ShoppingListBoard',
                [
                    'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                    'title'       => __('Shopping list board'),
                    'pageHead'    => [
                        'icon'  => ['icon' => ['fal', 'fa-boxes'], 'title' => __('Shopping list board')],
                        'title' => __('Shopping list board'),
                    ],
                    'agent'       => $this->scopedAgent ? [
                        'id'   => $this->scopedAgent->id,
                        'code' => $this->scopedAgent->code,
                        'slug' => $this->scopedAgent->slug,
                    ] : null,
                    'agents'      => $data['agents'],
                ]
            );
        }

        return Inertia::render(
            'SupplyChain/ShoppingListBoard',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Shopping list board'),
                'pageHead'    => [
                    'icon'  => ['icon' => ['fal', 'fa-boxes'], 'title' => __('Shopping list board')],
                    'title' => __('Shopping list board'),
                ],
                'agents'      => $data['agents'],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = [
            [
                'type'   => 'simple',
                'simple' => [
                    'route' => [
                        'name'       => $routeName,
                        'parameters' => $routeParameters,
                    ],
                    'label' => __('Shopping list board'),
                    'icon'  => 'fal fa-boxes',
                ],
            ],
        ];

        if (str_starts_with($routeName, 'grp.org.')) {
            return array_merge(
                ShowProcurementDashboard::make()->getBreadcrumbs($routeParameters),
                $headCrumb
            );
        }

        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            $headCrumb
        );
    }
}
