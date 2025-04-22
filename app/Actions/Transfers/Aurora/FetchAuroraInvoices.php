<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 19:32:35 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\Invoice\SetInvoicePaymentState;
use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\Invoice\UpdateInvoice;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Accounting\Invoice;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraInvoices extends FetchAuroraAction
{
    use WithAuroraParsers;

    public string $jobQueue = 'urgent';

    public string $commandSignature = 'fetch:invoices {organisations?*} {--s|source_id=} {--S|shop= : Shop slug} {--U|only_refunds : Fetch only refunds}  {--N|only_new : Fetch only new} {--w|with=* : Accepted values: transactions payments full} {--d|db_suffix=} {--r|reset} {--T|only_orders_no_transactions : Fetch only orders with no transactions} {--D|days= : fetch last n days} {--O|order= : order asc|desc}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId, bool $forceWithTransactions = false): ?Invoice
    {
        $doTransactions = false;
        if (in_array('transactions', $this->with) || $forceWithTransactions || in_array('full', $this->with)) {
            $doTransactions = true;
        }

        $invoiceData = $organisationSource->fetchInvoice($organisationSourceId);

        if (!$invoiceData) {
            return null;
        }

        if ($invoice = Invoice::withTrashed()->where('source_id', $invoiceData['invoice']['source_id'])->first()) {
            try {
                $invoice = UpdateInvoice::make()->action(
                    invoice: $invoice,
                    modelData: $invoiceData['invoice'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $invoiceData['invoice'], 'Invoice', 'update');

                return null;
            }
        } else {
            try {
                $invoice = StoreInvoice::make()->action(
                    parent: $invoiceData['parent'],
                    modelData: $invoiceData['invoice'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                Invoice::enableAuditing();
                $this->saveMigrationHistory(
                    $invoice,
                    Arr::except($invoiceData['invoice'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );


                $this->recordNew($organisationSource);

                $sourceData = explode(':', $invoice->source_id);
                DB::connection('aurora')->table('Invoice Dimension')
                    ->where('Invoice Key', $sourceData[1])
                    ->update(['aiku_id' => $invoice->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $invoiceData['invoice'], 'Invoice', 'store');

                return null;
            }
        }


        if ($doTransactions) {
            $this->fetchInvoiceTransactions($organisationSource, $invoice);
            $this->fetchInvoiceNoProductTransactions($organisationSource, $invoice);
            $sourceData = explode(':', $invoice->source_id);

            DB::connection('aurora')->table('Invoice Dimension')
                ->where('Invoice Key', $sourceData[1])
                ->update(['aiku_all_id' => $invoice->id]);
        }


        if (in_array('payments', $this->with) or in_array('full', $this->with)) {
            $this->fetchPayments($organisationSource, $invoice);
        }

        return $invoice;
    }

    private function fetchPayments($organisationSource, Invoice $invoice): void
    {
        $organisation = $organisationSource->getOrganisation();
        $sourceData   = explode(':', $invoice->source_id);

        $modelHasPayments = [];

        foreach (

            DB::connection('aurora')
                ->table('Order Payment Bridge')
                ->select('Payment Key')
                ->where('Invoice Key', $sourceData[1])
                ->get() as $auroraData
        ) {
            $payment = $this->parsePayment($organisation->id.':'.$auroraData->{'Payment Key'});

            if ($payment) {
                $modelHasPayments[$payment->id] = [
                    'amount' => $payment->amount,
                    'share'  => 1
                ];
            }
        }

        if ($invoice->shop->type == ShopTypeEnum::FULFILMENT) {
            $aikuPayments = $invoice->payments()->whereNull('payments.source_id')->orWhere('payments.source_id', '')->count();
            // do not sync aurora payments if a payment has been made in aiku
            if ($aikuPayments == 0) {
                $invoice->payments()->sync($modelHasPayments);
            }
        } else {
            $invoice->payments()->sync($modelHasPayments);
        }


        SetInvoicePaymentState::run($invoice);
    }

    private function fetchInvoiceTransactions($organisationSource, Invoice $invoice): void
    {
        if (!$invoice->trashed()) {
            $trashedInvoiceTransactions = $invoice->invoiceTransactions()->onlyTrashed()->get();

            foreach ($trashedInvoiceTransactions as $trashedModel) {
                $trashedModel->forceDelete();
            }
        }


        $transactionsToDelete = $invoice->invoiceTransactions()->withTrashed()->whereIn('model_type', ['Product', 'Service', 'Rental'])->pluck('source_id', 'id')->all();


        $this->allowLegacy = true;

        $sourceData = explode(':', $invoice->source_id);

        foreach (
            DB::connection('aurora')
                ->table('Order Transaction Fact')
                ->select('Order Transaction Fact Key')
                ->where('Invoice Key', $sourceData[1])
                ->get() as $auroraData
        ) {
            $transactionsToDelete = array_diff($transactionsToDelete, [$organisationSource->getOrganisation()->id.':'.$auroraData->{'Order Transaction Fact Key'}]);
            FetchAuroraInvoiceTransactions::run($organisationSource, $auroraData->{'Order Transaction Fact Key'}, $invoice);
        }


        $invoice->invoiceTransactions()->whereIn('id', array_keys($transactionsToDelete))->delete();
    }

    private function fetchInvoiceNoProductTransactions($organisationSource, Invoice $invoice): void
    {
        $transactionsToDelete = $invoice->invoiceTransactions()->withTrashed()->whereNotIn('model_type', ['Product', 'Service', 'Rental'])->pluck('source_alt_id', 'id')->all();

        $this->allowLegacy = true;

        $sourceData = explode(':', $invoice->source_id);

        foreach (
            DB::connection('aurora')
                ->table('Order No Product Transaction Fact')
                ->select('Order No Product Transaction Fact Key')
                ->where('Invoice Key', $sourceData[1])
                ->get() as $auroraData
        ) {
            $transactionsToDelete = array_diff($transactionsToDelete, [$organisationSource->getOrganisation()->id.':'.$auroraData->{'Order No Product Transaction Fact Key'}]);
            FetchAuroraNoProductInvoiceTransactions::run($organisationSource, $auroraData->{'Order No Product Transaction Fact Key'}, $invoice);
        }

        foreach ($transactionsToDelete as $id => $source_id) {
            $invoice->invoiceTransactions()->where('id', $id)->forceDelete();
        }
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Invoice Dimension')
            ->select('Invoice Key as source_id');

        $query = $this->commonSelectModelsToFetch($query);
        $query->orderBy('Invoice Date', $this->orderDesc ? 'desc' : 'asc');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Invoice Dimension');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->count();
    }

    public function commonSelectModelsToFetch($query)
    {
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        } elseif ($this->onlyOrdersNoTransactions) {
            $query->whereNull('aiku_all_id');
        }

        if ($this->onlyRefunds) {
            $query->where('Invoice Type', 'Refund');
        }


        if ($this->fromDays) {
            $query->where('Invoice Date', '>=', now()->subDays($this->fromDays)->format('Y-m-d'));
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Invoice Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Invoice Dimension')->update(['aiku_id' => null]);
    }


}
