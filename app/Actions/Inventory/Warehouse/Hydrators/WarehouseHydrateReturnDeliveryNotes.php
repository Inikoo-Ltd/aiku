<?php

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydrateReturnDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id . '-return_delivery_note_state';
    }

    public function handle(Warehouse $warehouse)
    {
        $stats = [
            'number_return_delivery_notes'     =>   $warehouse->returnDeliveryNotes()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'return_delivery_notes',
            field: 'state',
            enum: ReturnDeliveryNoteStateEnum::class,
            models: ReturnDeliveryNote::class,
            where: function ($q) use ($warehouse) {
                $q->whereNull('deleted_at')
                    ->where('warehouse_id', $warehouse->id);
            }
        ));

        $warehouse->stats()->update($stats);
    }
}
