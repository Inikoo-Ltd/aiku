<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Dec 2024 03:50:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Media\SaveModelImages;
use App\Actions\Masters\MasterAsset\StoreMasterAsset;
use App\Actions\Masters\MasterAsset\UpdateMasterAsset;
use App\Models\Goods\MasterAsset;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraMasterAssets extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:master_assets {organisations?*} {--s|source_id=}  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?MasterAsset
    {
        $masterAssetData = $organisationSource->fetchMasterAsset($organisationSourceId);


        if (!$masterAssetData) {
            return null;
        }

        $sourceData = explode(':', $masterAssetData['master_asset']['source_id']);
        $stocks  = $organisationSource->fetchMasterAssetHasStock($sourceData[1])['stocks'];

        data_set(
            $masterAssetData,
            'master_asset.stocks',
            $stocks
        );

        /** @var MasterAsset $masterAsset */
        $masterAsset = MasterAsset::withTrashed()->where('source_id', $masterAssetData['master_asset']['source_id'])->first();


        if ($masterAsset) {
            // try {
            $masterAsset = UpdateMasterAsset::make()->action(
                masterAsset: $masterAsset,
                modelData: $masterAssetData['master_asset'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
                audit: false
            );
            //            } catch (Exception|Throwable $e) {
            //                $this->recordError($organisationSource, $e, $masterAssetData['master_asset'], 'MasterAsset', 'update');
            //
            //                return null;
            //            }
        } else {
            // try {
            $masterAsset = StoreMasterAsset::make()->action(
                parent: $masterAssetData['parent'],
                modelData: $masterAssetData['master_asset'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
                audit: false
            );

            MasterAsset::enableAuditing();
            $this->saveMigrationHistory(
                $masterAsset,
                Arr::except($masterAssetData['master_asset'], ['fetched_at', 'last_fetched_at'])
            );

            $this->recordNew($organisationSource);


            //            } catch (Exception|Throwable $e) {
            //                $this->recordError($organisationSource, $e, $masterAssetData['master_asset'], 'MasterAsset', 'store');
            //
            //                return null;
            //            }
        }

        $sourceData = explode(':', $masterAsset->source_id);


        if (count($masterAssetData['master_asset']['images']) > 0) {
            foreach ($masterAssetData['master_asset']['images'] as $imageData) {
                if (isset($imageData['image_path']) and isset($imageData['filename'])) {
                    try {
                        SaveModelImages::run(
                            model:$masterAsset,
                            imageData:[
                                'path'         => $imageData['image_path'],
                                'originalName' => $imageData['filename'],

                            ],
                            scope:'photo',
                            mediaScope:'master_asset_images'
                        );
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $imageData, 'Image', 'store');
                    }
                }
            }
        }


        foreach (
            DB::connection('aurora')
                ->table('Product Dimension')
                ->where('Product Type', 'Product')
                ->where('is_variant', 'Yes')
                ->where('variant_parent_id', $sourceData[1])
                ->select('Product ID as source_id')
                ->orderBy('Product ID')->get() as $productVariantData
        ) {
            FetchAuroraMasterAssets::run($organisationSource, $productVariantData->source_id);
        }


        return $masterAsset;
    }

    public function getModelsQuery(): Builder
    {

        $query = $this->commonSelectModelsToFetch();

        $query->select('Product ID as source_id')
            ->orderBy('Product Valid From');

        return $query;
    }

    public function count(): ?int
    {
        $query = $this->commonSelectModelsToFetch();

        return $query->count();
    }


    public function commonSelectModelsToFetch(): Builder
    {
        return DB::connection('aurora')
            ->table('Product Dimension')
            ->where('Product Type', 'Product')
            ->whereNull('Product Customer Key');
    }


}
