<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Jun 2025 17:33:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\Collection\AttachModelToCollection;
use App\Actions\Catalogue\Collection\DetachModelFromCollection;
use App\Actions\Catalogue\Collection\StoreCollection;
use App\Actions\Catalogue\Collection\UpdateCollection;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraCollections extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:collections {organisations?*} {--N|only_new : Fetch only new}  {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Collection
    {
        $collectionData = $organisationSource->fetchCollection($organisationSourceId);

        if ($collectionData) {
            if ($collection = Collection::where('source_id', $collectionData['collection']['source_id'])
                ->first()) {
                try {
                    $collection = UpdateCollection::make()->action(
                        collection: $collection,
                        modelData: $collectionData['collection'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $collection->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $collectionData['collection'], 'Collection', 'update');

                    return null;
                }
            } else {
                // try {
                $collection = StoreCollection::make()->action(
                    parent: $collectionData['shop'],
                    modelData: $collectionData['collection'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false
                );
                Collection::enableAuditing();
                $this->saveMigrationHistory(
                    $collection,
                    Arr::except($collectionData['collection'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $collection->source_id);

                DB::connection('aurora')->table('Category Dimension')
                    ->where('Category Key', $sourceData[1])
                    ->update(['aiku_id' => $collection->id]);
                //                } catch (Exception|Throwable $e) {
                //                    $this->recordError($organisationSource, $e, $collectionData['collection'], 'Collection', 'store');
                //
                //                    return null;
                //                }
            }


            $productsDelete = $collection->products()->where('type', 'direct')->pluck('model_id')->all();
            $familiesDelete = $collection->families()->pluck('model_id')->all();

            foreach (
                $collectionData['models'] as $model
            ) {
                if ($model instanceof Product) {
                    if (!DB::table('collection_has_models')->where('collection_id', $collection->id)->where('model_type', 'Product')->where('model_id', $model->id)->exists()) {



                        AttachModelToCollection::make()->action(
                            collection: $collection,
                            model: $model,
                        );
                    }
                } elseif (!DB::table('collection_has_models')->where('collection_id', $collection->id)->where('model_type', 'ProductCategory')->where('model_id', $model->id)->exists()) {
                    AttachModelToCollection::make()->action(
                        collection: $collection,
                        model: $model,
                    );

                }


                if ($model instanceof Product) {
                    $productsDelete = array_diff($productsDelete, [$model->id]);
                } else {
                    $familiesDelete = array_diff($productsDelete, [$model->id]);
                }
            }


            foreach ($productsDelete as $productDelete) {
                $product = Product::find($productDelete);
                DetachModelFromCollection::make()->action($collection, $product);
            }

            foreach ($familiesDelete as $familyDelete) {
                $family = ProductCategory::find($familyDelete);
                DetachModelFromCollection::make()->action($collection, $family);
            }


            return $collection;
        }


        return null;
    }


    public function getModelsQuery(): Builder
    {
        $collectionsRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->get()->pluck('Category Key')->toArray();

        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->select('Category Key as source_id')
            ->where('Category Branch Type', 'Head')
            ->whereIn('Category Root Key', $collectionsRootAuroraIDs);

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }


        return $query->orderBy('source_id');
    }

    public function count(): ?int
    {
        $collectionsRootAuroraIDs = DB::connection('aurora')->table('Category Dimension')
            ->select('Category Key', 'Category Code', 'Category Subject')
            ->where('Category Branch Type', 'Root')
            ->where('Category Scope', 'Product')
            ->where('Category Code', 'like', 'Web.%')
            ->get()->pluck('Category Key')->toArray();


        $query = DB::connection('aurora')
            ->table('Category Dimension')
            ->where('Category Branch Type', 'Head')
            ->whereIn('Category Root Key', $collectionsRootAuroraIDs);

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }


}
