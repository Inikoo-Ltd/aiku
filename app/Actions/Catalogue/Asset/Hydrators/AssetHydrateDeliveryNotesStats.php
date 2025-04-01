<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateDeliveryNotes;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Catalogue\Asset;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateDeliveryNotesStats implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateDeliveryNotes;

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }

    public string $jobQueue = 'sales';

    public function handle(Asset $asset): void
    {
        $deliveryNotes = $asset->transactions()
                    ->with('deliveryNoteItem.deliveryNote')
                    ->get()
                    ->pluck('deliveryNoteItem')
                    ->pluck('deliveryNote')
                    ->filter()
                    ->unique('id');

        $stats = [
            'number_delivery_notes'         => $deliveryNotes->count(),

            'last_delivery_note_created_at'    => $deliveryNotes->max('created_at'),
            'last_delivery_note_dispatched_at' => $deliveryNotes->max('dispatched_at'),

            'last_delivery_note_type_order_created_at'    => $deliveryNotes->where('type', DeliveryNoteTypeEnum::ORDER)->max('created_at'),
            'last_delivery_note_type_order_dispatched_at' => $deliveryNotes->where('type', DeliveryNoteTypeEnum::ORDER)->max('dispatched_at'),

            'last_delivery_note_type_replacement_created_at'    => $deliveryNotes->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->max('created_at'),
            'last_delivery_note_type_replacement_dispatched_at' => $deliveryNotes->where('type', DeliveryNoteTypeEnum::REPLACEMENT)->max('dispatched_at'),

        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'delivery_notes',
                field: 'type',
                enum: DeliveryNoteTypeEnum::class,
                models: DeliveryNote::class,
                where: function ($q) use ($deliveryNotes) {
                    $q->whereIn('id', $deliveryNotes->pluck('id'));
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'delivery_notes',
                field: 'state',
                enum: DeliveryNoteStateEnum::class,
                models: DeliveryNote::class,
                where: function ($q) use ($deliveryNotes) {
                    $q->whereIn('id', $deliveryNotes->pluck('id'));
                }
            )
        );

        $asset->orderingStats()->update($stats);
    }
}
