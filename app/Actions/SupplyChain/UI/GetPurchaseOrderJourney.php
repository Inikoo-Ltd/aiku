<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum as Stage;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPurchaseOrderJourney
{
    use AsObject;

    public const int AT_RISK_DAYS = 3;

    public const int HANDOVER_DAYS_AFTER_READY = 7;

    private const array LEAD_TIME_STAGES = ['deposit_paid', 'production', 'qc', 'clean_handover', 'dispatched', 'in_transit'];

    private const array DISPATCHED_STATES = ['dispatched', 'received', 'checked', 'placed'];

    private const array RECEIVED_STATES = ['received', 'checked', 'placed'];

    /**
     * Stages somebody has to mark (sample, deposit, production, QC, handover) only hold the ribbon up once one of
     * them has been recorded on the order; until then they show their planned dates and the ribbon runs on what
     * the system records by itself, so a stage nobody tracks never turns an order red.
     *
     * @param array{
     *     journey: string,
     *     state: string,
     *     delivery_state: ?string,
     *     created_at: string,
     *     submitted_at?: ?string,
     *     is_npo?: bool,
     *     has_deposit?: bool,
     *     sample_approved_at?: ?string,
     *     deposit_paid_at?: ?string,
     *     produced_at?: ?string,
     *     qc_passed_at?: ?string,
     *     handed_over_at?: ?string,
     *     estimated_production_at?: ?string,
     *     approved_ready_at?: ?string,
     *     estimated_received_at?: ?string,
     *     dispatched_at?: ?string,
     *     received_at?: ?string,
     *     placed_at?: ?string,
     *     sellable_products?: int,
     *     online_products?: int,
     *     online_at?: ?string,
     *     delivery_time?: ?int,
     *     production_waiting_time?: ?int,
     *     stage_days?: array<string, int|string|null>
     * } $facts
     *
     * @return array{segments: array<int, array<string, mixed>>, current_stage: ?string, status: string, days_overdue: int, eta: ?string}
     */
    public function handle(array $facts, ?Carbon $today = null): array
    {
        $today  = ($today ?? now())->copy()->startOfDay();
        $stages = $this->stages($facts);
        $days   = $this->stageDays($facts, array_keys($stages));

        if (!$stages['dispatched']['done'] && !$stages['dispatched']['target'] && $stages['in_transit']['target']) {
            $stages['dispatched']['target'] = Carbon::parse($stages['in_transit']['target'])->subDays($days['in_transit'])->toDateString();
        }

        $keys     = array_keys($stages);
        $lastDone = -1;
        foreach ($keys as $index => $key) {
            if ($stages[$key]['done']) {
                $lastDone = $index;
            }
        }

        $isTracked = collect($stages)->contains(fn (array $stage, string $key) => Stage::from($key)->markColumn() !== null && $stage['done']);

        $labels       = Stage::labels();
        $descriptions = Stage::descriptions();
        $anchor       = Carbon::parse($facts['created_at'])->startOfDay();
        $forecast     = $anchor->copy();
        $segments     = [];
        $current      = null;
        $daysOverdue  = 0;
        $status       = 'completed';

        foreach ($keys as $index => $key) {
            $stage   = $stages[$key];
            $planned = $stage['target']
                ? Carbon::parse($stage['target'])->startOfDay()
                : $anchor->copy()->addDays($days[$key]);
            $doneAt  = $stage['done_at'] ? Carbon::parse($stage['done_at'])->startOfDay() : null;
            $overdue = 0;

            if ($stage['done'] || $index < $lastDone) {
                $state      = $stage['done'] ? 'done' : 'skipped';
                $anchor     = $doneAt ?? $planned->copy()->min($today);
                $forecastAt = $anchor->copy();
            } elseif (!$isTracked && Stage::from($key)->markColumn() !== null) {
                $state      = 'untracked';
                $forecastAt = $planned->copy()->max($forecast->copy()->addDays($days[$key]));
            } elseif ($current === null) {
                $current = $key;
                if ($planned->lt($today)) {
                    $state   = 'overdue';
                    $overdue = (int) $planned->diffInDays($today);
                } elseif ($planned->lte($today->copy()->addDays(self::AT_RISK_DAYS))) {
                    $state = 'at_risk';
                } else {
                    $state = 'on_track';
                }
                $status      = $state;
                $daysOverdue = $overdue;
                $forecastAt  = $planned->copy()->max($today);
            } else {
                $state      = 'future';
                $forecastAt = $planned->copy()->max($forecast->copy()->addDays($days[$key]));
            }

            if (!in_array($state, ['done', 'skipped'])) {
                $anchor = $planned;
            }
            $forecast = $forecastAt;

            $segments[] = [
                'key'          => $key,
                'label'        => $labels[$key],
                'description'  => $descriptions[$key],
                'state'        => $state,
                'done_at'      => $doneAt?->toDateString(),
                'planned_at'   => $planned->toDateString(),
                'forecast_at'  => $forecastAt->toDateString(),
                'behind_plan'  => in_array($state, ['future', 'untracked']) && $planned->lt($today),
                'days_overdue' => $overdue,
                'mark_column'  => Stage::from($key)->markColumn(),
            ];
        }

        $last = end($segments);

        return [
            'segments'      => $segments,
            'current_stage' => $current,
            'status'        => $status,
            'days_overdue'  => $daysOverdue,
            'eta'           => $current ? $last['forecast_at'] : ($last['done_at'] ?? $last['planned_at']),
        ];
    }

    /**
     * @return array<string, array{done: bool, done_at: ?string, target: ?string}>
     */
    private function stages(array $facts): array
    {
        $journey       = $facts['journey'];
        $deliveryState = $facts['delivery_state'] ?? null;
        $isAgent       = $journey === 'agent';

        $isSent = $facts['state'] !== 'in_process';
        $stages = [
            'po_created' => $this->stage($isSent, $isSent ? ($facts['submitted_at'] ?? null) : null),
        ];
        $readyAt = !empty($facts['approved_ready_at']) ? Carbon::parse($facts['approved_ready_at']) : null;

        if (($facts['is_npo'] ?? false) && $journey !== 'partner') {
            $stages['spec_sample'] = $this->stage(false, $facts['sample_approved_at'] ?? null);
        }
        if ($isAgent || ($facts['has_deposit'] ?? false) || !empty($facts['deposit_paid_at'])) {
            $stages['deposit_paid'] = $this->stage(false, $facts['deposit_paid_at'] ?? null);
        }
        if ($isAgent || !empty($facts['produced_at']) || ($journey === 'supplier' && ($facts['production_waiting_time'] ?? 0) > 0)) {
            $stages['production'] = $this->stage(false, $facts['produced_at'] ?? null, $facts['estimated_production_at'] ?? null);
        }
        if ($isAgent || !empty($facts['qc_passed_at'])) {
            $stages['qc'] = $this->stage(false, $facts['qc_passed_at'] ?? null, $readyAt?->toDateString());
        }
        if ($isAgent || !empty($facts['handed_over_at'])) {
            $stages['clean_handover'] = $this->stage(false, $facts['handed_over_at'] ?? null, $readyAt?->copy()->addDays(self::HANDOVER_DAYS_AFTER_READY)->toDateString());
        }

        $stages['dispatched']         = $this->stage(in_array($deliveryState, self::DISPATCHED_STATES), $facts['dispatched_at'] ?? null);
        $stages['in_transit']         = $this->stage(in_array($deliveryState, self::RECEIVED_STATES), $facts['received_at'] ?? null, $facts['estimated_received_at'] ?? null);
        $stages['warehouse_received'] = $this->stage($deliveryState === 'placed', $facts['placed_at'] ?? null);

        $sellable = (int) ($facts['sellable_products'] ?? 0);
        if ($sellable > 0) {
            $allOnline = $deliveryState === 'placed' && (int) ($facts['online_products'] ?? 0) >= $sellable;
            $onlineAt  = $allOnline && !empty($facts['placed_at']) ? max(array_filter([$facts['placed_at'], $facts['online_at'] ?? null])) : null;

            $stages['products_online'] = $this->stage($allOnline, $onlineAt ?: null);
        }

        return $stages;
    }

    /**
     * @return array{done: bool, done_at: ?string, target: ?string}
     */
    private function stage(bool $done, ?string $doneAt, ?string $target = null): array
    {
        return [
            'done'    => $done || $doneAt !== null,
            'done_at' => $doneAt,
            'target'  => $target,
        ];
    }

    /**
     * Days per stage: the agent's own override wins, then the supplier's production days, and the rest are
     * stretched or squeezed to fit the agent's or supplier's promised lead time from submission to arrival.
     *
     * @param array<int, string> $keys
     *
     * @return array<string, int>
     */
    private function stageDays(array $facts, array $keys): array
    {
        $overrides = array_filter($facts['stage_days'] ?? [], fn ($value) => is_numeric($value));
        $days      = [];
        $fixed     = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $overrides)) {
                $days[$key]  = (int) $overrides[$key];
                $fixed[$key] = true;
            } else {
                $days[$key] = Stage::from($key)->defaultDays($facts['journey']);
            }
        }

        $productionDays = (int) ($facts['production_waiting_time'] ?? 0);
        if (isset($days['production']) && !isset($fixed['production']) && $productionDays > 0) {
            $days['production']  = $productionDays;
            $fixed['production'] = true;
        }

        $leadTime   = (int) ($facts['delivery_time'] ?? 0);
        $leadStages = array_values(array_intersect(self::LEAD_TIME_STAGES, $keys));
        $fixedDays  = array_sum(array_map(fn ($key) => isset($fixed[$key]) ? $days[$key] : 0, $leadStages));
        $flexDays   = array_sum(array_map(fn ($key) => isset($fixed[$key]) ? 0 : $days[$key], $leadStages));
        $remaining  = $leadTime - $fixedDays;

        if ($leadTime > 0 && $flexDays > 0 && $remaining > 0) {
            foreach ($leadStages as $key) {
                if (!isset($fixed[$key])) {
                    $days[$key] = max(1, (int) round($days[$key] * $remaining / $flexDays));
                }
            }
        }

        return $days;
    }
}
