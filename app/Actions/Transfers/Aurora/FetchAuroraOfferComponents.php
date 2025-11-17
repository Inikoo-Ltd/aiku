<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:20:49 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Discounts\OfferAllowance\StoreOfferAllowance;
use App\Actions\Discounts\OfferAllowance\UpdateOfferAllowance;
use App\Models\Discounts\OfferAllowance;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraOfferComponents extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:offer_components {organisations?*} {--s|source_id=} {--d|db_suffix=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new} ';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?OfferAllowance
    {
        $offerAllowanceData = $organisationSource->fetchOfferComponent($organisationSourceId);
        if (!$offerAllowanceData) {
            return null;
        }
        $offerAllowance = OfferAllowance::withTrashed()->where('source_id', $offerAllowanceData['offerAllowance']['source_id'])->first();
        if ($offerAllowance) {
            try {
                $offerAllowance = UpdateOfferAllowance::make()->action(
                    offerAllowance: $offerAllowance,
                    modelData: $offerAllowanceData['offerAllowance'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $offerAllowance->wasChanged());

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $offerAllowance->source_id);
                DB::connection('aurora')->table('Deal Component Dimension')
                    ->where('Deal Component Key', $sourceData[1])
                    ->update(['aiku_id' => $offerAllowance->id]);
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $offerAllowanceData['offerAllowance'], 'OfferAllowance', 'update');

                return null;
            }
        } else {
            try {
                $offerAllowance = StoreOfferAllowance::make()->action(
                    offer: $offerAllowanceData['offer'],
                    modelData: $offerAllowanceData['offerAllowance'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                $this->recordNew($organisationSource);
                OfferAllowance::enableAuditing();
                $this->saveMigrationHistory(
                    $offerAllowance,
                    Arr::except($offerAllowanceData['offerAllowance'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $offerAllowance->source_id);
                DB::connection('aurora')->table('Deal Component Dimension')
                    ->where('Deal Component Key', $sourceData[1])
                    ->update(['aiku_id' => $offerAllowance->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $offerAllowanceData['offerAllowance'], 'Offer', 'store');

                return null;
            }
        }

        return $offerAllowance;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Deal Component Dimension')
            ->select('Deal Component Key as source_id');

        $query = $this->commonSelectModelsToFetch($query);

        return $query->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Deal Component Dimension');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->count();
    }

    public function commonSelectModelsToFetch($query)
    {
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Deal Component Store Key', $sourceData[1]);
        }

        return $query;
    }

}
