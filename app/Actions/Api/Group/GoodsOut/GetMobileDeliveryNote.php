<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-10h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Api\Group\Locations\WithMobileLocationsAccess;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\FetchDeliveryNoteItemImage;
use App\Actions\Dispatching\DeliveryNoteItem\FetchDeliveryNoteItemRow;
use App\Actions\Dispatching\DeliveryNoteItem\PickDeliveryNoteItemByScan;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItems;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateUnassigned;
use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemMatching;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemStateEnum;
use App\Enums\Dispatching\Picking\PickingNotPickedReasonEnum;
use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Http\Resources\CRM\CustomerResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateHandlingResource;
use App\Http\Resources\Dispatching\DeliveryNoteItemsStateUnassignedResource;
use App\Http\Resources\Dispatching\DeliveryNoteResource;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Picking;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\Location;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileDeliveryNote
{
    use AsController;
    use WithMobileDeliveryNotes;
    use WithMobileLocationsAccess;
    use WithMobilePickingActions;
    use WithMobilePackingFields;
    use WithScannedDeliveryNoteItemMatching;
    private ?int $noteWarehouseId = null;

    /**
     * @return array<string, mixed>
     */
    public function handle(DeliveryNote $deliveryNote, User $user): array
    {
        $this->noteWarehouseId = $deliveryNote->warehouse_id;
        $showDeliveryNote = ShowDeliveryNote::make();
        $header           = DeliveryNoteResource::make($deliveryNote)->resolve();
        $listRow          = $this->listRow($deliveryNote, $user);
        $pickingTotals    = $showDeliveryNote->getPickingTotals($deliveryNote);
        $isEditable       = $showDeliveryNote->isEditableInWarehouse($deliveryNote, $user);
        $canHandle        = $this->canHandleDeliveryNote($deliveryNote);
        $order            = $deliveryNote->orders->first();
        $lines            = $this->lines($deliveryNote);
        $availableActions = $this->availableActions($deliveryNote, $user, $isEditable, $canHandle);

        return [
            'id'                      => $deliveryNote->id,
            'slug'                    => $deliveryNote->slug,
            'reference'               => $deliveryNote->reference,
            'type'                    => $deliveryNote->type->value,
            'state'                   => $deliveryNote->state->value,
            'state_label'             => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
            'warehouse_id'            => $deliveryNote->warehouse_id,
            'shop'                    => [
                'id'   => $deliveryNote->shop->id,
                'name' => $deliveryNote->shop->name,
                'type' => $deliveryNote->shop->type->value,
            ],
            'channel'                 => $listRow
                ? $this->dispatchingChannel((string) $listRow->dispatching_channel, (bool) $listRow->is_partner_customer)
                : $deliveryNote->shop->dispatchingChannel()->value,
            'platform'                => $deliveryNote->platform ? ['name' => $deliveryNote->platform->name] : null,
            'order'                   => $order ? ['id' => $order->id, 'reference' => $order->reference] : null,
            'customer'                => $this->customer($deliveryNote),
            'delivery_address'        => $this->deliveryAddress($deliveryNote),
            'is_collection'           => (bool) $deliveryNote->collection_address_id,
            'is_shipping_by_external' => (bool) $deliveryNote->is_shipping_by_external,
            'is_premium_dispatch'     => (bool) $header['is_premium_dispatch'],
            'is_cash_on_delivery'     => (bool) $header['is_cash_on_delivery'],
            'has_extra_packing'       => (bool) $header['has_extra_packing'],
            'has_gift_message'        => (bool) $header['has_gift_message'],
            'is_export'               => (bool) $listRow?->is_export,
            'summary'                 => [
                'number_items'        => (int) $deliveryNote->number_items,
                'number_skos'         => $pickingTotals['number_skos'],
                'number_units'        => $pickingTotals['number_units'],
                'estimated_weight_kg' => ($deliveryNote->estimated_weight ?? 0) / 1000,
                ...$showDeliveryNote->getEstimatedHandlingMinutes($deliveryNote, $pickingTotals['number_skos']),
            ],
            'picker'                  => $this->userIdAndName($deliveryNote->pickerUser),
            'packer'                  => $this->userIdAndName($deliveryNote->packerUser),
            'trolleys'                => $this->idsAndNames($deliveryNote->trolleysInUseForDisplay()),
            'picked_bays'             => $deliveryNote->pickedBays()->orderBy('code')->get()->map(fn ($pickedBay) => ['id' => $pickedBay->id, 'name' => $pickedBay->code])->all(),
            'parcels'                 => $this->parcels($deliveryNote),
            'parcels_editable'        => $this->parcelsEditable($deliveryNote, $isEditable),
            'consumables'             => $this->consumables($deliveryNote),
            'packaging'               => $this->packaging($deliveryNote),
            'inserts'                 => $this->inserts($deliveryNote, $user),
            'box_packing_list'        => $this->boxPackingList($deliveryNote),
            'shipping'                => $this->shipping($deliveryNote, $user, $availableActions),
            'notes'                   => $this->notes($showDeliveryNote, $deliveryNote),
            'timeline'                => $this->timeline($showDeliveryNote, $deliveryNote),
            'shipments'               => $this->shipments($deliveryNote, $user),
            'documents'               => $this->documents($deliveryNote, (bool) $header['has_gift_message']),
            'waiting_warehouse_count' => (int) $header['number_items_waiting_warehouse'],
            'waiting_crm_count'       => (int) $header['number_items_waiting_crm'],
            'is_editable'             => $isEditable,
            'can_handle'              => $canHandle,
            'allow_actions'           => $isEditable && $canHandle,
            'can_edit_contact'        => $deliveryNote->canBeWorkedOnBy($user),
            'available_actions'       => $availableActions,
            'settings'                => $this->pickingSettings($deliveryNote->organisation),
            'incomplete_sets'         => $this->incompleteSets($deliveryNote),
            'read_only_notice'        => $showDeliveryNote->getAuroraNotice($deliveryNote) ?? $showDeliveryNote->getReadOnlyNotice($deliveryNote, $isEditable),
            'warning'                 => $showDeliveryNote->getWarning($deliveryNote)['text'] ?? null,
            'return_delivery_note'    => $this->cancellationReturn($deliveryNote),
            'line_counts'             => $this->lineCounts($deliveryNote),
            'lines'                   => $lines,
        ];
    }

    /**
     * @return array{id: int, reference: string, state_label: string}|null
     */
    private function cancellationReturn(DeliveryNote $deliveryNote): ?array
    {
        if ($deliveryNote->state !== DeliveryNoteStateEnum::CANCELLED) {
            return null;
        }

        /** @var ReturnDeliveryNote|null $returnDeliveryNote */
        $returnDeliveryNote = $deliveryNote->returnedDeliveryNote()
            ->where('type', ReturnDeliveryNoteTypeEnum::CANCELLATION)
            ->latest('id')
            ->first();

        if (!$returnDeliveryNote) {
            return null;
        }

        return [
            'id'          => $returnDeliveryNote->id,
            'reference'   => $returnDeliveryNote->reference,
            'state_label' => ReturnDeliveryNoteStateEnum::labels()[$returnDeliveryNote->state->value],
        ];
    }

    private function listRow(DeliveryNote $deliveryNote, User $user): ?DeliveryNote
    {
        $query = $this->deliveryNotesQuery($deliveryNote->warehouse, 'all', 'all', null, $user);

        /** @var DeliveryNote|null $row */
        $row = $this->withRowColumns($query)->where('delivery_notes.id', $deliveryNote->id)->first();

        return $row;
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userIdAndName(?User $user): ?array
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
     * @return array<string, mixed>|null
     */
    private function customer(DeliveryNote $deliveryNote): ?array
    {
        if (!$deliveryNote->customer) {
            return null;
        }

        $customer = CustomerResource::make($deliveryNote->customer)->getArray();

        return [
            'name'         => $customer['name'],
            'reference'    => $customer['reference'],
            'contact_name' => $customer['contact_name'],
            'company_name' => $customer['company_name'],
            'email'        => $customer['email'],
            'phone'        => $customer['phone'],
            'is_vip'       => (bool) $deliveryNote->customer->is_vip,
        ];
    }

    /**
     * @return array{lines: array<int, string>, country_code: string|null, fields: array<string, string|null>}|null
     */
    private function deliveryAddress(DeliveryNote $deliveryNote): ?array
    {
        $address = $deliveryNote->deliveryAddress;

        if (!$address) {
            return null;
        }

        return [
            'lines'        => collect(explode("\n", $address->formatted_address))->map(fn (string $line) => trim($line))->filter()->values()->all(),
            'country_code' => $address->country_code,
            'fields'       => [
                'contact_name'        => $deliveryNote->contact_name,
                'company_name'        => $deliveryNote->company_name,
                'email'               => $deliveryNote->email,
                'phone'               => $deliveryNote->phone,
                'address_line_1'      => $address->address_line_1,
                'address_line_2'      => $address->address_line_2,
                'sorting_code'        => $address->sorting_code,
                'postal_code'         => $address->postal_code,
                'locality'            => $address->locality,
                'dependent_locality'  => $address->dependent_locality,
                'administrative_area' => $address->administrative_area,
                'country_code'        => $address->country_code,
            ],
        ];
    }

    /**
     * @return array<int, array{weight_kg: float|null, dimensions_cm: array<int, float>|null, boxes: int|null}>
     */
    private function parcels(DeliveryNote $deliveryNote): array
    {
        return collect(is_array($deliveryNote->parcels) ? $deliveryNote->parcels : [])
            ->filter(fn ($parcel) => is_array($parcel))
            ->map(fn (array $parcel) => [
                'weight_kg'     => isset($parcel['weight']) ? (float) $parcel['weight'] : null,
                'dimensions_cm' => is_array($parcel['dimensions'] ?? null) ? array_map(fn ($dimension) => (float) $dimension, array_values($parcel['dimensions'])) : null,
                'boxes'         => isset($parcel['boxes']) ? (int) $parcel['boxes'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{text: string, editable: bool}>
     */
    private function notes(ShowDeliveryNote $showDeliveryNote, DeliveryNote $deliveryNote): array
    {
        $notes = collect($showDeliveryNote->getDeliveryNoteNotes($deliveryNote)['note_list'])->keyBy('field');

        $note = fn (string $field) => [
            'text'     => (string) $notes->get($field)['note'],
            'editable' => (bool) $notes->get($field)['editable'],
        ];

        return [
            'shipping'  => $note('shipping_notes'),
            'customer'  => $note('customer_notes'),
            'warehouse' => $note('private_warehouse_note'),
        ];
    }

    /**
     * @return array<int, array{state: string, label: string, at: string|null}>
     */
    private function timeline(ShowDeliveryNote $showDeliveryNote, DeliveryNote $deliveryNote): array
    {
        return collect($showDeliveryNote->getTimeline($deliveryNote))
            ->map(fn (array $step) => [
                'state' => $step['key'],
                'label' => $step['tooltip'],
                'at'    => $this->isoDate($step['timestamp']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{all: int, todo: int, done: int}
     */
    private function lineCounts(DeliveryNote $deliveryNote): array
    {
        if (in_array($deliveryNote->state, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED, DeliveryNoteStateEnum::FINALISED, DeliveryNoteStateEnum::DISPATCHED, DeliveryNoteStateEnum::CANCELLED], true)) {
            return ShowDeliveryNote::packingCounts($deliveryNote);
        }

        return ShowDeliveryNote::pickingCounts($deliveryNote);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lines(DeliveryNote $deliveryNote): array
    {
        $this->useWebTableParameters([]);

        [$index, $resource] = match ($deliveryNote->state) {
            DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED => [IndexDeliveryNoteItemsStateUnassigned::make(), DeliveryNoteItemsStateUnassignedResource::class],
            DeliveryNoteStateEnum::HANDLING                                  => [IndexDeliveryNoteItemsStateHandling::make(), DeliveryNoteItemsStateHandlingResource::class],
            default                                                          => [IndexDeliveryNoteItems::make(), DeliveryNoteItemsResource::class],
        };

        $items = $this->allWebPages(fn () => $index->handle($deliveryNote))->values();
        $rows  = $resource::collection($items)->resolve();

        $pickings            = Picking::query()->whereIn('delivery_note_item_id', $items->pluck('id'))->with('location')->orderBy('id')->get();
        $pickingsByLine      = $pickings->groupBy('delivery_note_item_id');
        $locationOrgStockIds = $this->pickingLocationOrgStockIds($pickings);

        return $items->map(fn (DeliveryNoteItem $item, int $position) => $this->line($item, $rows[$position], $pickingsByLine->get($item->id, collect()), $locationOrgStockIds))->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pickingLocations(DeliveryNoteItem $item): array
    {
        $this->useWebTableParameters([]);
        request()->merge(['page' => 1]);

        $row = FetchDeliveryNoteItemRow::run($item)?->resolve();

        return $row ? $this->lineLocations($item, $row['locations'] ?? [], $item->pickings()->orderBy('id')->get()) : [];
    }

    /**
     * @param Collection<int, Picking> $pickings
     * @return Collection<string, int>
     */
    private function pickingLocationOrgStockIds(Collection $pickings): Collection
    {
        $pickings = $pickings->filter(fn (Picking $picking) => $picking->location_id);

        if ($pickings->isEmpty()) {
            return collect();
        }

        return LocationOrgStock::query()
            ->whereIn('location_id', $pickings->pluck('location_id')->unique()->values())
            ->whereIn('org_stock_id', $pickings->pluck('org_stock_id')->unique()->values())
            ->get(['id', 'location_id', 'org_stock_id'])
            ->mapWithKeys(fn (LocationOrgStock $locationOrgStock) => [$locationOrgStock->location_id.'-'.$locationOrgStock->org_stock_id => $locationOrgStock->id]);
    }

    /**
     * @param Collection<int, Picking> $pickings
     * @param Collection<string, int> $locationOrgStockIds
     * @return array<int, array<string, mixed>>
     */
    private function linePickings(DeliveryNoteItem $item, Collection $pickings, Collection $locationOrgStockIds): array
    {
        return $pickings
            ->map(function (Picking $picking) use ($item, $locationOrgStockIds) {
                $isNotPick = $picking->type === PickingTypeEnum::NOT_PICK;
                $reason    = $isNotPick ? $picking->not_picked_reason : null;

                return [
                    'id'                      => $picking->id,
                    'type'                    => $picking->type?->value,
                    'location_org_stock_id'   => $picking->location_id ? $locationOrgStockIds->get($picking->location_id.'-'.$picking->org_stock_id) : null,
                    'location_code'           => $picking->location?->code,
                    'quantity'                => (float) $picking->quantity,
                    'quantity_label'          => $this->formatScanQuantity($item, (float) $picking->quantity),
                    'not_picked_reason'       => $reason?->value,
                    'not_picked_reason_label' => $reason ? PickingNotPickedReasonEnum::labels()[$reason->value] : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string|null, name: string|null, quantity: float, quantity_label: string, locations: array<int, string>}>
     */
    private function incompleteSets(DeliveryNote $deliveryNote): array
    {
        if (!$deliveryNote->hasIncompleteSets()) {
            return [];
        }

        $items = $deliveryNote->incompleteSetItems()->with('orgStock')->get()->keyBy(fn (DeliveryNoteItem $item) => $item->orgStock?->code);

        return collect(ShowDeliveryNote::make()->getPutBackIncompleteSetsAction($deliveryNote)['parts'])
            ->map(fn (array $part) => [
                'code'           => $part['code'],
                'name'           => $part['name'],
                'quantity'       => (float) $part['quantity'],
                'quantity_label' => $items->has($part['code']) ? $this->formatScanQuantity($items->get($part['code']), (float) $part['quantity']) : (string) ((float) $part['quantity'] + 0),
                'locations'      => $part['locations'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, mixed> $row
     * @param Collection<int, Picking> $pickings
     * @param Collection<string, int> $locationOrgStockIds
     * @return array<string, mixed>
     */
    private function line(DeliveryNoteItem $item, array $row, Collection $pickings, Collection $locationOrgStockIds): array
    {
        $quantityRequired = (float) $row['quantity_required'];
        $quantityPicked   = (float) ($row['quantity_picked'] ?? $item->quantity_picked);
        $quantityToPick   = (float) ($row['quantity_to_pick'] ?? PickDeliveryNoteItemByScan::quantityLeftToPick($item));
        $quantityPacked   = (float) ($row['quantity_packed'] ?? $item->quantity_packed);
        $quantityToPack   = (float) ($row['quantity_to_pack'] ?? ($quantityPicked > 0 ? UpdateDeliveryNoteItemPacking::quantityLeftToPack($item) : 0));
        $state            = $row['state'] instanceof DeliveryNoteItemStateEnum ? $row['state'] : DeliveryNoteItemStateEnum::from($row['state']);
        $isDonePacking    = (bool) ($row['is_done_packing'] ?? false);

        return [
            'id'                         => $item->id,
            'state'                      => $state->value,
            'state_label'                => DeliveryNoteItemStateEnum::labels()[$state->value],
            'org_stock'                  => [
                'id'        => $row['org_stock_id'],
                'code'      => $row['org_stock_code'],
                'name'      => $row['org_stock_name'],
                'barcode'   => $row['barcode'],
                'packed_in' => (int) $row['packed_in'],
                'image_url' => Arr::get(FetchDeliveryNoteItemImage::make()->handle($item), 'original'),
            ],
            'quantity_required'          => $quantityRequired,
            'quantity_required_label'    => $this->formatScanQuantity($item, $quantityRequired),
            'quantity_picked'            => $quantityPicked,
            'quantity_picked_label'      => $this->formatScanQuantity($item, $quantityPicked),
            'quantity_to_pick'           => $quantityToPick,
            'quantity_to_pick_label'     => $this->formatScanQuantity($item, $quantityToPick),
            'quantity_not_picked'        => (float) ($row['quantity_not_picked'] ?? $item->quantity_not_picked),
            'quantity_packed'            => $quantityPacked,
            'quantity_packed_label'      => $this->formatScanQuantity($item, $quantityPacked),
            'quantity_to_pack'           => $quantityToPack,
            'quantity_to_pack_label'     => $this->formatScanQuantity($item, $quantityToPack),
            'quantity_waiting_warehouse' => (float) ($row['quantity_waiting_warehouse'] ?? $item->quantity_waiting_warehouse),
            'quantity_waiting_crm'       => (float) ($row['quantity_waiting_crm'] ?? $item->quantity_waiting_crm),
            'is_handled'                 => (bool) ($row['is_handled'] ?? $item->is_handled),
            'is_picked'                  => (bool) ($row['is_picked'] ?? $quantityToPick == 0),
            'is_done_packing'            => $isDonePacking,
            'is_packed'                  => $isDonePacking,
            'locations'                  => $this->lineLocations($item, $row['locations'] ?? [], $pickings),
            'pickings'                   => $this->linePickings($item, $pickings, $locationOrgStockIds),
            'boxes'                      => $this->lineBoxes($row['boxes'] ?? []),
            'warehouse_area'             => $row['warehouse_area'] ?? null,
            'note_to_pickers'            => $row['note_to_pickers'] ?? null,
            'note_to_packers'            => $row['note_to_packers'] ?? null,
            'un_numbers'                 => $row['un_numbers'] ?? null,
            'replacement_reason'         => $row['replacement_reason_label'] ?? null,
            'indivisible_set'            => !empty($row['indivisible_set']),
        ];
    }

    /**
     * @return array<int, array{box: int, quantity: float}>
     */
    private function lineBoxes(mixed $boxes): array
    {
        return collect(is_array($boxes) ? $boxes : [])
            ->filter(fn ($box) => is_array($box))
            ->map(fn (array $box) => ['box' => (int) $box['box'], 'quantity' => (float) $box['quantity']])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, Picking> $pickings
     * @return array<int, array<string, mixed>>
     */
    private function lineLocations(DeliveryNoteItem $item, mixed $locations, Collection $pickings): array
    {
        $locations          = collect($locations instanceof JsonResource ? $locations->resolve() : (array) $locations);
        $warehouseLocations = Location::whereIn('id', $locations->pluck('location_id')->filter()->all())
            ->where('warehouse_id', $this->noteWarehouseId ?? $item->deliveryNote?->warehouse_id)
            ->pluck('id')
            ->all();

        return $locations
            ->filter(fn (array $location) => in_array((int) $location['location_id'], $warehouseLocations, true))
            ->map(fn (array $location) => [
                'location_org_stock_id' => (int) $location['id'],
                'location_id'           => (int) $location['location_id'],
                'code'                  => $location['location_code'],
                'stock'                 => (float) $location['quantity'],
                'stock_label'           => $this->formatScanQuantity($item, (float) $location['quantity']),
                'type'                  => $location['type'],
                'quantity_picked'       => (float) $location['quantity_picked'],
                'picking_id'            => $pickings->first(fn (Picking $picking) => $picking->location_id == $location['location_id'])?->id,
            ])
            ->values()
            ->all();
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->authorisedWarehouses()->whereKey($deliveryNote->warehouse_id)->exists(), 404);
        abort_unless($deliveryNote->canBeViewedBy($user), 403);

        return response()->json(['data' => $this->handle($deliveryNote, $user)]);
    }
}
