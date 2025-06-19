<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 21 Oct 2022 09:32:47 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\ProductCategory\StoreProductCategory;
use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Helpers\Media\SaveModelImages;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraFamilies extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:families {organisations?*} {--N|only_new : Fetch only new}  {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?ProductCategory
    {
        $this->organisationSource = $organisationSource;
        if ($familyData = $organisationSource->fetchFamily($organisationSourceId)) {
            if ($family = ProductCategory::where('source_family_id', $familyData['family']['source_family_id'])
                ->first()) {
                try {
                    $shop      = $family->shop;
                    $modelData = [];

                    if ($shop->type == ShopTypeEnum::B2B) {
                        $modelData = $familyData['family'];
                    } elseif ($shop->type == ShopTypeEnum::DROPSHIPPING && !$family->image_id) {
                        $modelData = Arr::only($familyData['family'], ['image', 'fetched_at', 'last_fetched_at']);
                    }

                    $imageData = Arr::pull($modelData, 'image');

                    $family = UpdateProductCategory::make()->action(
                        productCategory: $family,
                        modelData: $modelData,
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );


                    if (isset($imageData['image_path']) and isset($imageData['filename'])) {
                        SaveModelImages::run(
                            $family,
                            [
                                'path'         => $imageData['image_path'],
                                'originalName' => $imageData['filename'],

                            ],
                            'photo',
                            'product_images'
                        );
                    }


                    $this->recordChange($organisationSource, $family->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $familyData['family'], 'Family', 'update');

                    return null;
                }
            } else {
                try {
                    $imageData = Arr::pull($familyData['family'], 'image');
                    if (isset($imageData['image_path']) and isset($imageData['filename'])) {
                        SaveModelImages::run(
                            $family,
                            [
                                'path'         => $imageData['image_path'],
                                'originalName' => $imageData['filename'],

                            ],
                            'photo',
                            'product_images'
                        );
                    }

                    $family = StoreProductCategory::make()->action(
                        parent: $familyData['parent'],
                        modelData: $familyData['family'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );


                    ProductCategory::enableAuditing();
                    $this->saveMigrationHistory(
                        $family,
                        Arr::except($familyData['family'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $family->source_family_id);
                    DB::connection('aurora')->table('Category Dimension')
                        ->where('Category Key', $sourceData[1])
                        ->update(['aiku_family_id' => $family->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $familyData['family'], 'Family', 'store');

                    return null;
                }
            }


            return $family;
        }


        return null;
    }


    public function getModelsQuery(): Builder
    {
        $familySourceIDs = [];
        $query           = DB::connection('aurora')
            ->table('Store Dimension')
            ->select('Store Family Category Key');
        foreach ($query->get() as $row) {
            $familySourceIDs[] = $row->{'Store Family Category Key'};
        }


        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->select('Category Key as source_id')
            ->where('Category Branch Type', 'Head');

        if ($this->onlyNew) {
            $query->whereNull('aiku_family_id');
        }

        return $query->whereIn('Category Root Key', $familySourceIDs)
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        $familySourceIDs = [];
        $query           = DB::connection('aurora')
            ->table('Store Dimension')
            ->select('Store Family Category Key');
        foreach ($query->get() as $row) {
            $familySourceIDs[] = $row->{'Store Family Category Key'};
        }


        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->where('Category Branch Type', 'Head');
        if ($this->onlyNew) {
            $query->whereNull('aiku_family_id');
        }

        return $query->whereIn('Category Root Key', $familySourceIDs)
            ->count();
    }
}
