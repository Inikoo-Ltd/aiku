<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\FetchDeliveryNoteItemImage;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsInPickingSessionStateActive;
use App\Actions\Dispatching\PickingSession\PickPickingSessionItemByScan;
use App\Actions\Dispatching\PickingSession\UI\IndexPickingSessions;
use App\Actions\Dispatching\PickingSession\UI\ShowPickingSession;
use App\Actions\Dispatching\PickingSession\UndoWaitingPickingSession;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Enums\Dispatching\PickingSession\PickingSessionTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

trait WithMobilePickingSessions
{
    use WithMobileDeliveryNotes;
    use WithMobileLocationsAccess;
    use WithMobilePickingGuards;

    protected function ensureCanViewPickingSession(PickingSession $pickingSession, User $user): void
    {
        abort_if($pickingSession->type === PickingSessionTypeEnum::FULFILMENT, 404);
        abort_unless($user->authorisedWarehouses()->whereKey($pickingSession->warehouse_id)->exists(), 404);
        abort_unless(IndexPickingSessions::canViewDispatching($user, $pickingSession->warehouse), 403);
    }

    protected function ensureCanWorkPickingSession(PickingSession $pickingSession, User $user): void
    {
        $this->ensureCanViewPickingSession($pickingSession, $user);
        abort_unless($pickingSession->warehouse->canBeWorkedInBy($user), 403, __('Only warehouse staff can work on picking sessions.'));
        $this->ensureNotesNotLockedInAurora($pickingSession->deliveryNotes()->orderBy('delivery_notes.id')->get(), 'delivery_note');
    }

    /**
     * @param Collection<int, DeliveryNote> $deliveryNotes
     */
    protected function ensureNotesNotLockedInAurora(Collection $deliveryNotes, string $errorKey): void
    {
        $lockedDeliveryNote = $deliveryNotes->first(fn (DeliveryNote $deliveryNote) => $deliveryNote->isLockedInAurora());

        if ($lockedDeliveryNote) {
            throw ValidationException::withMessages([
                $errorKey => $lockedDeliveryNote->reference.': '.ShowDeliveryNote::make()->getAuroraNotice($lockedDeliveryNote),
            ]);
        }
    }

    /**
     * @param array<int, PickingSessionStateEnum> $states
     */
    protected function ensurePickingSessionState(PickingSession $pickingSession, array $states): void
    {
        if (!in_array($pickingSession->state, $states, true)) {
            $this->refuseState(__('This can not be done while the picking session is :state.', ['state' => $this->pickingSessionStateLabel($pickingSession->state)]));
        }
    }

    /**
     * @return array<int, PickingSessionStateEnum>
     */
    protected function trolleyChangeStates(): array
    {
        return array_values(array_filter(PickingSessionStateEnum::cases(), fn (PickingSessionStateEnum $state) => $state !== PickingSessionStateEnum::PACKING_FINISHED));
    }

    protected function pickingSessionStateLabel(PickingSessionStateEnum $state): string
    {
        return $state === PickingSessionStateEnum::HANDLING ? __('Picking') : PickingSessionStateEnum::labels()[$state->value];
    }

    /**
     * @return array<int, int>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validatedDeliveryNoteIds(ActionRequest $request): array
    {
        $data = Validator::make($request->all(), [
            'delivery_note_ids'   => ['required', 'array', 'min:1', 'max:500'],
            'delivery_note_ids.*' => ['required', 'integer'],
        ])->validate();

        return collect($data['delivery_note_ids'])->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * @param array<int, int> $deliveryNoteIds
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function ensurePickingSessionCandidates(Warehouse $warehouse, User $user, array $deliveryNoteIds, bool $checkChannel): void
    {
        $rows = $this->withRowColumns($this->deliveryNotesQuery($warehouse, 'all', 'all', null, $user))
            ->whereIn('delivery_notes.id', $deliveryNoteIds)
            ->get()
            ->keyBy('id');

        $refused = collect($deliveryNoteIds)
            ->map(function (int $deliveryNoteId) use ($rows, $checkChannel) {
                $row = $rows->get($deliveryNoteId);

                if (!$row) {
                    return '#'.$deliveryNoteId;
                }

                return $this->isPickingSessionCandidate($row, $checkChannel) ? null : $row->reference;
            })
            ->filter()
            ->values();

        if ($refused->isNotEmpty()) {
            throw ValidationException::withMessages([
                'delivery_note_ids' => __('These delivery notes can not go in a picking session: :references.', ['references' => $refused->implode(', ')]),
            ]);
        }
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    protected function personPayload(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        return [
            'id'   => $user->id,
            'name' => $user->contact_name ?? $user->username,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pickingSessionRow(PickingSession $pickingSession): array
    {
        return [
            'id'                    => $pickingSession->id,
            'reference'             => $pickingSession->reference,
            'state'                 => $pickingSession->state->value,
            'state_label'           => $this->pickingSessionStateLabel($pickingSession->state),
            'picker'                => $this->personPayload($pickingSession->user),
            'number_delivery_notes' => (int) $pickingSession->number_delivery_notes,
            'number_items'          => (int) $pickingSession->number_items,
            'number_trolleys'       => (int) $pickingSession->number_trolleys,
            'picking_percentage'    => (float) $pickingSession->picking_percentage,
            'packing_percentage'    => (float) $pickingSession->packing_percentage,
            'is_waiting_ready'      => (bool) $pickingSession->is_waiting_ready,
            'start_at'              => $this->isoDate($pickingSession->start_at),
            'end_at'                => $this->isoDate($pickingSession->end_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pickingSessionPayload(PickingSession $pickingSession, User $user): array
    {
        $canWork       = $pickingSession->warehouse->canBeWorkedInBy($user);
        $settings      = $this->pickingSettings($pickingSession->organisation);
        $deliveryNotes = $pickingSession->deliveryNotes()->orderBy('delivery_notes.id')->get();

        return [
            ...$this->pickingSessionRow($pickingSession),
            'can_work'            => $canWork,
            'trolleys'            => $pickingSession->trolleys()->orderBy('name')->get()->map(fn (Trolley $trolley) => [
                'id'   => $trolley->id,
                'name' => $trolley->name,
            ])->all(),
            'can_change_trolleys' => $canWork && $pickingSession->state !== PickingSessionStateEnum::PACKING_FINISHED,
            'settings'            => [
                'allow_scan_to_pick'          => $settings['allow_scan_to_pick'],
                'allow_scan_to_pack'          => $settings['allow_scan_to_pack'],
                'allow_waiting'               => $settings['allow_waiting'],
                'allow_picker_set_not_picked' => $settings['allow_picker_set_not_picked'],
            ],
            'available_actions'   => $this->pickingSessionActions($pickingSession, $user, $canWork, $settings, $deliveryNotes),
            'delivery_notes'      => $deliveryNotes->map(fn (DeliveryNote $deliveryNote) => $this->pickingSessionDeliveryNote($deliveryNote))->values()->all(),
            'pick_list'           => $this->pickList($pickingSession),
        ];
    }

    /**
     * @param array<string, bool> $settings
     * @param Collection<int, DeliveryNote> $deliveryNotes
     * @return array<int, array{key: string, label: string}>
     */
    protected function pickingSessionActions(PickingSession $pickingSession, User $user, bool $canWork, array $settings, Collection $deliveryNotes): array
    {
        if (!$canWork || $deliveryNotes->contains(fn (DeliveryNote $deliveryNote) => $deliveryNote->isLockedInAurora())) {
            return [];
        }

        $actions = match ($pickingSession->state) {
            PickingSessionStateEnum::IN_PROCESS       => [
                'start_picking'         => __('Start picking'),
                'add_delivery_notes'    => __('Add delivery notes'),
                'remove_delivery_notes' => __('Remove delivery notes'),
            ],
            PickingSessionStateEnum::HANDLING         => $settings['allow_scan_to_pick'] ? ['scan_pick' => __('Scan to pick')] : [],
            PickingSessionStateEnum::HANDLING_BLOCKED => UndoWaitingPickingSession::canStepBack($user, $pickingSession) ? ['back_to_picking' => __('Back to picking')] : [],
            PickingSessionStateEnum::PICKING_FINISHED => ShowPickingSession::offersScanToPack($pickingSession) ? ['scan_pack' => __('Scan to pack')] : [],
            default                                   => [],
        };

        return collect($actions)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function pickingSessionDeliveryNote(DeliveryNote $deliveryNote): array
    {
        return [
            'id'                 => $deliveryNote->id,
            'reference'          => $deliveryNote->reference,
            'state'              => $deliveryNote->state->value,
            'state_label'        => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
            'number_items'       => (int) $deliveryNote->number_items,
            'picking_percentage' => (float) $deliveryNote->picking_percentage,
            'packing_percentage' => (float) $deliveryNote->packing_percentage,
            'has_waiting_items'  => $deliveryNote->deliveryNoteItems()
                ->where(fn ($query) => $query->where('has_waiting_warehouse', true)->orWhere('has_waiting_crm', true))
                ->exists(),
            'is_for_collection'  => (bool) $deliveryNote->collection_address_id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pickList(PickingSession $pickingSession): array
    {
        if (!in_array($pickingSession->state, [PickingSessionStateEnum::HANDLING, PickingSessionStateEnum::HANDLING_BLOCKED], true)) {
            return [];
        }

        $this->useWebTableParameters([]);

        $items = EloquentCollection::make(
            $this->allWebPages(fn () => IndexDeliveryNoteItemsInPickingSessionStateActive::make()->handle($pickingSession))->all()
        )->load('orgStock.tradeUnits');

        return $items
            ->groupBy(fn (DeliveryNoteItem $item) => ($item->picking_location_id ?? 0).'-'.$item->org_stock_id)
            ->map(fn (Collection $lines, string $key) => $this->pickListEntry($key, $lines))
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, DeliveryNoteItem> $lines
     * @return array<string, mixed>
     */
    private function pickListEntry(string $key, Collection $lines): array
    {
        /** @var DeliveryNoteItem $first */
        $first          = $lines->first();
        $quantityToPick = round($lines->sum(fn (DeliveryNoteItem $line) => PickPickingSessionItemByScan::quantityLeftToPick($line)), 6);

        return [
            'key'                    => $key,
            'location'               => $first->picking_location_id ? [
                'id'   => (int) $first->picking_location_id,
                'code' => $first->picking_location_code,
            ] : null,
            'org_stock'              => [
                'id'        => (int) $first->org_stock_id,
                'code'      => $first->org_stock_code,
                'name'      => $first->org_stock_name,
                'barcode'   => $first->barcode,
                'image_url' => Arr::get(FetchDeliveryNoteItemImage::make()->handle($first), 'original'),
            ],
            'quantity_required'      => round($lines->sum(fn (DeliveryNoteItem $line) => (float) $line->quantity_required), 6),
            'quantity_picked'        => round($lines->sum(fn (DeliveryNoteItem $line) => (float) $line->quantity_picked), 6),
            'quantity_to_pick'       => $quantityToPick,
            'quantity_to_pick_label' => $this->formatScanQuantity($first, $quantityToPick),
            'lines'                  => $lines->map(fn (DeliveryNoteItem $line) => [
                'delivery_note_item_id'   => $line->id,
                'delivery_note_id'        => (int) $line->delivery_note_id,
                'delivery_note_reference' => $line->delivery_note_reference,
                'quantity_required'       => (float) $line->quantity_required,
                'quantity_picked'         => (float) $line->quantity_picked,
                'quantity_to_pick'        => PickPickingSessionItemByScan::quantityLeftToPick($line),
            ])->values()->all(),
        ];
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function respondWithPickingSession(PickingSession $pickingSession, User $user, string $status, ?string $message, array $extra = []): JsonResponse
    {
        return response()->json([
            'data'   => $this->pickingSessionPayload(PickingSession::findOrFail($pickingSession->id), $user),
            'result' => array_merge(['status' => $status, 'message' => $message], $extra),
        ]);
    }
}
