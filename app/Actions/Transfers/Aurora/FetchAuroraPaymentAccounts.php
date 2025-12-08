<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 19:41:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\Accounting\PaymentAccount\UpdatePaymentAccount;
use App\Models\Accounting\PaymentAccount;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPaymentAccounts extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:payment_accounts {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?PaymentAccount
    {
        if ($paymentAccountData = $organisationSource->fetchPaymentAccount($organisationSourceId)) {
            if ($paymentAccount = PaymentAccount::where('source_id', $paymentAccountData['paymentAccount']['source_id'])
                ->first()) {
                try {
                    $paymentAccount = UpdatePaymentAccount::make()->action(
                        paymentAccount: $paymentAccount,
                        modelData: $paymentAccountData['paymentAccount'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $paymentAccountData['paymentAccount'], 'PaymentAccount', 'update');

                    return null;
                }
            } else {
                try {
                    $paymentAccount = StorePaymentAccount::make()->action(
                        orgPaymentServiceProvider: $paymentAccountData['orgPaymentServiceProvider'],
                        modelData: $paymentAccountData['paymentAccount'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    PaymentAccount::enableAuditing();
                    $this->saveMigrationHistory(
                        $paymentAccount,
                        Arr::except($paymentAccountData['paymentAccount'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceId = explode(':', $paymentAccount->source_id);
                    DB::connection('aurora')->table('Payment Account Dimension')
                        ->where('Payment Account Key', $sourceId[1])
                        ->update(['aiku_id' => $paymentAccount->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $paymentAccountData['paymentAccount'], 'PaymentAccount', 'store');

                    return null;
                }
            }

            return $paymentAccount;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Payment Account Dimension')
            ->select('Payment Account Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Payment Account Dimension')->count();
    }
}
