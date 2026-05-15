<?php

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateReturnDeliveryNotes implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'hydrators-slave';


    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id . '-return_delivery_note_state';
    }


    public function handle(Organisation $organisation)
    {
        $stats = [
            'number_return_delivery_notes'     =>   $organisation->returnDeliveryNotes()->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'return_delivery_notes',
            field: 'state',
            enum: ReturnDeliveryNoteStateEnum::class,
            models: ReturnDeliveryNote::class,
            where: function ($q) use ($organisation) {
                $q->whereNull('deleted_at')
                    ->where('organisation_id', $organisation->id);
            }
        ));

        $organisation->procurementStats()->update($stats);
    }
}
