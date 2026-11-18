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

        $agentNames = collect($ribbons)->pluck('agent_name');

        return [
            'stages'  => $this->stages($journey, match (true) {
                $agentNames->isEmpty()         => null,
                $agentNames->every(fn ($name) => $name !== null) => true,
                $agentNames->every(fn ($name) => $name === null) => false,
                default                        => null,
            }),
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
            'stages' => $this->stages($journey, ($ribbon['agent_name'] ?? null) !== null),
            'ribbon' => [
                ...Arr::only($ribbon, ['current_stage', 'current_label', 'status', 'days_overdue', 'eta']),
                'segments' => array_map(fn (array $segment) => [...$segment, 'mark_column' => null], $ribbon['segments']),
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stages(ShowSupplyChainPurchaseOrderJourney $journey, ?bool $throughAgent = null): array
    {
        return collect($journey->stages())
            ->filter(fn (array $stage) => in_array($stage['key'], self::SUPPLIER_STAGES, true))
            ->map(fn (array $stage) => [
                ...$stage,
                'markable' => false,
                'note'     => $stage['key'] === 'handed_over' ? $this->cleanHandOverNote($throughAgent) : null,
            ])
            ->values()
            ->all();
    }

    /**
     * What the CHO date means to the supplier: the day it hands the finished stock to the agent, or the day it
     * dispatches the order to AW when there is no agent. A list of orders that may mix both says both.
     */
    private function cleanHandOverNote(?bool $throughAgent): string
    {
        return match ($throughAgent) {
            true    => __('CHO date = Clean Hand Over: the day you hand over the finished stock to the AW Group agent (or that agent\'s shipping agent).'),
            false   => __('CHO date = Clean Hand Over: the day you dispatch this order to AW Group.'),
            default => __('CHO date = Clean Hand Over: the day you hand over the finished stock to the AW Group agent (or that agent\'s shipping agent). If there is no agent, it is the day you dispatch the order to AW Group.'),
        };
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
