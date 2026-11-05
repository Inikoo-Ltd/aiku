<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\GoodsIn\StockDelivery\StoreStockDeliveryFromPurchaseOrder;
use App\Actions\Procurement\AgentGoodsReceipt\StoreAgentGoodsReceipt;
use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\AgentGoodsReceipt\AgentGoodsReceiptStateEnum;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStageEnum as Stage;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Enums\Procurement\PurchaseOrderTransaction\PurchaseOrderTransactionStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\AgentGoodsReceipt;
use App\Models\Procurement\AgentGoodsReceiptItem;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use App\Actions\SupplyChain\UI\GetPurchaseOrderJourney;
use App\Actions\SupplyChain\UI\ShowSupplyChainPurchaseOrderJourney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Where a purchase order stands in its stages. A delivery stage counts as reached only when every live
 * delivery of the order reached it, dated by the last of them, so the order shows its slowest delivery.
 * In warehouse is reached when the first goods reach the agent warehouse; it is checked and packed once every
 * line is received in full and all of it is checked, or packed. It shows only on orders through an agent that
 * receives goods in its own warehouse, and, as nobody records it by hand, marking a later stage never asks for it.
 * Handed over (Clean Hand Over) means the order is ready to be put on a shipment. It is done when the agent marked it,
 * when every Ready to Roll check is done (RecordPurchaseOrderHandedOverWhenReady records it), when a delivery is
 * dispatched (noting the checks it went without) and, for an agent that sends goods on the next container (cho_mode),
 * when every item is in a container, so that mode does not offer the mark: agents read it as the supplier handing the
 * goods to them (HELP-4292). A date already marked can still be cleared.
 * On an order through an agent, Ready is also reached once every line is in a container, packed first or not: the QC
 * mark is then optional. With the agent warehouse, production done is filled by the goods receipts, so it shows under
 * In warehouse and is never marked by hand.
 * A deposit not required is never asked for, but it is not reached either until a later stage is: a sent order without
 * one stays at Submitted rather than looking as if its deposit was paid (HELP-4315). A deposit owed and passed by a
 * later stage without a payment shows as unpaid, never as done.
 */
class GetPurchaseOrderStages
{
    use AsObject;

    private const array WAREHOUSE_COLUMNS = ['warehouse_arrived_at', 'warehouse_checked_at', 'warehouse_packed_at'];

    private array $warehouseDates = [];

    private array $usesAgentWarehouse = [];

    private array $allOnContainersAt = [];

    public const array SHIPPED_STATES = [
        StockDeliveryStateEnum::DISPATCHED,
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
        StockDeliveryStateEnum::PLACED,
    ];

    private const array RECEIVED_STATES = [
        StockDeliveryStateEnum::RECEIVED,
        StockDeliveryStateEnum::CHECKED,
        StockDeliveryStateEnum::BOOKING_IN,
        StockDeliveryStateEnum::BOOKED_IN,
        StockDeliveryStateEnum::PLACED,
    ];

    /**
     * @return array<string, array{recorded: bool, reached: bool, date: ?Carbon, user_id: ?int, deliveries: ?array{reached: int, total: int}}>
     */
    public function handle(PurchaseOrder $purchaseOrder): array
    {
        $deliveries = $this->liveDeliveries($purchaseOrder);

        $stages = [];
        foreach ($this->stagesOf($purchaseOrder) as $stage) {
            $stages[$stage->value] = match (true) {
                $stage->isDeliveryStage()     => $this->deliveryStage($purchaseOrder, $stage, $deliveries),
                $stage === Stage::IN_WAREHOUSE => [
                    'recorded'   => $this->warehouseDates($purchaseOrder)['warehouse_arrived_at'] !== null,
                    'date'       => $this->warehouseDates($purchaseOrder)['warehouse_arrived_at'],
                    'user_id'    => null,
                    'deliveries' => null,
                ],
                $stage === Stage::READY && $purchaseOrder->qc_passed_at === null && $this->allOnContainersAt($purchaseOrder) !== null => [
                    'recorded'   => true,
                    'date'       => $this->allOnContainersAt($purchaseOrder),
                    'user_id'    => null,
                    'deliveries' => null,
                ],
                $stage === Stage::HANDED_OVER => $this->handedOverStage($purchaseOrder, $deliveries),
                default => [
                    'recorded'   => $purchaseOrder->{$stage->column()} !== null
                        || ($stage === Stage::SUBMITTED && $purchaseOrder->state !== PurchaseOrderStateEnum::IN_PROCESS)
                        || ($stage === Stage::DEPOSIT_PAID && $this->depositNotRequired($purchaseOrder)),
                    'date'       => $purchaseOrder->{$stage->column()},
                    'user_id'    => $purchaseOrder->stageMarkUserId($stage->column()),
                    'deliveries' => null,
                ],
            };
        }

        $laterReached = false;
        foreach (array_reverse(array_keys($stages)) as $key) {
            $laterReached            = $laterReached || ($stages[$key]['recorded'] && !($key === Stage::DEPOSIT_PAID->value && $this->depositNotRequired($purchaseOrder)));
            $stages[$key]['reached'] = $laterReached;
        }

        return $stages;
    }

    /**
     * @return array{recorded: bool, date: ?Carbon, user_id: ?int, deliveries: null}
     */
    private function handedOverStage(PurchaseOrder $purchaseOrder, Collection $deliveries): array
    {
        $dispatched   = $deliveries->filter(fn (StockDelivery $delivery) => in_array($delivery->state, self::SHIPPED_STATES));
        $isDispatched = $deliveries->isEmpty()
            ? in_array(StockDeliveryStateEnum::tryFrom((string) $purchaseOrder->delivery_state?->value), self::SHIPPED_STATES)
            : $dispatched->isNotEmpty();
        $dispatchedAt = $dispatched->pluck('dispatched_at')->filter()->min();
        $onContainers = $purchaseOrder->choMode() === 'next_container' ? $this->allOnContainersAt($purchaseOrder) : null;

        $date = $onContainers
            ? collect([$purchaseOrder->handed_over_at, $onContainers, $dispatchedAt])->filter()->min()
            : ($purchaseOrder->handed_over_at ?? $dispatchedAt);

        return [
            'recorded'   => $purchaseOrder->handed_over_at !== null || $isDispatched || $onContainers !== null,
            'date'       => $date,
            'user_id'    => $purchaseOrder->stageMarkUserId('handed_over_at'),
            'deliveries' => null,
        ];
    }

    /**
     * What marking this date passes over without it being recorded: earlier stages, and the stage's gate. Clean Hand
     * Over does not ask for the earlier stages: agents record it whether or not the buyer recorded the deposit or QC.
     *
     * @return string[]
     */
    public function skippedBy(PurchaseOrder $purchaseOrder, string $field, array $stages): array
    {
        $stage   = Stage::ofField($field);
        $skipped = [];

        if ($field === $stage->column() && $stage !== Stage::HANDED_OVER) {
            foreach (Stage::cases() as $earlier) {
                if ($earlier->position() >= $stage->position()) {
                    break;
                }
                if ($earlier !== Stage::IN_WAREHOUSE && isset($stages[$earlier->value]) && !$stages[$earlier->value]['recorded']) {
                    $skipped[] = Stage::labels()[$earlier->value];
                }
            }
        }

        foreach (Stage::FIELD_GATES[$field] ?? [] as $gate) {
            if ($gate instanceof PurchaseOrderAttachmentScopeEnum) {
                if (!$this->evidenceCount($purchaseOrder, $gate)) {
                    $skipped[] = PurchaseOrderAttachmentScopeEnum::labels()[$gate->value];
                }
            } elseif ($purchaseOrder->{$gate} === null) {
                $skipped[] = Stage::fieldLabels()[$gate];
            }
        }

        if ($field === 'handed_over_at' && $purchaseOrder->isWaitingForSample()) {
            $skipped[] = Stage::fieldLabels()['sample_approved_at'];
        }

        if ($field === 'qc_passed_at') {
            array_push($skipped, ...GetPurchaseOrderCompliance::make()->missingSupplierOnly($purchaseOrder));
        }

        if ($field === 'compliance_complete_at') {
            array_push($skipped, ...GetPurchaseOrderCompliance::make()->missingRequired($purchaseOrder));
        }

        return $skipped;
    }

    /**
     * No deposit is owed when the buyer set it to none, or, on an order straight to a supplier, when none was set.
     */
    public function depositNotRequired(PurchaseOrder $purchaseOrder): bool
    {
        if ($purchaseOrder->deposit_paid_at !== null) {
            return false;
        }

        return $purchaseOrder->deposit_amount === null
            ? !$purchaseOrder->isAgentOrder()
            : (float) $purchaseOrder->deposit_amount == 0;
    }

    public function evidenceCount(PurchaseOrder $purchaseOrder, PurchaseOrderAttachmentScopeEnum $scope): int
    {
        return $purchaseOrder->attachments()->wherePivot('scope', $scope->value)->count();
    }

    /**
     * @param string[] $markableFields
     */
    public function forUi(PurchaseOrder $purchaseOrder, array $markableFields): array
    {
        $stages     = $this->handle($purchaseOrder);
        $deliveries = $this->liveDeliveries($purchaseOrder);
        $fieldLabels = Stage::fieldLabels();

        $steps = [];
        foreach ($this->stagesOf($purchaseOrder) as $stage) {
            foreach ($this->stepsOf($purchaseOrder, $stage) as $column) {
                $steps[$stage->value][] = $this->step($purchaseOrder, $deliveries, $column);
            }
        }

        $userIds = collect($stages)->pluck('user_id')
            ->merge(collect($steps)->flatten(1)->pluck('user_id'))
            ->filter()->unique();
        $names = $userIds->isEmpty() ? collect() : User::whereIn('id', $userIds)->get(['id', 'contact_name', 'username'])
            ->mapWithKeys(fn (User $user) => [$user->id => $user->contact_name ?: $user->username]);

        $agentTexts = $purchaseOrder->isAgentOrder() && $purchaseOrder->agent ? $purchaseOrder->agent->poJourney()['stages'] : [];
        $current  = collect($stages)->filter(fn (array $stage) => $stage['reached'])->keys()->last();
        $expected = $this->expectedDates($purchaseOrder);
        $today    = now()->startOfDay();
        $confirmsOrder = MarkPurchaseOrderStage::confirmsOrder($purchaseOrder, $today) && !$purchaseOrder->isWaitingForLineAnswers();

        return collect($this->stagesOf($purchaseOrder))->values()->map(fn (Stage $stage, int $index) => [
            'key'         => $stage->value,
            'number'      => $index + 1,
            'phase'       => match (true) {
                in_array($stage, [Stage::ARRIVED, Stage::RECEIVED]) => 'delivery',
                $purchaseOrder->isAgentOrder() => 'agent',
                default => 'supplier',
            },
            'label'       => $agentTexts[$stage->value]['label'] ?? Stage::labels()[$stage->value],
            'description' => $agentTexts[$stage->value]['description'] ?? Stage::descriptions()[$stage->value],
            'reached'     => $stages[$stage->value]['reached'],
            'current'     => $stage->value === $current,
            'date'        => $stages[$stage->value]['date'],
            'by'          => $names->get($stages[$stage->value]['user_id']),
            'expected'    => $expected[$stage->value]['date'] ?? null,
            'expected_note' => $expected[$stage->value]['note'] ?? null,
            'days_late'   => !$stages[$stage->value]['reached'] && isset($expected[$stage->value]) && Carbon::parse($expected[$stage->value]['date'])->lt($today)
                ? (int) Carbon::parse($expected[$stage->value]['date'])->diffInDays($today)
                : 0,
            'deliveries'  => $stages[$stage->value]['deliveries'],
            'note'        => match (true) {
                $stage === Stage::SUBMITTED && (bool) $purchaseOrder->agent_reference => __('Agent\'s number: :number', ['number' => $purchaseOrder->agent_reference]),
                $stage === Stage::READY && $purchaseOrder->qc_passed_at === null && $this->allOnContainersAt($purchaseOrder) !== null => __('Every item is in a container, QC not recorded'),
                $stage === Stage::HANDED_OVER && RecordPurchaseOrderHandedOverWhenReady::wasRecordedAutomatically($purchaseOrder) => __('Recorded when every Ready to Roll check was done'),
                $stage === Stage::HANDED_OVER && $purchaseOrder->handed_over_at === null && $stages[$stage->value]['recorded'] => $purchaseOrder->choMode() === 'next_container'
                    ? __('Every item is in a container')
                    : $this->dispatchedWithoutHandOverNote($purchaseOrder, $deliveries),
                default => null,
            },
            'checks'      => $stage === Stage::READY ? $this->readyChecks($purchaseOrder) : null,
            'not_required' => $stage === Stage::DEPOSIT_PAID && $this->depositNotRequired($purchaseOrder),
            'unpaid'       => $stage === Stage::DEPOSIT_PAID && $stages[$stage->value]['reached'] && $purchaseOrder->deposit_paid_at === null && !$this->depositNotRequired($purchaseOrder),
            'steps'       => collect($steps[$stage->value] ?? [])->map(fn (array $step) => [
                'key'   => $step['key'],
                'label' => $step['label'],
                'date'  => $step['date'],
                'by'    => $names->get($step['user_id']),
                'note'  => $this->stepNote($step, $steps[$stage->value] ?? []),
            ])->all(),
            'evidence'    => collect($stage->evidence())->map(fn (PurchaseOrderAttachmentScopeEnum $scope) => [
                'scope' => $scope->value,
                'label' => PurchaseOrderAttachmentScopeEnum::labels()[$scope->value],
                'count' => $this->evidenceCount($purchaseOrder, $scope),
            ])->all(),
            'marks'       => collect(Stage::MARKABLE_FIELDS)
                ->filter(
                    fn (Stage $fieldStage, string $field) => $fieldStage === $stage && in_array($field, $markableFields)
                        && !($field === 'produced_at' && $this->usesAgentWarehouse($purchaseOrder))
                        && !($field === 'handed_over_at' && $this->hidesHandedOver($purchaseOrder))
                )
                ->keys()
                ->map(fn (string $field) => [
                    'field'   => $field,
                    'label'   => $confirmsOrder && $field === 'production_started_at' && $purchaseOrder->{$field} === null ? __('Confirm and Start Production') : $fieldLabels[$field],
                    'confirms_order' => $confirmsOrder && $purchaseOrder->{$field} === null,
                    'date'    => $purchaseOrder->{$field},
                    'skipped' => $this->skippedBy($purchaseOrder, $field, $stages),
                    'planned' => in_array($field, Stage::PLANNED_FIELDS),
                    'suggested' => $field === 'approved_ready_at' ? $purchaseOrder->proposed_ready_at : null,
                ])->values()->all(),
        ])->all();
    }

    /**
     * Each stage's expected date, from the plan the PO journey board draws for the order: the agent's or
     * supplier's lead time stretched over the stages, and the approved ready date for QC. The board works the
     * shipping date back from the arrival estimate, so a stage is never expected before the one ahead of it.
     * Each date carries a note saying where it came from, so a buyer can tell a plan from a promise.
     *
     * @return array<string, array{date: string, note: string}>
     */
    public function expectedDates(PurchaseOrder $purchaseOrder): array
    {
        $ribbon = ShowSupplyChainPurchaseOrderJourney::make()->ribbons($purchaseOrder->group, $purchaseOrder->id)->first();
        if (!$ribbon) {
            return [];
        }

        $segments = collect($ribbon['segments'])->keyBy('key');
        $expected = [];
        $previous = null;

        foreach (Stage::cases() as $stage) {
            $segment = $segments->get($stage->value);
            $date    = $segment ? ($segment['done_at'] ?: $segment['planned_at']) : null;
            if (!$date) {
                continue;
            }

            if ($previous && $previous['date'] > $date) {
                $expected[$stage->value] = [
                    'date' => $previous['date'],
                    'note' => __('The plan puts it before :stage, so it takes that stage\'s date.', ['stage' => Stage::labels()[$previous['stage']]]),
                ];
                continue;
            }

            $expected[$stage->value] = [
                'date' => $date,
                'note' => $segment['done_at'] ? __('The date the PO journey board recorded for this stage.') : $this->expectedNote($segment['planned_from'], $ribbon),
            ];
            $previous = ['stage' => $stage->value, 'date' => $date];
        }

        return $expected;
    }

    /**
     * @param array{basis: ?string, days: int, after: ?string} $plannedFrom
     */
    private function expectedNote(array $plannedFrom, array $ribbon): string
    {
        $replacements = [
            'days'  => $plannedFrom['days'],
            'stage' => $plannedFrom['after'] ? (Stage::labels()[$plannedFrom['after']] ?? $plannedFrom['after']) : __('the order was created'),
        ];

        return match ($plannedFrom['basis']) {
            'lead_time'            => __(':days days after :stage: this stage\'s share of the :lead-day lead time set on :parent.', [...$replacements, 'lead' => $ribbon['lead_time'], 'parent' => $ribbon['parent_name']]),
            'stage_days'           => __(':days days after :stage, as set in the journey stage days of :parent.', [...$replacements, 'parent' => $ribbon['parent_name']]),
            'production_time'      => __(':days days after :stage, the production time set on :supplier.', [...$replacements, 'supplier' => $ribbon['supplier_name'] ?? $ribbon['parent_name']]),
            'approved_ready'       => __('The approved ready date.'),
            'estimated_production' => __('The estimated production date given for this order.'),
            'after_ready'          => __(':days days after the approved ready date, to hand the goods over.', ['days' => GetPurchaseOrderJourney::HANDOVER_DAYS_AFTER_READY]),
            'arrival_estimate'     => __('The estimated arrival date of the delivery, or of the order while it has no delivery.'),
            'before_arrival'       => __(':days days before the estimated arrival, worked back from it.', $replacements),
            default                => __(':days days after :stage, the standard length of this stage.', $replacements),
        };
    }

    /**
     * How long the goods took from placed in stock to online on the website.
     */
    private function stepNote(array $step, array $stageSteps): ?string
    {
        if ($step['key'] === 'dispatched_at' && isset($step['count']) && $step['count']['reached'] < $step['count']['total']) {
            return __(':reached of :total dispatched', $step['count']);
        }

        $placedAt = collect($stageSteps)->firstWhere('key', 'placed_at')['date'] ?? null;
        if ($step['key'] !== 'online_at' || !$step['date'] || !$placedAt) {
            return null;
        }

        $days = (int) Carbon::parse($placedAt)->startOfDay()->diffInDays(Carbon::parse($step['date'])->startOfDay());

        return trans_choice(':count day after placed|:count days after placed', $days, ['count' => $days]);
    }

    /**
     * @return Stage[]
     */
    private function stagesOf(PurchaseOrder $purchaseOrder): array
    {
        return array_values(array_filter(Stage::cases(), fn (Stage $stage) => $stage !== Stage::IN_WAREHOUSE || $this->usesAgentWarehouse($purchaseOrder)));
    }

    /**
     * Set on the agent, or seen from the goods receipts of an agent that records them without the setting.
     */
    public function usesAgentWarehouse(PurchaseOrder $purchaseOrder): bool
    {
        return $this->usesAgentWarehouse[$purchaseOrder->id]
            ??= $purchaseOrder->isAgentOrder() && (
                (bool) $purchaseOrder->agent?->receivesGoodsInOwnWarehouse()
                || AgentGoodsReceipt::where('agent_id', $purchaseOrder->agent_id)->where('state', AgentGoodsReceiptStateEnum::RECEIVED)->exists()
            );
    }

    /**
     * Clean Hand Over not recorded but passed because goods were dispatched: which deliveries took them, and which
     * Ready to Roll checks were still missing.
     */
    private function dispatchedWithoutHandOverNote(PurchaseOrder $purchaseOrder, Collection $deliveries): string
    {
        $references = $deliveries
            ->filter(fn (StockDelivery $delivery) => in_array($delivery->state, self::SHIPPED_STATES))
            ->pluck('reference')
            ->implode(', ');
        $missing = collect($this->readyChecks($purchaseOrder) ?? [])
            ->reject(fn (array $check) => $check['done'])
            ->pluck('label')
            ->implode(', ');

        return match (true) {
            $references !== '' && $missing !== '' => __('Not marked: dispatched in :deliveries without :checks', ['deliveries' => $references, 'checks' => $missing]),
            $references !== ''                    => __('Not marked: dispatched in :deliveries', ['deliveries' => $references]),
            $missing !== ''                       => __('Not marked: dispatched without :checks', ['checks' => $missing]),
            default                               => __('Not marked: the goods were dispatched'),
        };
    }

    private function hidesHandedOver(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->handed_over_at === null && $purchaseOrder->choMode() === 'next_container';
    }

    /**
     * The checks the agent shows for Ready to Roll: done or not, with the date when done. Packing is the warehouse
     * packing for an agent warehouse, otherwise the production done date. With an agent warehouse QC is also done once
     * every item received is checked, when nobody recorded the QC itself.
     *
     * @return array<int, array{key: string, label: string, done: bool, date: ?Carbon}>|null
     */
    public function readyChecks(PurchaseOrder $purchaseOrder): ?array
    {
        if (!$purchaseOrder->isAgentOrder() || !$purchaseOrder->agent) {
            return null;
        }

        $labels = Stage::readyCheckLabels();

        return collect($purchaseOrder->agent->poJourney()['ready_checks'])->map(function (string $key) use ($purchaseOrder, $labels) {
            $date = match (true) {
                $key === 'packing' && $this->usesAgentWarehouse($purchaseOrder) => $this->warehouseDates($purchaseOrder)['warehouse_packed_at'],
                $key === 'qc' && $this->usesAgentWarehouse($purchaseOrder)      => $purchaseOrder->qc_passed_at ?? $this->warehouseDates($purchaseOrder)['warehouse_checked_at'],
                default                                                         => $purchaseOrder->{Stage::READY_CHECK_FIELDS[$key]},
            };

            return ['key' => $key, 'label' => $labels[$key], 'done' => $date !== null, 'date' => $date];
        })->all();
    }

    /**
     * @return string[]
     */
    private function stepsOf(PurchaseOrder $purchaseOrder, Stage $stage): array
    {
        if (!$this->usesAgentWarehouse($purchaseOrder)) {
            return $stage->steps();
        }

        return match ($stage) {
            Stage::IN_WAREHOUSE => ['warehouse_arrived_at', 'produced_at', 'warehouse_checked_at', 'warehouse_packed_at'],
            Stage::READY        => array_values(array_diff($stage->steps(), ['produced_at'])),
            default             => $stage->steps(),
        };
    }

    /**
     * @return array{warehouse_arrived_at: ?Carbon, warehouse_checked_at: ?Carbon, warehouse_packed_at: ?Carbon}
     */
    private function warehouseDates(PurchaseOrder $purchaseOrder): array
    {
        return $this->warehouseDates[$purchaseOrder->id] ??= $this->computeWarehouseDates($purchaseOrder);
    }

    /**
     * Packed has no date of its own: the warehouse stock's last change is when it was packed, as a check can not
     * change it afterwards.
     */
    private function computeWarehouseDates(PurchaseOrder $purchaseOrder): array
    {
        $items = AgentGoodsReceiptItem::where('purchase_order_id', $purchaseOrder->id)
            ->whereHas('agentGoodsReceipt', fn ($query) => $query->where('state', AgentGoodsReceiptStateEnum::RECEIVED))
            ->with(['agentGoodsReceipt:id,received_at', 'agentWarehouseStock:id,state,quantity,updated_at'])
            ->get();

        $dates = array_fill_keys(self::WAREHOUSE_COLUMNS, null);
        if ($items->isEmpty()) {
            return $dates;
        }

        $dates['warehouse_arrived_at'] = Carbon::parse($items->min(fn (AgentGoodsReceiptItem $item) => $item->agentGoodsReceipt->received_at));
        if (!StoreAgentGoodsReceipt::make()->isReceivedInFull($purchaseOrder)) {
            return $dates;
        }

        if ($items->every(fn (AgentGoodsReceiptItem $item) => $item->checked_at !== null)) {
            $dates['warehouse_checked_at'] = $items->max('checked_at');
        }

        $stocks = $items->pluck('agentWarehouseStock')->filter(fn ($stock) => $stock && (float) $stock->quantity > 0);
        if ($stocks->isNotEmpty() && $stocks->every(fn ($stock) => $stock->state !== AgentWarehouseStockStateEnum::RECEIVED)) {
            $dates['warehouse_packed_at'] = $stocks->max('updated_at');
        }

        return $dates;
    }

    /**
     * When the last line of an order through an agent was fully put in a container, or null while any line still
     * waits for one. Cancelled lines and lines never sent do not count.
     */
    public function allOnContainersAt(PurchaseOrder $purchaseOrder): ?Carbon
    {
        if (array_key_exists($purchaseOrder->id, $this->allOnContainersAt)) {
            return $this->allOnContainersAt[$purchaseOrder->id];
        }

        if (!$purchaseOrder->isAgentOrder()) {
            return $this->allOnContainersAt[$purchaseOrder->id] = null;
        }

        $lastOnDelivery = StoreStockDeliveryFromPurchaseOrder::liveDeliveryItemsOfTransaction(DB::query())
            ->selectRaw('max(stock_delivery_items.created_at)');

        $lines = StoreStockDeliveryFromPurchaseOrder::withQuantityAwaitingDelivery(
            $purchaseOrder->purchaseOrderTransactions()
                ->whereNotNull('supplier_product_id')
                ->whereIn('state', [PurchaseOrderTransactionStateEnum::SUBMITTED, PurchaseOrderTransactionStateEnum::CONFIRMED, PurchaseOrderTransactionStateEnum::SETTLED])
        )->selectSub($lastOnDelivery, 'last_on_delivery_at')->get();

        $allOnContainers = $lines->isNotEmpty()
            && $lines->every(fn ($line) => (float) $line->quantity_awaiting_delivery <= StoreStockDeliveryFromPurchaseOrder::QUANTITY_TOLERANCE);

        return $this->allOnContainersAt[$purchaseOrder->id] = $allOnContainers
            ? Carbon::parse($lines->max('last_on_delivery_at'))
            : null;
    }

    private function liveDeliveries(PurchaseOrder $purchaseOrder): Collection
    {
        return $purchaseOrder->stockDeliveries()
            ->whereNotIn('stock_deliveries.state', [StockDeliveryStateEnum::CANCELLED, StockDeliveryStateEnum::NOT_RECEIVED])
            ->get();
    }

    /**
     * Without a live delivery the order's own delivery state stands, as on the PO journey board, and its settled
     * date is the best date known for the goods arriving.
     */
    private function deliveryStage(PurchaseOrder $purchaseOrder, Stage $stage, Collection $deliveries): array
    {
        if ($deliveries->isEmpty()) {
            $state   = StockDeliveryStateEnum::tryFrom((string) $purchaseOrder->delivery_state?->value);
            $reached = $state && in_array($state, self::RECEIVED_STATES);

            return [
                'recorded'   => $reached,
                'date'       => $reached && $stage === Stage::RECEIVED ? $purchaseOrder->settled_at : null,
                'user_id'    => null,
                'deliveries' => ['reached' => 0, 'total' => 0],
            ];
        }

        $reached = $deliveries->filter(fn (StockDelivery $delivery) => $this->deliveryReached($delivery, $stage));
        $all     = $reached->count() === $deliveries->count();

        return [
            'recorded'   => $all,
            'date'       => $all ? $reached->pluck($stage->column())->filter()->max() : null,
            'user_id'    => $all && $deliveries->count() === 1 ? $deliveries->first()->stageMarkUserId($stage->column()) : null,
            'deliveries' => ['reached' => $reached->count(), 'total' => $deliveries->count()],
        ];
    }

    private function deliveryReached(StockDelivery $delivery, Stage $stage): bool
    {
        return match ($stage) {
            Stage::ARRIVED  => $delivery->arrived_at !== null || in_array($delivery->state, self::RECEIVED_STATES),
            Stage::RECEIVED => in_array($delivery->state, self::RECEIVED_STATES),
            default         => false,
        };
    }

    private function step(PurchaseOrder $purchaseOrder, Collection $deliveries, string $column): array
    {
        $step = ['key' => $column, 'label' => Stage::fieldLabels()[$column], 'date' => null, 'user_id' => null];

        if (in_array($column, self::WAREHOUSE_COLUMNS)) {
            return [...$step, 'date' => $this->warehouseDates($purchaseOrder)[$column]];
        }

        if (in_array($column, [...$purchaseOrder->stageMarkColumns(), 'online_at'])) {
            return [...$step, 'date' => $purchaseOrder->{$column}, 'user_id' => $purchaseOrder->stageMarkUserId($column)];
        }

        $dates = $deliveries->pluck($column);
        if ($column === 'dispatched_at' && $deliveries->count() > 1) {
            $step['count'] = ['reached' => $dates->filter()->count(), 'total' => $deliveries->count()];
        }
        if ($dates->isEmpty() || $dates->contains(null)) {
            return $step;
        }

        return [
            ...$step,
            'date'    => $dates->max(),
            'user_id' => $deliveries->count() === 1 ? $deliveries->first()->stageMarkUserId($column) : null,
        ];
    }
}
