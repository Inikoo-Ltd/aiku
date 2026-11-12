<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\UI;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderJourneyStageEnum as Stage;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsObject;

class GetPurchaseOrderJourney
{
    use AsObject;

    public const int AT_RISK_DAYS = 3;

    private const array LEAD_TIME_STAGES = ['deposit_paid', 'in_production', 'ready', 'handed_over', 'arrived', 'received'];

    private const array DISPATCHED_STATES = ['dispatched', 'received', 'checked', 'placed'];

    private const array RECEIVED_STATES = ['received', 'checked', 'placed'];

    /**
     * Stages somebody has to mark (deposit, production start, QC) only hold the ribbon up once one of
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
     *     no_deposit?: bool,
     *     deposit_paid_at?: ?string,
     *     production_started_at?: ?string,
     *     produced_at?: ?string,
     *     qc_passed_at?: ?string,
     *     estimated_production_at?: ?string,
     *     approved_ready_at?: ?string,
     *     estimated_received_at?: ?string,
     *     dispatched_at?: ?string,
     *     arrived?: bool,
     *     arrived_at?: ?string,
     *     received_at?: ?string,
     *     placed_at?: ?string,
     *     sellable_products?: int,
     *     online_products?: int,
     *     online_at?: ?string,
     *     delivery_time?: ?int,
     *     production_waiting_time?: ?int,
     *     stage_days?: array<string, int|string|null>,
     *     stage_texts?: array<string, array{label?: ?string, description?: ?string}>,
     *     cho_mode?: ?string,
     *     handed_over_at?: ?string,
     *     on_containers?: bool,
     *     on_containers_at?: ?string,
     *     ready_checks?: string[],
     *     balance_paid_at?: ?string,
     *     compliance_complete_at?: ?string
     * } $facts
     *
     * @return array{segments: array<int, array<string, mixed>>, current_stage: ?string, status: string, days_overdue: int, eta: ?string, lead_time: int}
     */
    public function handle(array $facts, ?Carbon $today = null): array
    {
        $today  = ($today ?? now())->copy()->startOfDay();
        $stages = $this->stages($facts);
        [$days, $dayBases] = $this->stageDays($facts, array_keys($stages));

        if ($stages['handed_over']['ready_at']) {
            $stages['handed_over']['target'] = $stages['handed_over']['ready_at'];
            $stages['handed_over']['basis']  = 'approved_ready';
            $days['handed_over']             = 0;
        } elseif (!$stages['handed_over']['done'] && $stages['received']['target']) {
            $stages['handed_over']['target'] = Carbon::parse($stages['received']['target'])->subDays($days['received'] + $days['arrived'])->toDateString();
            $stages['handed_over']['basis']  = 'before_arrival';
        }

        $keys     = array_keys($stages);
        $lastDone = -1;
        foreach ($keys as $index => $key) {
            if ($stages[$key]['done']) {
                $lastDone = $index;
            }
        }

        $isTracked = collect($stages)->contains(fn (array $stage, string $key) => Stage::from($key)->isRecordedByPeople() && $stage['done']);

        $labels       = Stage::labels();
        $descriptions = Stage::descriptions();
        foreach ($facts['stage_texts'] ?? [] as $key => $texts) {
            $labels[$key]       = filled($texts['label'] ?? null) ? $texts['label'] : $labels[$key];
            $descriptions[$key] = filled($texts['description'] ?? null) ? $texts['description'] : $descriptions[$key];
        }
        $anchor       = Carbon::parse($facts['created_at'])->startOfDay();
        $forecast     = $anchor->copy();
        $anchorKey    = null;
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
            } elseif (!$isTracked && Stage::from($key)->isRecordedByPeople()) {
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
                'planned_from' => [
                    'basis' => $stage['target'] ? $stage['basis'] : $dayBases[$key],
                    'days'  => $stage['basis'] === 'before_arrival' ? $days['received'] + $days['arrived'] : $days[$key],
                    'after' => $anchorKey,
                ],
                'forecast_at'  => $forecastAt->toDateString(),
                'behind_plan'  => in_array($state, ['future', 'untracked']) && $planned->lt($today),
                'days_overdue' => $overdue,
                'mark_column'  => Stage::from($key)->markColumn(),
                ...($key === 'ready' && isset($facts['ready_checks']) ? ['checks' => $this->readyChecks($facts)] : []),
            ];
            $anchorKey  = $key;
        }

        $last = end($segments);

        return [
            'segments'      => $segments,
            'current_stage' => $current,
            'status'        => $status,
            'days_overdue'  => $daysOverdue,
            'eta'           => $current ? $last['forecast_at'] : ($last['done_at'] ?? $last['planned_at']),
            'lead_time'     => (int) ($facts['delivery_time'] ?? 0),
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
            'submitted' => $this->stage($isSent, $isSent ? ($facts['submitted_at'] ?? null) : null),
        ];
        $readyAt = !empty($facts['approved_ready_at']) ? Carbon::parse($facts['approved_ready_at']) : null;

        if (($isAgent && !($facts['no_deposit'] ?? false)) || ($facts['has_deposit'] ?? false) || !empty($facts['deposit_paid_at'])) {
            $stages['deposit_paid'] = $this->stage(false, $facts['deposit_paid_at'] ?? null);
        }
        if ($isAgent || !empty($facts['production_started_at']) || ($journey === 'supplier' && ($facts['production_waiting_time'] ?? 0) > 0)) {
            $stages['in_production'] = $this->stage(false, $facts['production_started_at'] ?? null);
        }
        if ($isAgent || !empty($facts['qc_passed_at']) || !empty($facts['produced_at']) || !empty($facts['estimated_production_at'])) {
            $stages['ready'] = $this->stage(false, $facts['qc_passed_at'] ?? null, $readyAt?->toDateString() ?? ($facts['estimated_production_at'] ?? null), $readyAt ? 'approved_ready' : 'estimated_production');
        }

        $isReceived = in_array($deliveryState, self::RECEIVED_STATES);

        $stages['handed_over'] = $this->handedOver($facts, in_array($deliveryState, self::DISPATCHED_STATES), $readyAt);
        $stages['arrived']  = $this->stage($isReceived || ($facts['arrived'] ?? false), $facts['arrived_at'] ?? null);
        $stages['received'] = $this->stage($isReceived, $facts['received_at'] ?? null, $facts['estimated_received_at'] ?? null, 'arrival_estimate');

        $sellable = (int) ($facts['sellable_products'] ?? 0);
        if ($sellable > 0) {
            $allOnline = $deliveryState === 'placed' && (int) ($facts['online_products'] ?? 0) >= $sellable;
            $onlineAt  = $allOnline && !empty($facts['placed_at']) ? max(array_filter([$facts['placed_at'], $facts['online_at'] ?? null])) : null;

            $stages['products_online'] = $this->stage($allOnline, $onlineAt ?: null);
        }

        return $stages;
    }

    /**
     * Clean Hand Over: the agent's mark, a dispatched delivery, and, for an agent sending on the next container, every
     * item being in a container. Dispatch implies the hand over happened.
     *
     * @return array{done: bool, done_at: ?string, target: ?string, basis: ?string, ready_at: ?string}
     */
    private function handedOver(array $facts, bool $isDispatched, ?Carbon $readyAt): array
    {
        $onContainers = ($facts['cho_mode'] ?? null) === 'next_container' && ($facts['on_containers'] ?? false);
        $dates        = array_filter([
            $facts['handed_over_at'] ?? null,
            $onContainers ? ($facts['on_containers_at'] ?? null) : null,
            $isDispatched ? ($facts['dispatched_at'] ?? null) : null,
        ]);
        $doneAt       = $onContainers ? ($dates ? min($dates) : null) : ($facts['handed_over_at'] ?? ($isDispatched ? ($facts['dispatched_at'] ?? null) : null));

        return [
            ...$this->stage($isDispatched || $onContainers, $doneAt),
            'ready_at' => $readyAt?->toDateString(),
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, done: bool, date: ?string}>
     */
    private function readyChecks(array $facts): array
    {
        $labels = PurchaseOrderStageEnum::readyCheckLabels();
        $checks = [];

        foreach ($facts['ready_checks'] as $key) {
            $date     = $facts[PurchaseOrderStageEnum::READY_CHECK_FIELDS[$key]] ?? null;
            $checks[] = ['key' => $key, 'label' => $labels[$key], 'done' => $date !== null, 'date' => $date ? Carbon::parse($date)->toDateString() : null];
        }

        return $checks;
    }

    /**
     * @return array{done: bool, done_at: ?string, target: ?string, basis: ?string}
     */
    private function stage(bool $done, ?string $doneAt, ?string $target = null, ?string $basis = null): array
    {
        return [
            'done'    => $done || $doneAt !== null,
            'done_at' => $doneAt,
            'target'  => $target,
            'basis'   => $target ? $basis : null,
        ];
    }

    /**
     * Days per stage: the agent's own override wins, then the supplier's production days, and the rest are
     * stretched or squeezed to fit the agent's or supplier's promised lead time from submission to arrival.
     *
     * @param array<int, string> $keys
     *
     * @return array{0: array<string, int>, 1: array<string, string>} the days of each stage, and what set them
     */
    private function stageDays(array $facts, array $keys): array
    {
        $overrides = array_filter($facts['stage_days'] ?? [], fn ($value) => is_numeric($value));
        $days      = [];
        $fixed     = [];
        $bases     = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $overrides)) {
                $days[$key]  = (int) $overrides[$key];
                $fixed[$key] = true;
                $bases[$key] = 'stage_days';
            } else {
                $days[$key]  = Stage::from($key)->defaultDays($facts['journey']);
                $bases[$key] = 'standard';
            }
        }

        $productionDays = (int) ($facts['production_waiting_time'] ?? 0);
        if (isset($days['ready']) && !isset($fixed['ready']) && $productionDays > 0) {
            $days['ready']  = $productionDays;
            $fixed['ready'] = true;
            $bases['ready'] = 'production_time';
        }

        $leadTime   = (int) ($facts['delivery_time'] ?? 0);
        $leadStages = array_values(array_intersect(self::LEAD_TIME_STAGES, $keys));
        $fixedDays  = array_sum(array_map(fn ($key) => isset($fixed[$key]) ? $days[$key] : 0, $leadStages));
        $flexDays   = array_sum(array_map(fn ($key) => isset($fixed[$key]) ? 0 : $days[$key], $leadStages));
        $remaining  = $leadTime - $fixedDays;

        if ($leadTime > 0 && $flexDays > 0 && $remaining > 0) {
            foreach ($leadStages as $key) {
                if (!isset($fixed[$key])) {
                    $days[$key]  = max(1, (int) round($days[$key] * $remaining / $flexDays));
                    $bases[$key] = 'lead_time';
                }
            }
        }

        return [$days, $bases];
    }
}
