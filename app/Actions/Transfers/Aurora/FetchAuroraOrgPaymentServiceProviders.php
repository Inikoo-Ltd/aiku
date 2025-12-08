<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 14:24:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\OrgPaymentServiceProvider\StoreOrgPaymentServiceProvider;
use App\Actions\Accounting\OrgPaymentServiceProvider\UpdateOrgPaymentServiceProvider;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraOrgPaymentServiceProviders extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:payment_service_providers {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?OrgPaymentServiceProvider
    {
        if ($paymentServiceProviderData = $organisationSource->fetchOrgPaymentServiceProvider($organisationSourceId)) {
            if ($orgPaymentServiceProvider = OrgPaymentServiceProvider::where('source_id', $paymentServiceProviderData['orgPaymentServiceProvider']['source_id'])
                ->first()) {
                $orgPaymentServiceProvider = UpdateOrgPaymentServiceProvider::make()->action(
                    orgPaymentServiceProvider: $orgPaymentServiceProvider,
                    modelData: $paymentServiceProviderData['orgPaymentServiceProvider'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } else {
                $orgPaymentServiceProvider = StoreOrgPaymentServiceProvider::make()->action(
                    paymentServiceProvider: $paymentServiceProviderData['paymentServiceProvider'],
                    organisation: $organisationSource->getOrganisation(),
                    modelData: $paymentServiceProviderData['orgPaymentServiceProvider'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                $sourceId = explode(':', $orgPaymentServiceProvider->source_id);

                DB::connection('aurora')->table('Payment Service Provider Dimension')
                    ->where('Payment Service Provider Key', $sourceId[1])
                    ->update(['aiku_id' => $orgPaymentServiceProvider->id]);

                $this->saveMigrationHistory(
                    $orgPaymentServiceProvider,
                    Arr::except($paymentServiceProviderData['orgPaymentServiceProvider'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

            }

            return $orgPaymentServiceProvider;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Payment Service Provider Dimension')
            ->select('Payment Service Provider Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Payment Service Provider Dimension')->count();
    }
}
