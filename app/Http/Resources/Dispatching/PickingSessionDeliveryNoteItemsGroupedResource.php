<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Jun 2025 14:00:22 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteLeaflets;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNotePackaging;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Actions\Dispatching\PickingSession\UI\PickingSessionGroupedRowsPreload;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Closure;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\AbstractPaginator;

/**
 * @property mixed $delivery_note_id
 * @property mixed $delivery_note_slug
 * @property mixed $delivery_note_customer_notes
 * @property mixed $delivery_note_public_notes
 * @property mixed $delivery_note_internal_notes
 * @property mixed $delivery_note_shipping_notes
 * @property mixed $delivery_note_reference
 * @property mixed $packed_in
 * @property mixed $delivery_note_is_premium_dispatch
 * @property mixed $delivery_note_has_extra_packing
 */
class PickingSessionDeliveryNoteItemsGroupedResource extends JsonResource
{
    use WithDeliveryNotePackaging {
        firstOrder as queryFirstOrder;
        packagingTransaction as queryPackagingTransaction;
    }
    use WithDeliveryNoteLeaflets;

    public ?PickingSessionGroupedRowsPreload $preload = null;

    public static function collection($resource)
    {
        $rows    = $resource instanceof AbstractPaginator ? $resource->getCollection() : collect($resource);
        $preload = PickingSessionGroupedRowsPreload::forDeliveryNotes($rows->pluck('delivery_note_id'));

        $collection = parent::collection($resource);
        $collection->collection->each(fn (self $row) => $row->preload = $preload);

        return $collection;
    }

    protected function rememberPackaging(string $key, Closure $resolve): mixed
    {
        return $this->preload ? $this->preload->remember($key, $resolve) : $resolve();
    }

    protected function firstOrder(DeliveryNote $deliveryNote): ?Order
    {
        return $deliveryNote->relationLoaded('orders') ? $deliveryNote->orders->first() : $this->queryFirstOrder($deliveryNote);
    }

    protected function packagingTransaction(Order $order): ?Transaction
    {
        return $order->relationLoaded('packagingTransaction') ? $order->packagingTransaction : $this->queryPackagingTransaction($order);
    }

    public function toArray($request): array
    {
        $preloadedDeliveryNote = $this->preload?->deliveryNotes->get($this->delivery_note_id);

        $deliveryNote      = $preloadedDeliveryNote ?? DeliveryNote::find($this->delivery_note_id);
        $packaging         = $this->effectivePackaging($deliveryNote);
        $hasIncompleteSets = $deliveryNote->state == DeliveryNoteStateEnum::HANDLING_BLOCKED && $deliveryNote->hasIncompleteSets();

        return [
            'id'                              => $this->delivery_note_id,
            'delivery_note_reference'         => $this->delivery_note_reference,
            'delivery_note_state_icon'        => $deliveryNote->state->stateIcon()[$deliveryNote->state->value],
            'delivery_note_slug'              => $this->delivery_note_slug,
            'delivery_note_id'                => $this->delivery_note_id,
            'delivery_note_state'             => $deliveryNote->state,
            'delivery_note_is_for_collection' => (bool)$deliveryNote->collection_address_id,
            'delivery_note_has_waiting_items' => $preloadedDeliveryNote
                ? $this->preload->deliveryNotesWithWaitingItems->has($deliveryNote->id)
                : $deliveryNote->deliveryNoteItems()
                    ->where(function (Builder $query) {
                        $query->where('has_waiting_warehouse', true)
                            ->orWhere('has_waiting_crm', true);
                    })
                    ->exists(),
            'put_back_incomplete_sets'        => $hasIncompleteSets ? ShowDeliveryNote::make()->getPutBackIncompleteSetsAction($deliveryNote) : null,

            'delivery_note_customer_notes' => $this->delivery_note_customer_notes,
            'delivery_note_public_notes'   => $this->delivery_note_public_notes,
            'delivery_note_internal_notes' => $this->delivery_note_internal_notes,
            'delivery_note_shipping_notes' => $this->delivery_note_shipping_notes,

            'delivery_note_is_premium_dispatch' => $this->delivery_note_is_premium_dispatch,
            'delivery_note_has_extra_packing'   => $this->delivery_note_has_extra_packing,

            'packaging'         => $this->getPackaging($packaging),
            'packaging_options' => $this->getPackagingOptions($deliveryNote, $packaging?->family_code),
            'leaflets'          => $this->getLeaflets($deliveryNote),
            'print_status'      => $this->getPrintStatus($deliveryNote),

            'items' => DeliveryNoteItemsStateHandlingResource::collection(
                $preloadedDeliveryNote
                    ? $this->preload->items->get($deliveryNote->id, collect())
                    : IndexDeliveryNoteItemsStateHandling::run($deliveryNote, ignoreParentPagination: true)
            )->resolve()
        ];
    }

}
