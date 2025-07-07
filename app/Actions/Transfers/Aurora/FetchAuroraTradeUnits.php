<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:28:55 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\TradeUnit\Hydrators\TradeUnitHydrateImages;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Helpers\Media\SaveModelImages;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraImages;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraTradeUnits extends FetchAuroraAction
{
    use WithAuroraParsers;
    use WithAuroraImages;

    public string $commandSignature = 'fetch:trade_units {organisations?*} {--s|source_id=} {--d|db_suffix=}';
    private Organisation $organisation;


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?TradeUnit
    {
        $this->organisationSource = $organisationSource;


        $organisation       = $organisationSource->getOrganisation();
        $this->organisation = $organisation;

        $tradeUnitData = $organisationSource->fetchTradeUnit($organisationSourceId);


        if ($tradeUnitData) {
            if ($metaTradeUnit = TradeUnit::withTrashed()->where('source_slug', $tradeUnitData['trade_unit']['source_slug'])->first()) {
                if ($tradeUnit = TradeUnit::withTrashed()->where('source_id', $tradeUnitData['trade_unit']['source_id'])->first()) {
                    try {
                        $tradeUnit = UpdateTradeUnit::make()->action(
                            tradeUnit: $tradeUnit,
                            modelData: $tradeUnitData['trade_unit'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                            audit: false
                        );
                        $this->recordChange($organisationSource, $tradeUnit->wasChanged());
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $tradeUnitData['trade_unit'], 'TradeUnit', 'update');

                        return null;
                    }
                }


                if ($organisation->id == 2) {
                    $dataToUpdate = Arr::only(
                        $tradeUnitData['trade_unit'],
                        ['gross_weight', 'marketing_weight', 'marketing_dimensions']
                    );

                    if (!Arr::get($tradeUnitData, 'trade_unit.gross_weight')) {
                        data_forget($tradeUnitData, 'trade_unit.gross_weight');
                    }
                    if (!Arr::get($tradeUnitData, 'trade_unit.marketing_weight')) {
                        data_forget($tradeUnitData, 'trade_unit.marketing_weight');
                    }
                    if (!Arr::get($tradeUnitData, 'trade_unit.marketing_dimensions')) {
                        data_forget($tradeUnitData, 'trade_unit.marketing_dimensions');
                    }


                    $tradeUnit = UpdateTradeUnit::make()->action(
                        tradeUnit: $metaTradeUnit,
                        modelData: $dataToUpdate,
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                }
            } else {
                try {
                    $tradeUnit = StoreTradeUnit::make()->action(
                        group: $organisationSource->getOrganisation()->group,
                        modelData: $tradeUnitData['trade_unit'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    TradeUnit::enableAuditing();
                    $this->saveMigrationHistory(
                        $tradeUnit,
                        Arr::except($tradeUnitData['trade_unit'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $tradeUnitData['trade_unit'], 'TradeUnit', 'store');

                    return null;
                }
            }


            if (!$tradeUnit) {
                $tradeUnit = TradeUnit::withTrashed()->where('source_slug', $tradeUnitData['trade_unit']['source_slug'])->first();
            }

            if ($tradeUnit) {
                $this->updateTradeUnitSources($tradeUnit, $tradeUnitData['trade_unit']['source_id']);

                if (isset($tradeUnitData['barcodes']) && count($tradeUnitData['barcodes']) > 0) {
                    $tradeUnit->barcodes()->sync(
                        $tradeUnitData['barcodes']
                    );

                    $barcodeId     = null;
                    $barcodeNumber = null;

                    foreach ($tradeUnitData['barcodes'] as $barcodeKey => $barcodeData) {
                        if ($barcodeData['status']) {
                            /** @var Barcode $barcode */
                            $barcode = Barcode::find($barcodeKey);

                            $barcodeId     = $barcode->id;
                            $barcodeNumber = $barcode->number;
                            break;
                        }
                    }

                    $tradeUnit->updateQuietly([
                        'barcode_id' => $barcodeId,
                        'barcode'    => $barcodeNumber,
                    ]);
                }

                // trust only organisation 2 for ingredients
                if ($organisation->id == 2) {
                    $ingredientsToDelete = $tradeUnit->ingredients()->pluck('trade_unit_has_ingredients.ingredient_id')->toArray();

                    $dataSource  = explode(':', $tradeUnit->source_id);
                    $ingredients = [];
                    foreach (
                        DB::connection('aurora')->table('Part Material Bridge')
                            ->where('Part SKU', $dataSource[1])->get() as $auroraIngredients
                    ) {
                        $ingredient = $this->parseIngredient(
                            $organisation->id.
                            ':'.$auroraIngredients->{'Material Key'}
                        );
                        if ($ingredient) {
                            $ingredientsToDelete = array_diff($ingredientsToDelete, [$ingredient->id]);

                            $ingredientSourceID = $organisation->id.':'.$auroraIngredients->{'Material Key'};

                            $arguments                    = Arr::get($ingredient->source_data, 'trade_unt_args.'.$ingredientSourceID, []);
                            $ingredients[$ingredient->id] = $arguments;
                        }


                        $tradeUnit->ingredients()->syncWithoutDetaching($ingredients);
                    }


                    $tradeUnit->ingredients()->whereIn('ingredient_id', array_keys($ingredientsToDelete))->forceDelete();
                }


                // now let process the product images
                $this->fetchTradeUnitImages(
                    $tradeUnit,
                );
            }


            return $tradeUnit;
        }


        return null;
    }


    public function updateTradeUnitSources(TradeUnit $tradeUnit, string $source): void
    {
        $sources   = Arr::get($tradeUnit->sources, 'parts', []);
        $sources[] = $source;
        $sources   = array_unique($sources);

        $tradeUnit->updateQuietly([
            'sources' => [
                'parts' => $sources,
            ]
        ]);
    }

    public function fetchTradeUnitImages(TradeUnit $tradeUnit): void
    {
        $images = [];
      //  print "Fetching images for trade unit: {$tradeUnit->slug} {$tradeUnit->code}  {$tradeUnit->name} ({$tradeUnit->source_id})=================\n";
        /** @var Product $product */
        foreach ($tradeUnit->products()->where('unit_relationship_type',ProductUnitRelationshipType::SINGLE)->where('products.organisation_id', $this->organisation->id)->get() as $product) {

            if($product->shop->state!==ShopStateEnum::OPEN){
                continue;
            }

          //  print "Fetching images for product: {$product->slug} {$product->code}  {$product->name} ({$product->source_id})\n";
            $productImages = $this->fetchAuroraProductImages($product);
            foreach ($productImages as $productImage) {
                if (!isset($productImage['checksum']) || !$productImage['checksum']) {
                    continue;
                }
               // print_r($productImage);
                $images[$productImage['checksum']] = $productImage;
            }
        }

        foreach ($images as $imageData) {
            SaveModelImages::run(
                model: $tradeUnit,
                mediaData: [
                    'path'         => $imageData['image_path'],
                    'originalName' => $imageData['filename'],

                ],
                mediaScope: 'product_images',
                modelHasMediaData: [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'fetched_at' => now(),
                    'source_id'  => $imageData['source_id'],
                    'scope'      => 'photo',
                    'is_public'  => $imageData['is_public'],
                    'caption'    => $imageData['caption'] ?? '',
                    'position'   => $this->organisation->id * 1000 + $imageData['position'],

                ]
            );
        }

        TradeUnitHydrateImages::run($tradeUnit);
    }

    private function fetchAuroraProductImages(Product $product): array
    {
        $sourceData = $product->source_id;
        if (!$sourceData) {
            return [];
        }
        $sourceData = explode(':', $sourceData);

        $images = $this->getModelImagesCollection(
            'Product',
            $sourceData[1]
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });

        return $images->toArray();
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Part Dimension')
            ->select('Part SKU as source_id');

        $query->orderBy('Part Valid From');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Part Dimension');

        return $query->count();
    }


}
