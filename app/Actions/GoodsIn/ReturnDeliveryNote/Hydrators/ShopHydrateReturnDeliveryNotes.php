<?php

namespace App\Actions\GoodsIn\ReturnDeliveryNote\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateReturnDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';

    public function getJobUniqueId(Shop $shop): string
    {
        return $shop->id . '-return_delivery_note_state';
    }

    public function handle(Shop $shop)
    {
        $stats = [
            'number_return_delivery_notes'     =>   $shop->returnDeliveryNotes()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'return_delivery_notes',
            field: 'state',
            enum: ReturnDeliveryNoteStateEnum::class,
            models: ReturnDeliveryNote::class,
            where: function ($q) use ($shop) {
                $q->whereNull('deleted_at')
                    ->where('shop_id', $shop->id);
            }
        ));

        $shop->stats()->update($stats);
    }
}
