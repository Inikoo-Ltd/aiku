<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 19:51:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\UpdateOffer;
use App\Models\Discounts\Offer;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraOffers extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:offers {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Offer
    {
        $offerData = $organisationSource->fetchOffer($organisationSourceId);
        if (!$offerData) {
            return null;
        }

        if ($offer = Offer::withTrashed()->where('source_id', $offerData['offer']['source_id'])
            ->first()) {
            try {
                $offer = UpdateOffer::make()->action(
                    offer: $offer,
                    modelData: $offerData['offer'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $offer->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $offerData['offer'], 'Offer', 'update');

                return null;
            }
        } else {
            try {
                $offer = StoreOffer::make()->action(
                    offerCampaign: $offerData['offer_campaign'],
                    modelData: $offerData['offer'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                $this->recordNew($organisationSource);
                Offer::enableAuditing();
                $this->saveMigrationHistory(
                    $offer,
                    Arr::except($offerData['offer'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $offer->source_id);
                DB::connection('aurora')->table('Deal Dimension')
                    ->where('Deal Key', $sourceData[1])
                    ->update(['aiku_id' => $offer->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $offerData['offer'], 'Offer', 'store');

                return null;
            }
        }


        return $offer;

    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Deal Dimension')
            ->select('Deal Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Deal Dimension')->count();
    }


}
