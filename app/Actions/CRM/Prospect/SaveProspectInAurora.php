<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 Oct 2025 11:07:25 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Prospect;

use App\Actions\Dispatching\Picking\WithAuroraApi;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use App\Models\CRM\Prospect;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveProspectInAurora implements ShouldBeUnique
{
    use AsAction;
    use WithAuroraApi;

    public function getJobUniqueId(Prospect $prospect): string
    {
        return $prospect->id;
    }


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Prospect $prospect): void
    {
        if (!$prospect->shop->is_aiku) {
            return;
        }

        $apiUrl       = $this->getApiUrl($prospect->organisation);
        $shopSourceId = explode(':', $prospect->shop->source_id);

        $prospectAuroraId = null;


        $prospectSourceId = null;
        if ($prospect->source_id) {
            $prospectSourceId = $prospect->source_id;
        }
        if (!$prospectSourceId) {
            $prospectSourceId = $prospect->post_source_id;
        }


        if ($prospectSourceId) {
            $prospectSourceId = explode(':', $prospectSourceId);
            $prospectAuroraId = $prospectSourceId[1];
        }

        $status = 'NoContacted';
        //enum('NoContacted','Contacted','NotInterested','Registered','Invoiced','Bounced')
        if ($prospect->state == ProspectStateEnum::SUCCESS) {
            $status = 'Registered';
            if ($prospect->success_status == ProspectSuccessStatusEnum::INVOICED) {
                $status = 'Invoiced';
            }
        } elseif ($prospect->state == ProspectStateEnum::CONTACTED) {
            $status = 'Contacted';
        } elseif ($prospect->state == ProspectStateEnum::FAIL) {
            $status = 'NotInterested';
            if ($prospect->fail_status == ProspectFailStatusEnum::HARD_BOUNCED) {
                $status = 'Bounced';
            }
        }

        $auroraCustomerId = null;
        if ($prospect->customer) {
            $auroraCustomerId = $prospect->customer->source_id;
            if (!$auroraCustomerId) {
                $auroraCustomerId = $prospect->customer->post_source_id;
            }
            if ($auroraCustomerId) {
                $auroraCustomerId = explode(':', $auroraCustomerId)[1];
            }
        }

        $data = [
            'status'              => $status,
            'action'              => 'create_prospect',
            'contact_name'        => $prospect->contact_name,
            'company_name'        => $prospect->company_name,
            'phone'               => $prospect->phone,
            'email'               => $prospect->email,
            'address_line_1'      => $prospect->address?->address_line_1,
            'address_line_2'      => $prospect->address?->address_line_2,
            'sorting_code'        => $prospect->address?->sorting_code,
            'postal_code'         => $prospect->address?->postal_code,
            'dependent_locality'  => $prospect->address?->dependent_locality,
            'locality'            => $prospect->address?->locality,
            'administrative_area' => $prospect->address?->administrative_area,
            'country_code'        => $prospect->address?->country_code,
            'store_key'           => $shopSourceId[1],
            'aiku_id'             => $prospect->id,
            'picker_name'         => 'prospect',
            'created_at'          => $prospect->created_at?->format('Y-m-d H:i:s'),
            'prospect_key'        => $prospectAuroraId,
            'customer_key'        => $auroraCustomerId,
            'opt_in'              => $prospect->is_opt_in ? 'Yes' : 'No',

        ];


        $response = Http::withHeaders([
            'secret' => $this->getApiToken($prospect->organisation),
        ])->withQueryParameters($data)->get($apiUrl);


        if (Arr::get($response, 'prospect_key')) {
            $prospect->update(['post_source_id' => $prospect->organisation->id.':'.$response['prospect_key']]);
        }

        if (Arr::get($response, 'error')) {
            print_r($response->json());
        }
    }


    public function getCommandSignature(): string
    {
        return 'prospect:aurora_save {prospectID? : The ID of the prospect to save in Aurora (optional, processes all prospects if not provided)}';
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): int
    {
        $prospectID = $command->argument('prospectID');

        if ($prospectID) {
            // Process a single prospect
            $command->info("Processing prospect ID: $prospectID");
            $prospect = Prospect::findOrFail($prospectID);
            $this->handle($prospect);
            $command->info("Prospect ID: $prospectID processed successfully");
        } else {
            // Process all pickings
            $command->info('Processing all prospects');

            $chunkSize = 100;
            $count     = 0;

            $totalProspects = Prospect::whereNull('source_id')->count();

            if ($totalProspects === 0) {
                $command->info('No prospects to process');

                return 0;
            }

            // Create a progress bar
            $bar = $command->getOutput()->createProgressBar($totalProspects);
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
            $bar->start();

            // Process pickings in chunks to avoid memory issues
            Prospect::whereNull('source_id')
                ->chunk($chunkSize, function ($prospects) use (&$count, $bar, $command) {
                    foreach ($prospects as $prospect) {
                        try {
                            $this->handle($prospect);
                            $count++;
                        } catch (\Exception $e) {
                            $command->error("Error processing prospect: $prospect->slug - {$e->getMessage()}");
                        }
                        // $bar->advance();
                    }
                });

            $bar->finish();
            $command->newLine();
            $command->info("$count prospects processed successfully");
        }

        return 0;
    }


}
