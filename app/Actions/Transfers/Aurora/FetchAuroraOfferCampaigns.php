<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 13:56:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Discounts\OfferCampaign\UpdateOfferCampaign;
use App\Models\Discounts\OfferCampaign;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraOfferCampaigns extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:offer_campaigns {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?OfferCampaign
    {
        $offerCampaignData = $organisationSource->fetchOfferCampaign($organisationSourceId);
        if (! $offerCampaignData) {
            return null;
        }

        $shop = $offerCampaignData['shop'];
        $offerCampaign = $shop->offerCampaigns()->where('source_id', $offerCampaignData['offer-campaign']['source_id'])->first();
        if (! $offerCampaign) {
            $offerCampaign = $shop->offerCampaigns()->where('type', $offerCampaignData['type'])->first();
            unset($offerCampaignData['offer-campaign']['last_fetched_at']);
        } else {
            unset($offerCampaignData['offer-campaign']['fetched_at']);
        }

        if ($offerCampaign) {
            try {
                $offerCampaign = UpdateOfferCampaign::make()->action(
                    offerCampaign: $offerCampaign,
                    modelData: $offerCampaignData['offer-campaign'],
                    strict: false
                );

                if (! $offerCampaign->fetched_at) {
                    $offerCampaign->updateQuietly(
                        [
                            'fetched_at' => now(),
                            'source_id' => $offerCampaignData['offer-campaign']['source_id'],
                        ]
                    );
                    OfferCampaign::enableAuditing();
                    $this->saveMigrationHistory(
                        $offerCampaign,
                        Arr::except($offerCampaignData['offer-campaign'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );
                }

                $this->recordChange($organisationSource, $offerCampaign->wasChanged());
                $sourceData = explode(':', $offerCampaign->source_id);
                DB::connection('aurora')->table('Deal Campaign Dimension')
                    ->where('Deal Campaign Key', $sourceData[1])
                    ->update(['aiku_id' => $offerCampaign->id]);
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $offerCampaignData['offer-campaign'], 'OfferCampaign', 'update');

                return null;
            }
        }

        return $offerCampaign;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Deal Campaign Dimension')
            ->select('Deal Campaign Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Deal Campaign Dimension')->count();
    }
}
