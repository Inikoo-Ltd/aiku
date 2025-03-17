<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 23:45:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Dropshipping\CustomerClient\ForceDeleteCustomerClient;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\FetchAuroraCustomerClients;
use App\Models\Dropshipping\CustomerClient;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairCustomerClientFetch
{
    use AsAction;
    use WithOrganisationSource;

    private int $count = 0;
    /**
     * @var \App\Transfers\AuroraOrganisationService|\App\Transfers\WowsbarOrganisationService|null
     */
    private \App\Transfers\WowsbarOrganisationService|null|AuroraOrganisationService $organisationSource;

    /**
     * @throws \Throwable
     */
    public function handle(Command $command, Organisation $organisation): void
    {
        $this->setSource($organisation);
        $this->findNonDeletedCustomerClients();
        $this->checkCount($command);
        $this->findNotFetchedCustomerClients();
    }

    /**
     * @throws \Throwable
     */
    public function findNonDeletedCustomerClients(): void
    {
        $counter = 0;

        //   $sevenDays = now()->subDays(120)->startOfDay();
        CustomerClient::where(
            'organisation_id',
            $this->organisationSource->organisation->id
        )->whereNotNull('source_id')
    //        ->where('created_at', '>=', $sevenDays)
            ->orderBy('created_at', 'desc')
            ->chunk(
                1000,
                function ($chunkedData) use ($counter) {
                    foreach ($chunkedData as $customerClient) {
                        $sourceData = explode(':', $customerClient->source_id);
                        if (!DB::connection('aurora')->table('Customer Client Dimension')->where('Customer Client Key', $sourceData[1])->exists()) {
                            $counter++;
                            print "$counter Customer Client ($customerClient->id)  $customerClient->source_id  ".$customerClient->creatd_at."  will be deleted\n";
                            try {
                                ForceDeleteCustomerClient::make()->action($customerClient);
                            } catch (Throwable $e) {
                                print_r($e);
                                print "Error deleting customer client $customerClient->id\n";
                            }
                        }
                    }
                }
            );
    }

    public function findNotFetchedCustomerClients(): void
    {
        $counter = 0;

        $sevenDays = now()->subDays(100000)->startOfDay();
        DB::connection('aurora')->table('Customer Client Dimension')
            ->where('Customer Client Creation Date', '>=', $sevenDays)
            ->orderBy('Customer Client Creation Date', 'desc')
            ->chunk(
                1000,
                function ($chunkedData) use ($counter) {
                    foreach ($chunkedData as $auroraData) {
                        $sourceId = $this->organisationSource->organisation->id.':'.$auroraData->{'Customer Client Key'};
                        if (!CustomerClient::withTrashed()->where('source_id', $sourceId)->exists()) {
                            $counter++;
                            print "$counter Customer Client $sourceId  ".$auroraData->{'Customer Client Creation Date'}."  to be fetched\n";

                            FetchAuroraCustomerClients::make()->action($this->organisationSource->organisation->id, $auroraData->{'Customer Client Key'}, []);
                        }
                    }
                }
            );
    }

    public function checkCount(Command $command): void
    {
        DB::connection('aurora')->table('Customer Client Dimension')->count();


        $auroraCustomerClients = DB::connection('aurora')->table('Customer Client Dimension')->count();
        $aikuCustomerClients   = DB::connection('aiku')->table('customer_clients')->whereNotNull('source_id')->where('organisation_id', $this->organisationSource->organisation->id)->count();


        $command->table(
            ['', 'Aurora', 'Aiku', ''],
            [
                [
                    'Customer Clients',
                    number_format($auroraCustomerClients),
                    number_format($aikuCustomerClients),
                    ($aikuCustomerClients > $auroraCustomerClients ? '+' : '').$aikuCustomerClients - $auroraCustomerClients
                ],
            ]
        );
    }


    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:repair_customer_client_fetch {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();


        $this->handle($command, $organisation);


        return 0;
    }


}
