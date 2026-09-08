<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Mar 2025 10:47:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Aurora;

use App\Actions\Traits\WithOrganisationSource;
use App\Actions\Transfers\Aurora\FetchAuroraInvoices;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use App\Transfers\AuroraOrganisationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;
use Laravel\Nightwatch\Facades\Nightwatch;

class RepairInvoiceFetch
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
        $this->findNotFetchedInvoices();
        $this->findDuplicateInvoices($command);
    }


    public function findNotFetchedInvoices(): void
    {
        $counter = 0;

        $sevenDays = now()->subDays(7)->startOfDay();
        DB::connection('aurora')->table('Invoice Dimension')
            ->where('Invoice Date', '>=', $sevenDays)
            ->orderBy('Invoice Date', 'desc')
            ->chunk(
                1000,
                function ($chunkedData) use ($counter) {
                    foreach ($chunkedData as $auroraData) {
                        $sourceId = $this->organisationSource->organisation->id.':'.$auroraData->{'Invoice Key'};
                        if (!Invoice::where('source_id', $sourceId)->exists()) {
                            $counter++;
                            print "$counter Invoice $sourceId  ".$auroraData->{'Invoice Date'}."  not fetched\n";

                            FetchAuroraInvoices::make()->action($this->organisationSource->organisation->id, $auroraData->{'Invoice Key'}, ['full']);
                        }
                    }
                }
            );
    }

    public function checkCount(Command $command): void
    {
        DB::connection('aurora')->table('Invoice Dimension')->count();


        $auroraInvoices = DB::connection('aurora')->table('Invoice Dimension')->count();
        $aikuInvoices   = DB::connection('aiku')->table('invoices')->whereNotNull('source_id')->whereNull('deleted_at')->where('organisation_id', $this->organisationSource->organisation->id)->count();

        $auroraDeletedInvoices = DB::connection('aurora')->table('Invoice Deleted Dimension')->count();
        $aikuDeletedIInvoices  = DB::connection('aiku')->table('invoices')->whereNotNull('source_id')->whereNotNull('deleted_at')->where('organisation_id', $this->organisationSource->organisation->id)->count();


        $command->table(
            ['', 'Aurora', 'Aiku', ''],
            [
                [
                    'invoices',
                    number_format($auroraInvoices),
                    number_format($aikuInvoices),
                    ($aikuInvoices > $auroraInvoices ? '+' : '').$aikuInvoices - $auroraInvoices
                ],
                [
                    'deleted invoices',
                    number_format($auroraDeletedInvoices),
                    number_format($aikuDeletedIInvoices),
                    ($aikuDeletedIInvoices > $auroraDeletedInvoices ? '+' : '').$aikuDeletedIInvoices - $auroraDeletedInvoices
                ]
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
        return 'maintenance:repair_invoice_fetch {organisation}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();


        try {
            $this->handle($command, $organisation);
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }

    public function findDuplicateInvoices(Command $command): void
    {
        $command->info('Checking for invoices with duplicated source_id...');


        $duplicates = DB::connection('aiku')
            ->table('invoices')
            ->select('source_id', DB::raw('COUNT(id) as count'))
            ->where('organisation_id', $this->organisationSource->organisation->id)
            ->whereNotNull('source_id')
            ->groupBy('source_id')
            ->havingRaw('COUNT(id) > 1')  // Use havingRaw with the actual function
            ->get();

        if ($duplicates->isEmpty()) {
            $command->info('No duplicate invoices found.');

            return;
        }

        $command->info('Found '.$duplicates->count().' source_id values with duplicates:');

        foreach ($duplicates as $duplicate) {
            $invoices = Invoice::where('source_id', $duplicate->source_id)
                ->select('id', 'reference', 'date', 'deleted_at')
                ->get();

            $command->line("Source ID: $duplicate->source_id ($duplicate->count occurrences)");

            $details = [];
            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                $details[] = [
                    'id' => $invoice->id,
                    'reference' => $invoice->reference,
                    'date' => $invoice->date,
                    'deleted' => $invoice->deleted_at ? 'Yes' : 'No'
                ];
            }

            $command->table(['ID', 'Reference', 'Date', 'Deleted'], $details);
        }
    }

}
