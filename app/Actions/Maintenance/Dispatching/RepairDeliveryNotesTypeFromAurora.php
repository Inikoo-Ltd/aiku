<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Jan 2026 16:02:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Dispatching;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairDeliveryNotesTypeFromAurora
{
    use AsAction;
    use WithOrganisationSource;


    public function getCommandSignature(): string
    {
        return 'maintenance:update_delivery_notes_type_from_aurora {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);

        $query = DB::connection('aurora')->table('Delivery Note Dimension')
            ->whereIn('Delivery Note Type', ['Replacement & Shortages', 'Replacement', 'Shortages']);

        $count = $query->count();

        $bar = $command->getOutput()->createProgressBar($count);
        $bar->setFormat('debug');
        $bar->start();

        $query->select('Delivery Note Key')->orderBy('Delivery Note Key')
            ->chunk(100, function ($auroraData) use ($organisation, $bar) {
                foreach ($auroraData as $data) {
                    $sourceId = $organisation->id.':'.$data->{'Delivery Note Key'};
                    DeliveryNote::where('source_id', $sourceId)->update(
                        [
                            'type' => DeliveryNoteTypeEnum::REPLACEMENT
                        ]
                    );
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();

        return 0;
    }

}
