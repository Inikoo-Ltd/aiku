<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 11:11
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The PO journey board, for the supplier's own page: only the orders sent to it, each with its stages, the ones
 * needing action first. Who placed the order, its value in our currency and every staff link stay out.
 */
class IndexSupplierPortalPurchaseOrderStages
{
    use AsObject;

    private const array STATUS_ORDER = ['overdue' => 0, 'at_risk' => 1, 'on_track' => 2, 'completed' => 3];

    private const array SHARED_FIELDS = [
        'key', 'id', 'reference', 'organisation_code', 'organisation_name', 'agent_name', 'journey', 'type', 'amount',
        'currency_code', 'created_at', 'current_stage', 'current_label', 'status', 'days_overdue', 'eta',
    ];

    /**
     * @return array{stages: array<int, array<string, mixed>>, ribbons: array<int, array<string, mixed>>}
     */
    public function handle(Supplier $supplier): array
    {
        $journey = ShowSupplyChainPurchaseOrderJourney::make();

        $ribbons = $journey->ribbons($supplier->group, supplier: $supplier)
            ->sort(fn (array $a, array $b) => [self::STATUS_ORDER[$a['status']], -$a['days_overdue'], $a['eta'] ?? '', $a['created_at']]
                <=> [self::STATUS_ORDER[$b['status']], -$b['days_overdue'], $b['eta'] ?? '', $b['created_at']])
            ->map(fn (array $ribbon) => [
                ...Arr::only($ribbon, self::SHARED_FIELDS),
                'slug'              => null,
                'supplier_code'     => null,
                'supplier_name'     => null,
                'supplier_company'  => null,
                'supplier_nickname' => null,
                'agent_slug'        => null,
                'supplier_route'    => null,
                'placed_by'         => null,
                'parent_code'       => null,
                'parent_name'       => null,
                'buyer_name'        => null,
                'amount_grp'        => null,
                'route'             => null,
                'mark_route'        => null,
                'segments'          => array_map(fn (array $segment) => [...$segment, 'mark_column' => null], $ribbon['segments']),
            ])
            ->values()
            ->all();

        return [
            'stages'  => array_map(fn (array $stage) => [...$stage, 'markable' => false], $journey->stages()),
            'ribbons' => $ribbons,
        ];
    }
}
