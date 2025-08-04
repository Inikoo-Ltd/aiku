<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 08:41:26 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Models\Dispatching\Picking;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class DeletePickingInAurora implements ShouldBeUnique
{
    use AsAction;
    use WithAuroraApi;

    public function getJobUniqueId(int $pickingID, Organisation $organisation): string
    {
        return $organisation->id.$pickingID;
    }


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(int $pickingID, Organisation $organisation, string $name, OrgStock $orgStock): void
    {
        $apiUrl = $this->getApiUrl($organisation);


        Http::withHeaders([
            'secret' => $this->getApiToken($organisation),
        ])->withQueryParameters(
            [
                'picker_name' => $name,
                'action'      => 'aiku_delete_picking',
                'part_sku'    => $this->getAuroraObjectKey($orgStock),
                'picking_key' => $pickingID

            ]
        )->get($apiUrl);
    }


    public function getCommandSignature(): string
    {
        return 'picking:aurora_delete {pickingID}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): int
    {
        $pickingID = $command->argument('pickingID');


        $command->info("Processing picking ID: $pickingID");
        $picking = Picking::findOrFail($pickingID);
        $this->handle(
            $picking->id,
            $picking->organisation,
            $picking->picker->contact_name,
            $picking->orgStock
        );
        $command->info("Picking ID: $pickingID processed successfully");


        return 0;
    }


}
