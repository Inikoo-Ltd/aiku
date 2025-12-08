<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 10:44:01 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\Favourite\StoreFavourite;
use App\Actions\CRM\Favourite\UpdateFavourite;
use App\Models\CRM\Favourite;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraFavourites extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:favourites {organisations?*} {--S|shop= : Shop slug} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Favourite
    {
        if ($favouriteData = $organisationSource->fetchFavourite($organisationSourceId)) {
            if (empty($favouriteData['favourite'])) {
                return null;
            }

            if ($favourite = Favourite::where('source_id', $favouriteData['favourite']['source_id'])
                ->first()) {
                try {
                    $favourite = UpdateFavourite::make()->action(
                        favourite: $favourite,
                        modelData: $favouriteData['favourite'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $favourite->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $favouriteData['favourite'], 'Favourite', 'update');

                    return null;
                }
            } else {
                data_set($modelData, 'parent_id', $favouriteData['favourite'], overwrite: false);
                try {
                    $favourite = StoreFavourite::make()->action(
                        customer: $favouriteData['customer'],
                        product: $favouriteData['product'],
                        modelData: $favouriteData['favourite'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false
                    );

                    $sourceData = explode(':', $favourite->source_id);
                    DB::connection('aurora')->table('Customer Favourite Product Fact')
                        ->where('Customer Favourite Product Key', $sourceData[1])
                        ->update(['aiku_id' => $favourite->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $favouriteData['favourite'], 'Favourite', 'store');

                    return null;
                }
            }

            return $favourite;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Customer Favourite Product Fact')
            ->select('Customer Favourite Product Key as source_id')
            ->orderBy('Customer Favourite Product Creation Date');

        if ($this->onlyNew) {
            $query->whereNull('Customer Favourite Product Fact.aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Favourite Product Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Customer Favourite Product Fact');

        if ($this->onlyNew) {
            $query->whereNull('Customer Favourite Product Fact.aiku_id');
        }
        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Customer Favourite Product Store Key', $sourceData[1]);
        }

        return $query->count();
    }
}
