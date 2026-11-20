<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\ReturnDeliveryNote\UI\ShowReturnDeliveryNote;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteTypeEnum;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Http\Resources\Procurement\ReturnDeliveryNoteItemsResource;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait WithMobileReturns
{
    use WithMobileGoodsIn;

    protected function ensureCanViewReturns(User $user, Warehouse $warehouse): void
    {
        $this->ensureWarehouseAccess($user, $warehouse, self::canViewReturns($user, $warehouse), __('You can not see the returns of this warehouse.'));
    }

    protected function ensureCanViewReturn(User $user, Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote): void
    {
        $this->ensureCanViewReturns($user, $warehouse);
        abort_unless($returnDeliveryNote->warehouse_id === $warehouse->id, 404);
    }

    protected function ensureCanHandleReturn(User $user, Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote): void
    {
        $this->ensureCanViewReturn($user, $warehouse, $returnDeliveryNote);
        abort_unless(self::canHandleReturns($user, $warehouse), 403, __('You can not work on returns.'));
    }

    protected function ensureItemOfReturn(ReturnDeliveryNote $returnDeliveryNote, ReturnDeliveryNoteItem $returnDeliveryNoteItem): void
    {
        abort_unless($returnDeliveryNoteItem->return_delivery_note_id === $returnDeliveryNote->id, 404);
    }

    protected function ensureReturnState(ReturnDeliveryNote $returnDeliveryNote, ReturnDeliveryNoteStateEnum $state): void
    {
        if ($returnDeliveryNote->state !== $state) {
            $this->refuseState(__('This can not be done while the return is :state.', ['state' => ReturnDeliveryNoteStateEnum::labels()[$returnDeliveryNote->state->value]]));
        }
    }

    protected function remainingOf(ReturnDeliveryNoteItem $returnDeliveryNoteItem): float
    {
        return (float) $returnDeliveryNoteItem->total_expected_qty - (
            (float) $returnDeliveryNoteItem->total_item_damaged
            + (float) $returnDeliveryNoteItem->total_item_not_returned
            + (float) $returnDeliveryNoteItem->total_item_returned
        );
    }

    protected function hasHandledItem(ReturnDeliveryNote $returnDeliveryNote): bool
    {
        return ReturnDeliveryNoteItem::where('return_delivery_note_id', $returnDeliveryNote->id)
            ->where('total_expected_qty', '>', 0)
            ->where('is_handled', true)
            ->exists();
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    protected function returnActions(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, User $user): array
    {
        if (!self::canHandleReturns($user, $warehouse)) {
            return [];
        }

        return match ($returnDeliveryNote->state) {
            ReturnDeliveryNoteStateEnum::RECEIVED  => [$this->actionOption('start', __('Start'))],
            ReturnDeliveryNoteStateEnum::RETURNING => array_values(array_filter([
                $this->actionOption('put_back', __('Put back')),
                $this->actionOption('undo', __('Undo')),
                $this->hasHandledItem($returnDeliveryNote) ? $this->actionOption('set_as_returned', __('Set as returned')) : null,
            ])),
            default => [],
        };
    }

    /**
     * @return array{id: int, reference: string}|null
     */
    protected function returnDeliveryNoteReference(ReturnDeliveryNote $returnDeliveryNote): ?array
    {
        $deliveryNote = $returnDeliveryNote->deliveryNote;

        return $deliveryNote ? ['id' => $deliveryNote->id, 'reference' => $deliveryNote->reference] : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function returnDetail(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, User $user): array
    {
        $returnDeliveryNote->loadMissing(['customer', 'order', 'deliveryNote', 'handlerUser', 'shop']);

        return [
            'id'                => $returnDeliveryNote->id,
            'reference'         => $returnDeliveryNote->reference,
            'type'              => $returnDeliveryNote->type->value,
            'type_label'        => ReturnDeliveryNoteTypeEnum::labels()[$returnDeliveryNote->type->value],
            'state'             => $returnDeliveryNote->state->value,
            'state_label'       => ReturnDeliveryNoteStateEnum::labels()[$returnDeliveryNote->state->value],
            'customer'          => $returnDeliveryNote->customer ? ['name' => $returnDeliveryNote->customer->name] : null,
            'order'             => $returnDeliveryNote->order ? ['reference' => $returnDeliveryNote->order->reference] : null,
            'delivery_note'     => $this->returnDeliveryNoteReference($returnDeliveryNote),
            'handler'           => $this->userReference($returnDeliveryNote->handlerUser),
            'timeline'          => collect(ShowReturnDeliveryNote::make()->getTimeline($returnDeliveryNote))->map(fn (array $step) => [
                'state' => $step['key'],
                'label' => $step['label'],
                'at'    => $this->isoDateTime($step['timestamp']),
            ])->values()->all(),
            'items'             => $this->returnItemRows($warehouse, $returnDeliveryNote),
            'available_actions' => $this->returnActions($warehouse, $returnDeliveryNote, $user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function returnItemRows(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote): array
    {
        $items = ReturnDeliveryNoteItem::where('return_delivery_note_id', $returnDeliveryNote->id)
            ->with(['orgStock.tradeUnits.image', 'sowings' => fn ($sowings) => $sowings->orderBy('id')->with(['location', 'sower'])])
            ->orderBy('id')
            ->get();

        $locationsBySko = DB::table('location_org_stocks')
            ->join('locations', 'location_org_stocks.location_id', '=', 'locations.id')
            ->whereIn('location_org_stocks.org_stock_id', $items->pluck('org_stock_id')->filter()->unique()->values())
            ->where('location_org_stocks.warehouse_id', $warehouse->id)
            ->where('locations.warehouse_id', $warehouse->id)
            ->whereNull('locations.deleted_at')
            ->select(['location_org_stocks.id', 'location_org_stocks.org_stock_id', 'location_org_stocks.quantity', 'location_org_stocks.type', 'location_org_stocks.version', 'locations.id as location_id', 'locations.code as location_code'])
            ->orderByRaw(ReturnDeliveryNoteItemsResource::defaultLocationOrder($returnDeliveryNote->shop?->type))
            ->orderBy('location_org_stocks.picking_priority')
            ->get()
            ->groupBy('org_stock_id');

        return $items->map(fn (ReturnDeliveryNoteItem $item) => $this->returnItemRow($item, $locationsBySko->get($item->org_stock_id, collect())))->values()->all();
    }

    /**
     * @param Collection<int, object> $locations
     * @return array<string, mixed>
     */
    protected function returnItemRow(ReturnDeliveryNoteItem $item, Collection $locations): array
    {
        $orgStock = $item->orgStock;
        $packedIn = $orgStock?->packed_in;

        return [
            'id'           => $item->id,
            'state'        => $item->state->value,
            'is_handled'   => (bool) $item->is_handled,
            'sko'          => [
                'id'        => $orgStock?->id,
                'code'      => $orgStock?->code,
                'name'      => $orgStock?->name,
                'packed_in' => $packedIn === null ? null : (int) $packedIn,
                'image_url' => $orgStock ? $this->orgStockImageUrl($orgStock) : null,
            ],
            ...$this->skoFields('expected', $item->total_expected_qty, $packedIn),
            ...$this->skoFields('returned', $item->total_item_returned, $packedIn),
            ...$this->skoFields('damaged', $item->total_item_damaged, $packedIn),
            ...$this->skoFields('not_returned', $item->total_item_not_returned, $packedIn),
            ...$this->skoFields('remaining', max(0, $this->remainingOf($item)), $packedIn),
            'puts'         => $item->sowings->map(fn (Sowing $sowing) => [
                'id'         => $sowing->id,
                'type'       => $sowing->type->value,
                'type_label' => $this->putTypeLabel($sowing->type),
                ...$this->skoFields('quantity', $sowing->quantity, $packedIn),
                'location'   => $sowing->location ? ['id' => $sowing->location->id, 'code' => $sowing->location->code] : null,
                'by'         => $this->userReference($sowing->sower),
            ])->values()->all(),
            'locations'    => $locations->map(fn ($location) => [
                'location_org_stock_id' => $location->id,
                'location'              => ['id' => $location->location_id, 'code' => $location->location_code],
                'type'                  => $location->type,
                ...$this->skoFields('quantity', $location->quantity, $packedIn),
                'location_id'           => $location->location_id,
                'location_code'         => $location->location_code,
                'version'               => (int) $location->version,
            ])->values()->all(),
            'barcodes'     => $orgStock ? self::skoBarcodes($orgStock) : [],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function skoBarcodes(OrgStock $orgStock): array
    {
        return array_values(array_unique(array_filter([$orgStock->barcode, $orgStock->unit_barcode], fn (?string $barcode) => $barcode !== null && $barcode !== '')));
    }

    protected function putTypeLabel(SowingTypeEnum $type): string
    {
        return match ($type) {
            SowingTypeEnum::SOW     => __('Returned'),
            SowingTypeEnum::DAMAGED => __('Damaged'),
            SowingTypeEnum::NOT_SOW => __('Not returned'),
        };
    }

    protected function respondWithReturn(Warehouse $warehouse, ReturnDeliveryNote $returnDeliveryNote, User $user, string $status, string $message): JsonResponse
    {
        return response()->json([
            'data'   => $this->returnDetail($warehouse, ReturnDeliveryNote::findOrFail($returnDeliveryNote->id), $user),
            'result' => [
                'status'  => $status,
                'message' => $message,
            ],
        ]);
    }
}
