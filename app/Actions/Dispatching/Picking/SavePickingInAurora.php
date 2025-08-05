<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 03 Aug 2025 08:34:48 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Enums\Dispatching\Picking\PickingTypeEnum;
use App\Models\Dispatching\Picking;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class SavePickingInAurora implements ShouldBeUnique
{
    use AsAction;
    use WithAuroraApi;

    public function getJobUniqueId(Picking $picking): string
    {
        return $picking->id;
    }



    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Picking $picking): void
    {
        if ($picking->type == PickingTypeEnum::NOT_PICK) {
            return;
        }

        $apiUrl = $this->getApiUrl($picking->organisation);


        $note = sprintf(
            '%s picked on <a href="%s" target="_parent">%s</a>',
            $picking->quantity,
            route(
                'grp.org.warehouses.show.dispatching.delivery_notes.show',
                [
                    $picking->deliveryNote->organisation->slug,
                    $picking->deliveryNote->warehouse->slug,
                    $picking->deliveryNote->slug,
                ]
            ),
            $picking->deliveryNote->reference
        );


        Http::withHeaders([
            'secret' => $this->getApiToken($picking->organisation),
        ])->withQueryParameters(
            [
                'picker_name' => $picking->picker->contact_name,
                'action' => 'aiku_picking',
                'location_key' => $this->getAuroraObjectKey($picking->location),
                'part_sku' => $this->getAuroraObjectKey($picking->orgStock),
                'qty' => $picking->quantity,
                'note' => $note,
                'date' => $picking->created_at->format('Y-m-d H:i:s'),
                'picking_key' => $picking->id
            ]
        )->get($apiUrl);
    }


    public function getCommandSignature(): string
    {
        return 'picking:aurora_save {pickingID? : The ID of the picking to save in Aurora (optional, processes all pickings if not provided)}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): int
    {
        $pickingID = $command->argument('pickingID');

        if ($pickingID) {
            // Process a single picking
            $command->info("Processing picking ID: $pickingID");
            $picking = Picking::findOrFail($pickingID);
            $this->handle($picking);
            $command->info("Picking ID: $pickingID processed successfully");
        } else {
            // Process all pickings
            $command->info('Processing all pickings');

            $chunkSize = 100;
            $count     = 0;

            // Get pickings that are not of type NOT_PICK
            $totalPickings = Picking::where('type', '!=', PickingTypeEnum::NOT_PICK)->count();

            if ($totalPickings === 0) {
                $command->info('No pickings to process');

                return 0;
            }

            // Create a progress bar
            $bar = $command->getOutput()->createProgressBar($totalPickings);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $bar->start();

            // Process pickings in chunks to avoid memory issues
            Picking::where('type', '!=', PickingTypeEnum::NOT_PICK)
                ->chunk($chunkSize, function ($pickings) use (&$count, $bar, $command) {
                    foreach ($pickings as $picking) {
                        try {
                            $this->handle($picking);
                            $count++;
                        } catch (\Exception $e) {
                            $command->error("Error processing picking ID: $picking->id - {$e->getMessage()}");
                        }
                        $bar->advance();
                    }
                });

            $bar->finish();
            $command->newLine();
            $command->info("$count pickings processed successfully");
        }

        return 0;
    }


}
