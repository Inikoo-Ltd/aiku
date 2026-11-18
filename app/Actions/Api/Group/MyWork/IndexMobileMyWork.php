<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-14h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\MyWork;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileMyWork
{
    use AsController;
    use WithMobileMyWork;

    public const int MAX_ROWS = 100;

    /**
     * @return array{data: array<int, array<string, mixed>>, counts: array{total: int, delivery_notes: int, picking_sessions: int, returns: int, pallet_returns: int}}
     */
    public function handle(Warehouse $warehouse, User $user): array
    {
        $visibleSources = $this->visibleSources($user, $warehouse);

        $entries = collect();
        foreach (array_keys(self::SOURCE_KINDS) as $source) {
            if ($visibleSources[$source]) {
                $entries = $entries->concat($this->sourceEntries($source, $warehouse, $user));
            }
        }

        return [
            'data'   => $entries
                ->sort(fn (array $entry, array $other) => $entry['sort'] <=> $other['sort'])
                ->take(self::MAX_ROWS)
                ->pluck('row')
                ->values()
                ->all(),
            'counts' => $this->myWorkCounts($warehouse, $user, $visibleSources),
        ];
    }

    /**
     * @return Collection<int, array{sort: array<int, int>, row: array<string, mixed>}>
     */
    private function sourceEntries(string $source, Warehouse $warehouse, User $user): Collection
    {
        $kind  = self::SOURCE_KINDS[$source];
        $query = $this->orderedForMyWork($this->myWorkQuery($source, $warehouse, $user), $kind);

        $rows = match ($source) {
            'delivery_notes'   => $this->deliveryNoteRows($query),
            'picking_sessions' => $this->pickingSessionRows($query),
            'returns'          => $this->returnRows($query),
            'pallet_returns'   => $this->palletReturnRows($query),
        };

        $kindOrder = array_search($kind, array_values(self::SOURCE_KINDS), true);

        return $rows->map(fn (array $row) => [
            'sort' => [
                $row['step'] === 'waiting' ? 0 : 1,
                $row['since'] ? Carbon::parse($row['since'])->getTimestamp() : PHP_INT_MAX,
                $kindOrder,
                $row['id'],
            ],
            'row'  => $row,
        ]);
    }

    private function orderedForMyWork(Builder $query, string $kind): Builder
    {
        $table          = $query->getModel()->getTable();
        $stateStartedAt = $this->stateStartedAtSql($kind, $table);

        return $query
            ->selectRaw("$stateStartedAt as my_work_since")
            ->orderByRaw($this->waitingFirstSql($kind, $table))
            ->orderByRaw("$stateStartedAt asc")
            ->orderBy("$table.id")
            ->limit(self::MAX_ROWS);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function deliveryNoteRows(Builder $query): Collection
    {
        return $query
            ->addSelect(['delivery_notes.id', 'delivery_notes.reference', 'delivery_notes.state', 'delivery_notes.number_items', 'delivery_notes.customer_id'])
            ->with(['customer:id,name', 'trolleys', 'pickingSessionTrolleys'])
            ->get()
            ->map(fn (DeliveryNote $deliveryNote) => $this->myWorkRow(
                kind: 'delivery_note',
                id: $deliveryNote->id,
                reference: $deliveryNote->reference,
                state: $deliveryNote->state->value,
                stateLabel: DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
                typeGroup: null,
                items: (int) $deliveryNote->number_items,
                customer: $deliveryNote->customer?->name,
                trolleys: array_column($deliveryNote->trolleysInUseForDisplay(), 'name'),
                since: $deliveryNote->getAttribute('my_work_since'),
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function pickingSessionRows(Builder $query): Collection
    {
        return $query
            ->addSelect(['picking_sessions.id', 'picking_sessions.reference', 'picking_sessions.state', 'picking_sessions.number_delivery_notes'])
            ->with(['trolleys' => fn ($trolleys) => $trolleys->orderBy('name')])
            ->get()
            ->map(fn (PickingSession $pickingSession) => $this->myWorkRow(
                kind: 'picking_session',
                id: $pickingSession->id,
                reference: $pickingSession->reference,
                state: $pickingSession->state->value,
                stateLabel: $pickingSession->state === PickingSessionStateEnum::HANDLING ? __('Picking') : PickingSessionStateEnum::labels()[$pickingSession->state->value],
                typeGroup: null,
                items: (int) $pickingSession->number_delivery_notes,
                customer: null,
                trolleys: $pickingSession->trolleys->pluck('name')->values()->all(),
                since: $pickingSession->getAttribute('my_work_since'),
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function returnRows(Builder $query): Collection
    {
        return $query
            ->addSelect(['return_delivery_notes.id', 'return_delivery_notes.reference', 'return_delivery_notes.state', 'return_delivery_notes.customer_id'])
            ->withCount('returnDeliveryNoteItem as number_items')
            ->with('customer:id,name')
            ->get()
            ->map(fn (ReturnDeliveryNote $returnDeliveryNote) => $this->myWorkRow(
                kind: 'return',
                id: $returnDeliveryNote->id,
                reference: $returnDeliveryNote->reference,
                state: $returnDeliveryNote->state->value,
                stateLabel: ReturnDeliveryNoteStateEnum::labels()[$returnDeliveryNote->state->value],
                typeGroup: null,
                items: (int) $returnDeliveryNote->getAttribute('number_items'),
                customer: $returnDeliveryNote->customer?->name,
                trolleys: [],
                since: $returnDeliveryNote->getAttribute('my_work_since'),
            ));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function palletReturnRows(Builder $query): Collection
    {
        $lines = fn () => PalletReturnItem::query()->whereColumn('pallet_return_items.pallet_return_id', 'pallet_returns.id');

        return $query
            ->addSelect(['pallet_returns.id', 'pallet_returns.reference', 'pallet_returns.state', 'pallet_returns.type', 'pallet_returns.fulfilment_customer_id'])
            ->selectSub($lines()->selectRaw('COUNT(DISTINCT pallet_return_items.pallet_id)'), 'number_pallets')
            ->selectSub($lines()->where('pallet_return_items.type', 'StoredItem')->selectRaw('COUNT(*)'), 'number_stored_item_lines')
            ->with(['fulfilmentCustomer:id,customer_id', 'fulfilmentCustomer.customer:id,name'])
            ->get()
            ->map(function (PalletReturn $palletReturn) {
                $isPallets = $palletReturn->type === PalletReturnTypeEnum::PALLET;

                return $this->myWorkRow(
                    kind: 'pallet_return',
                    id: $palletReturn->id,
                    reference: $palletReturn->reference,
                    state: $palletReturn->state->value,
                    stateLabel: PalletReturnStateEnum::labels()[$palletReturn->state->value],
                    typeGroup: $isPallets ? 'pallets' : 'items',
                    items: (int) $palletReturn->getAttribute($isPallets ? 'number_pallets' : 'number_stored_item_lines'),
                    customer: $palletReturn->fulfilmentCustomer?->customer?->name,
                    trolleys: [],
                    since: $palletReturn->getAttribute('my_work_since'),
                );
            });
    }

    /**
     * @param array<int, string> $trolleys
     * @return array<string, mixed>
     */
    private function myWorkRow(string $kind, int $id, string $reference, string $state, string $stateLabel, ?string $typeGroup, int $items, ?string $customer, array $trolleys, mixed $since): array
    {
        $step = self::STEPS[$kind][$state];

        return [
            'kind'        => $kind,
            'id'          => $id,
            'reference'   => $reference,
            'state'       => $state,
            'state_label' => $stateLabel,
            'step'        => $step,
            'step_label'  => $this->stepLabels()[$step],
            'type_group'  => $typeGroup,
            'items'       => $items,
            'customer'    => $customer,
            'trolleys'    => array_values($trolleys),
            'since'       => blank($since) ? null : Carbon::parse($since)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function stepLabels(): array
    {
        return [
            'to_pick'     => __('To pick'),
            'picking'     => __('Picking'),
            'waiting'     => __('Waiting'),
            'to_pack'     => __('To pack'),
            'packing'     => __('Packing'),
            'to_start'    => __('To start'),
            'returning'   => __('Returning'),
            'to_dispatch' => __('To dispatch'),
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureAuthorisedWarehouse($user, $warehouse);

        return response()->json($this->handle($warehouse, $user));
    }
}
