<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Fri, 02 Sept 2022 14:38:02 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\Product\StoreProduct;
use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Helpers\Media\SaveModelImages;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraProducts extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:products {organisations?*} {--s|source_id=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new}  {--d|db_suffix=} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Product
    {
        $productData = $organisationSource->fetchProduct($organisationSourceId);


        if (!$productData) {
            return null;
        }

        $sourceData = explode(':', $productData['product']['source_id']);
        $orgStocks  = $organisationSource->fetchProductHasOrgStock($sourceData[1])['org_stocks'];

        data_set(
            $productData,
            'product.org_stocks',
            $orgStocks
        );

        /** @var Product $product */
        if ($product = Product::withTrashed()->where('source_id', $productData['product']['source_id'])->first()) {
            try {
                if ($productData['family']) {
                    if ($product->shop->type != ShopTypeEnum::DROPSHIPPING) {
                        $productData['product']['family_id'] = $productData['family']->id;
                    } elseif (!$product->family) {
                        $productData['product']['family_id'] = $productData['family']->id;
                    }
                }


                $product = UpdateProduct::make()->action(
                    product: $product,
                    modelData: $productData['product'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $productData['product'], 'Product', 'update');

                return null;
            }
        } else {
            try {
                $product = StoreProduct::make()->action(
                    parent: $productData['parent'],
                    modelData: $productData['product'],
                    hydratorsDelay: 120,
                    strict: false,
                    audit: false
                );

                Product::enableAuditing();
                $this->saveMigrationHistory(
                    $product,
                    Arr::except($productData['product'], ['fetched_at', 'last_fetched_at'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $product->source_id);

                DB::connection('aurora')->table('Product Dimension')
                    ->where('Product ID', $sourceData[1])
                    ->update(['aiku_id' => $product->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $productData['product'], 'Product', 'store');

                return null;
            }
        }

        $sourceData = explode(':', $product->source_id);


        if (count($productData['product']['images']) > 0) {
            foreach ($productData['product']['images'] as $imageData) {
                if (isset($imageData['image_path']) and isset($imageData['filename'])) {
                    try {
                        SaveModelImages::run(
                            $product,
                            [
                                'path'         => $imageData['image_path'],
                                'originalName' => $imageData['filename'],

                            ],
                            'photo',
                            'product_images'
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
            FetchAuroraProducts::run($organisationSource, $productVariantData->source_id);
        }


        return $product;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Product Dimension')
            ->where('Product Type', 'Product')
            ->select('Product ID as source_id')
            ->orderBy('Product Valid From');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Product Store Key', $sourceData[1]);
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Product Dimension')
            //    ->where('is_variant', 'No')
            ->where('Product Type', 'Product');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Product Store Key', $sourceData[1]);
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Product Dimension')->update(['aiku_id' => null]);
    }
}
