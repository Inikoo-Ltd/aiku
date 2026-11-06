<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 11:11
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The PO journey board, for the supplier's own page: only the orders sent to it, each with its stages, the ones
 * needing action first. Who placed the order, its value in our currency and every staff link stay out.
 * The supplier's part ends at hand over, so the board stops there: an order handed over is finished for the supplier,
 * whatever happens to it on its way to us.
 */
class IndexSupplierPortalPurchaseOrderStages
{
    use AsObject;

    private const array STATUS_ORDER = ['overdue' => 0, 'at_risk' => 1, 'on_track' => 2, 'completed' => 3];

    private const array SUPPLIER_STAGES = ['submitted', 'deposit_paid', 'in_production', 'ready', 'handed_over'];

    private const array CURRENT_STATES = ['overdue', 'at_risk', 'on_track'];

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
            ->map(fn (array $ribbon) => $this->untilHandOver($ribbon))
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
            'stages'  => $this->stages($journey),
            'ribbons' => $ribbons,
        ];
    }

    /**
     * The stages of one order, for its own page. Orders received long ago are off the board and have none.
     *
     * @return array{stages: array<int, array<string, mixed>>, ribbon: array<string, mixed>}|null
     */
    public function forPurchaseOrder(Supplier $supplier, int $purchaseOrderId): ?array
    {
        $journey = ShowSupplyChainPurchaseOrderJourney::make();
        $ribbon  = $journey->ribbons($supplier->group, $purchaseOrderId, $supplier)->first();

        if (!$ribbon) {
            return null;
        }

        $ribbon = $this->untilHandOver($ribbon);

        return [
            'stages' => $this->stages($journey),
            'ribbon' => [
                ...Arr::only($ribbon, ['current_stage', 'current_label', 'status', 'days_overdue', 'eta']),
                'segments' => array_map(fn (array $segment) => [...$segment, 'mark_column' => null], $ribbon['segments']),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stages(ShowSupplyChainPurchaseOrderJourney $journey): array
    {
        return collect($journey->stages())
            ->filter(fn (array $stage) => in_array($stage['key'], self::SUPPLIER_STAGES, true))
            ->map(fn (array $stage) => [...$stage, 'markable' => false])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $ribbon
     * @return array<string, mixed>
     */
    private function untilHandOver(array $ribbon): array
    {
        $segments = array_values(array_filter($ribbon['segments'], fn (array $segment) => in_array($segment['key'], self::SUPPLIER_STAGES, true)));
        $current  = collect($segments)->first(fn (array $segment) => in_array($segment['state'], self::CURRENT_STATES, true));
        $last     = end($segments);

        return [
            ...$ribbon,
            'segments'      => $segments,
            'current_stage' => $current['key'] ?? null,
            'current_label' => $current ? PurchaseOrderJourneyStageEnum::waitingLabels()[$current['key']] : null,
            'status'        => $current['state'] ?? 'completed',
            'days_overdue'  => $current['days_overdue'] ?? 0,
            'eta'           => $current ? $last['forecast_at'] : ($last['done_at'] ?? $last['planned_at']),
        ];
    }
}
