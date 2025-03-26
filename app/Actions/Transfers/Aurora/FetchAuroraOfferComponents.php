<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:20:49 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Discounts\OfferComponent\StoreOfferComponent;
use App\Actions\Discounts\OfferComponent\UpdateOfferComponent;
use App\Models\Discounts\OfferComponent;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraOfferComponents extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:offer_components {organisations?*} {--s|source_id=} {--d|db_suffix=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new} ';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?OfferComponent
    {
        $offerComponentData = $organisationSource->fetchOfferComponent($organisationSourceId);
        if (!$offerComponentData) {
            return null;
        }
        $offerComponent = OfferComponent::withTrashed()->where('source_id', $offerComponentData['offerComponent']['source_id'])->first();
        if ($offerComponent) {
            try {
                $offerComponent = UpdateOfferComponent::make()->action(
                    offerComponent: $offerComponent,
                    modelData: $offerComponentData['offerComponent'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                $this->recordChange($organisationSource, $offerComponent->wasChanged());

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $offerComponent->source_id);
                DB::connection('aurora')->table('Deal Component Dimension')
                    ->where('Deal Component Key', $sourceData[1])
                    ->update(['aiku_id' => $offerComponent->id]);
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $offerComponentData['offerComponent'], 'OfferComponent', 'update');

                return null;
            }
        } else {
            try {
                $offerComponent = StoreOfferComponent::make()->action(
                    offer: $offerComponentData['offer'],
                    trigger: $offerComponentData['trigger'],
                    modelData: $offerComponentData['offerComponent'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                $this->recordNew($organisationSource);
                OfferComponent::enableAuditing();
                $this->saveMigrationHistory(
                    $offerComponent,
                    Arr::except($offerComponentData['offerComponent'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $offerComponent->source_id);
                DB::connection('aurora')->table('Deal Component Dimension')
                    ->where('Deal Component Key', $sourceData[1])
                    ->update(['aiku_id' => $offerComponent->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $offerComponentData['offerComponent'], 'Offer', 'store');

                return null;
            }
        }

        return $offerComponent;
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
            $query->where('eal Component Store Key', $sourceData[1]);
        }

        return $query;
    }

}
