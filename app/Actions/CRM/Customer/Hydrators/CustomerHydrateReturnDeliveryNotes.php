<?php

/*
 * author Louis Perez
 * created on 06-06-2026-10h-11m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\CRM\Customer;
use App\Models\GoodsIn\ReturnDeliveryNote;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateReturnDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';


    public function getJobUniqueId(Customer $customer): string
    {
        return $customer->id . '-return_delivery_note_state';
    }


    public function handle(Customer $customer)
    {
        $stats = [
            'number_return_delivery_notes'     =>   $customer->returnDeliveryNotes()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'return_delivery_notes',
            field: 'state',
            enum: ReturnDeliveryNoteStateEnum::class,
            models: ReturnDeliveryNote::class,
            where: function ($q) use ($customer) {
                $q->whereNull('deleted_at')
                    ->where('customer_id', $customer->id);
            }
        ));

        $customer->stats()->update($stats);
    }
}
