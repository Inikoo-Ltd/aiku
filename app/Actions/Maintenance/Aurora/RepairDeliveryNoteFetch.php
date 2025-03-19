<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Mar 2025 00:24:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Dispatching\DeliveryNote\ForceDeleteDeliveryNote;
use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\FetchAuroraDeliveryNotes;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RepairDeliveryNoteFetch
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
        $this->checkCount($command);
        $this->findNotFetchedDeliveryNotes();
        $this->findDuplicateDeliveryNotes($command);
        $this->findNonDeletedDeliveryNotes();
    }

    /**
     * @throws \Throwable
     */
    public function findNonDeletedDeliveryNotes(): void
    {
        $counter = 0;

        $sevenDays = now()->subDays(120)->startOfDay();
        DeliveryNote::where(
            'organisation_id',
            $this->organisationSource->organisation->id
        )->whereNotNull('source_id')
            ->where('created_at', '>=', $sevenDays)
            ->orderBy('created_at', 'desc')
            ->chunk(
                1000,
                function ($chunkedData) use ($counter) {
                    foreach ($chunkedData as $deliveryNote) {


                        $sourceData          = explode(':', $deliveryNote->source_id);
                        if (!DB::connection('aurora')->table('Delivery Note Dimension')->where('Delivery Note Key', $sourceData[1])->exists()) {
                            $counter++;
                            print "$counter Delivery Note ($deliveryNote->id)  $deliveryNote->source_id  ".$deliveryNote->creatd_at."  will be deleted\n";
                            ForceDeleteDeliveryNote::make()->action($deliveryNote);
                        }



                    }
                }
            );
    }

    public function findNotFetchedDeliveryNotes(): void
    {
        $counter = 0;

        $sevenDays = now()->subDays(100000)->startOfDay();
        DB::connection('aurora')->table('Delivery Note Dimension')
            ->where('Delivery Note Date', '>=', $sevenDays)
            ->orderBy('Delivery Note Date', 'desc')
            ->chunk(
                1000,
                function ($chunkedData) use ($counter) {
                    foreach ($chunkedData as $auroraData) {
                        $sourceId = $this->organisationSource->organisation->id.':'.$auroraData->{'Delivery Note Key'};
                        if (!DeliveryNote::where('source_id', $sourceId)->exists()) {
                            $counter++;
                            print "$counter Delivery Note $sourceId  ".$auroraData->{'Delivery Note Date'}."  not fetched\n";

                            FetchAuroraDeliveryNotes::make()->action($this->organisationSource->organisation->id, $auroraData->{'Delivery Note Key'}, ['transactions']);
                        }
                    }
                }
            );
    }

    public function checkCount(Command $command): void
    {
        DB::connection('aurora')->table('Delivery Note Dimension')->count();


        $auroraDeliveryNotes = DB::connection('aurora')->table('Delivery Note Dimension')->count();
        $aikuDeliveryNotes   = DB::connection('aiku')->table('delivery_notes')->whereNotNull('source_id')->where('organisation_id', $this->organisationSource->organisation->id)->count();


        $command->table(
            ['', 'Aurora', 'Aiku', ''],
            [
                [
                    'Delivery notes',
                    number_format($auroraDeliveryNotes),
                    number_format($aikuDeliveryNotes),
                    ($aikuDeliveryNotes > $auroraDeliveryNotes ? '+' : '').$aikuDeliveryNotes - $auroraDeliveryNotes
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
        return 'maintenance:repair_delivery_note_fetch {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();


        try {
            $this->handle($command, $organisation);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    public function findDuplicateDeliveryNotes(Command $command): void
    {
        $command->info('Checking for delivery notes with duplicated source_id...');


        $duplicates = DB::connection('aiku')
            ->table('delivery_notes')
            ->select('source_id', DB::raw('COUNT(id) as count'))
            ->where('organisation_id', $this->organisationSource->organisation->id)
            ->whereNotNull('source_id')
            ->groupBy('source_id')
            ->havingRaw('COUNT(id) > 1')  // Use havingRaw with the actual function
            ->get();

        if ($duplicates->isEmpty()) {
            $command->info('No duplicate delivery notes found.');

            return;
        }

        $command->info('Found '.$duplicates->count().' source_id values with duplicates:');

        foreach ($duplicates as $duplicate) {
            $deliveryNotes = DeliveryNote::where('source_id', $duplicate->source_id)
                ->select('id', 'reference', 'date', 'deleted_at')
                ->get();

            $command->line("Source ID: $duplicate->source_id ($duplicate->count occurrences)");

            $details = [];
            /** @var DeliveryNote $deliveryNote */
            foreach ($deliveryNotes as $deliveryNote) {
                $details[] = [
                    'id'        => $deliveryNote->id,
                    'reference' => $deliveryNote->reference,
                    'date'      => $deliveryNote->date,
                    'deleted'   => $deliveryNote->deleted_at ? 'Yes' : 'No'
                ];
            }

            $command->table(['ID', 'Reference', 'Date', 'Deleted'], $details);
        }
    }

}
