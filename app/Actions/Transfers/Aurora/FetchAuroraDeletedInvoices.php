<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Feb 2023 15:21:25 Malaysia Time, Ubud, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Models\Accounting\Invoice;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeletedInvoices extends FetchAuroraAction
{
    public string $jobQueue = 'urgent';

    public string $commandSignature = 'fetch:deleted_invoices {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Invoice
    {
        $deletedInvoiceData = $organisationSource->fetchDeletedInvoice($organisationSourceId);
        if (! $deletedInvoiceData or ! $deletedInvoiceData['invoice']) {
            return null;
        }

        $invoice = Invoice::withTrashed()->where('source_id', $deletedInvoiceData['invoice']['source_id'])->first();

        if ($invoice) {
            //  try {
            $invoice = UpdateInvoice::make()->action(
                invoice: $invoice,
                modelData: $deletedInvoiceData['invoice'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
                audit: false
            );
            //            } catch (Exception $e) {
            //                $this->recordError($organisationSource, $e, $deletedInvoiceData['invoice'], 'Invoice', 'update');
            //
            //                return null;
            //            }
        } else {
            // try {
            $invoice = StoreInvoice::make()->action(
                parent: $deletedInvoiceData['parent'],
                modelData: $deletedInvoiceData['invoice'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
                audit: false
            );

            Invoice::enableAuditing();
            $this->saveMigrationHistory(
                $invoice,
                Arr::except($deletedInvoiceData['invoice'], ['fetched_at', 'last_fetched_at', 'source_id'])
            );
            $this->recordNew($organisationSource);

            $sourceData = explode(':', $invoice->source_id);
            DB::connection('aurora')->table('Invoice Deleted Dimension')
                ->where('Invoice Deleted Key', $sourceData[1])
                ->update(['aiku_id' => $invoice->id]);
            //            } catch (Exception|Throwable $e) {
            //                $this->recordError($organisationSource, $e, $deletedInvoiceData['invoice'], 'Invoice', 'store');
            //
            //                return null;
            //            }
        }

        DB::table('invoice_transactions')->where('invoice_id', $invoice->id)
            ->whereNull('deleted_at')->delete();

        return $invoice;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Invoice Deleted Dimension')
            ->select('Invoice Deleted Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Invoice Deleted Dimension')->count();
    }
}
