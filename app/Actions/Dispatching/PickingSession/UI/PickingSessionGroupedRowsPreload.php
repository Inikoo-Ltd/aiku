<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 15:35:24 +08
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickingSession\UI;

use App\Actions\Dispatching\DeliveryNoteItem\UI\IndexDeliveryNoteItemsStateHandling;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\DeliveryNoteLeaflet;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Everything the grouped tab of a picking session reads per delivery note, fetched once for all the
 * notes on the page. It lives for one rendering of that table and is handed to each of its rows.
 */
class PickingSessionGroupedRowsPreload
{
    /** @var array<string, mixed> */
    private array $remembered = [];

    /**
     * @param  EloquentCollection<int, DeliveryNote>  $deliveryNotes  keyed by id
     * @param  Collection<int, Collection<int, DeliveryNoteItem>>  $items  keyed by delivery note id
     * @param  Collection<int, int>  $deliveryNotesWithWaitingItems  keyed by delivery note id
     */
    private function __construct(
        public readonly EloquentCollection $deliveryNotes,
        public readonly Collection $items,
        public readonly Collection $deliveryNotesWithWaitingItems,
    ) {
    }

    /**
     * Null when there is nothing to gain or the notes cannot share one items query, and each row
     * then reads its own delivery note as it does when fetched alone.
     *
     * @param  Collection<int, int>  $deliveryNoteIds
     */
    public static function forDeliveryNotes(Collection $deliveryNoteIds): ?self
    {
        $deliveryNoteIds = $deliveryNoteIds->filter()->unique()->values();

        if ($deliveryNoteIds->isEmpty()) {
            return null;
        }

        $deliveryNotes = DeliveryNote::whereIn('id', $deliveryNoteIds)
            ->with(['shop.currency', 'packaging', 'orders.packaging', 'orders.packagingTransaction', 'leaflets.modelHasLeaflet'])
            ->get()
            ->keyBy('id');

        if ($deliveryNotes->isEmpty() || $deliveryNotes->pluck('organisation_id')->unique()->count() > 1) {
            return null;
        }

        $organisation = $deliveryNotes->first()->organisation;

        $deliveryNotes->each(function (DeliveryNote $deliveryNote) use ($organisation) {
            $deliveryNote->setRelation('organisation', $organisation);
            $deliveryNote->leaflets->each(fn (DeliveryNoteLeaflet $leaflet) => $leaflet->setRelation('deliveryNote', $deliveryNote));
        });

        return new self(
            $deliveryNotes,
            IndexDeliveryNoteItemsStateHandling::make()->forDeliveryNotes($deliveryNotes),
            DeliveryNoteItem::whereIn('delivery_note_id', $deliveryNotes->modelKeys())
                ->where(function (Builder $query) {
                    $query->where('has_waiting_warehouse', true)
                        ->orWhere('has_waiting_crm', true);
                })
                ->distinct()
                ->pluck('delivery_note_id')
                ->flip(),
        );
    }

    public function remember(string $key, Closure $resolve): mixed
    {
        if (!array_key_exists($key, $this->remembered)) {
            $this->remembered[$key] = $resolve();
        }

        return $this->remembered[$key];
    }
}
