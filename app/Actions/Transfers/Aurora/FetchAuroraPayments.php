<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Feb 2023 14:04:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\Payment\UpdatePayment;
use App\Models\Accounting\Payment;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPayments extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:payments {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new}  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Payment
    {
        if ($paymentData = $organisationSource->fetchPayment($organisationSourceId)) {
            if ($payment = Payment::where('source_id', $paymentData['payment']['source_id'])->first()) {
                try {
                    $payment = UpdatePayment::make()->action(
                        payment: $payment,
                        modelData: $paymentData['payment'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $payment->wasChanged());

                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $paymentData['payment'], 'Payment', 'update');

                    return null;
                }
            } elseif ($paymentData['customer']) {
                try {
                    $payment = StorePayment::make()->action(
                        customer: $paymentData['customer'],
                        paymentAccount: $paymentData['paymentAccount'],
                        modelData: $paymentData['payment'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Payment::enableAuditing();
                    $this->saveMigrationHistory(
                        $payment,
                        Arr::except($paymentData['payment'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $payment->source_id);
                    DB::connection('aurora')->table('Payment Dimension')
                        ->where('Payment Key', $sourceData[1])
                        ->update(['aiku_id' => $payment->id]);

                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $paymentData['payment'], 'Payment', 'store');

                    return null;
                }
            }

            return $payment;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Payment Dimension')
            ->select('Payment Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->orderBy('Payment Created Date');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Payment Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
