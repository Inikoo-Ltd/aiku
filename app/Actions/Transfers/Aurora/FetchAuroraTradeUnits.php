<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:28:55 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
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



                $this->fetchTradeUnitProductPropertiesInfo(
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

    public function fetchTradeUnitProductPropertiesInfo(TradeUnit $tradeUnit): void
    {
        foreach ($tradeUnit->products()->where('unit_relationship_type', ProductUnitRelationshipType::SINGLE)->where('products.organisation_id', $this->organisation->id)->get() as $product) {
            if ($product->shop->state !== ShopStateEnum::OPEN) {
                continue;
            }

            $dangerousGoodsInfo = $this->fetchAuroraProductPropertiesInfo($product);

            if ($this->organisation->id == 1) {
                UpdateTradeUnit::make()->action(
                    tradeUnit: $tradeUnit,
                    modelData: $dangerousGoodsInfo,
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
            } else {
                $filteredDangerousGoodsInfo = array_filter($dangerousGoodsInfo, function ($value) {
                    return !is_null($value);
                });

                // Filter dangerous goods info to only update fields that are currently null
                $fieldsToUpdate = [];
                foreach ($filteredDangerousGoodsInfo as $field => $value) {
                    if (is_null($tradeUnit->$field)) {
                        $fieldsToUpdate[$field] = $value;
                    }
                }

                if (!empty($fieldsToUpdate)) {
                    UpdateTradeUnit::make()->action(
                        tradeUnit: $tradeUnit,
                        modelData: $fieldsToUpdate,
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                }
            }
        }
    }

    public function fetchAuroraProductPropertiesInfo(Product $product): array
    {
        $sourceData = $product->source_id;
        if (!$sourceData) {
            return [];
        }
        $sourceData = explode(':', $sourceData);

        $productPropertiesInfo = DB::connection('aurora')->table('Product Dimension')
            ->where('Product Id', $sourceData[1])
            ->first();

        if (!$productPropertiesInfo) {
            return [];
        }

        $dutyRate = $productPropertiesInfo->{'Product Duty Rate'} ?? null;
        // Clean the duty rate string to remove any non-ASCII characters or encoding issues
        if ($dutyRate) {
            // Convert to UTF-8 and remove any invalid characters
            $dutyRate = mb_convert_encoding($dutyRate, 'UTF-8', 'UTF-8');
            // Replace any remaining non-ASCII characters with their closest ASCII equivalent or remove them
            $dutyRate = preg_replace('/[^\x20-\x7E]/', '', $dutyRate);
        }


        return [
            'un_number'                    => $productPropertiesInfo->{'Product UN Number'} ?? null,
            'un_class'                     => $productPropertiesInfo->{'Product UN Class'} ?? null,
            'packing_group'                => $productPropertiesInfo->{'Product Packing Group'} ?? null,
            'proper_shipping_name'         => $productPropertiesInfo->{'Product Proper Shipping Name'} ?? null,
            'hazard_identification_number' => $productPropertiesInfo->{'Product Hazard Identification Number'} ?? null,
            'gpsr_manufacturer'            => $productPropertiesInfo->{'Product GPSR Manufacturer'} ?? null,
            'gpsr_eu_responsible'          => $productPropertiesInfo->{'Product GPSR EU Responsible'} ?? null,
            'gpsr_warnings'                => $productPropertiesInfo->{'Product GPSR Warnings'} ?? null,
            'gpsr_manual'                  => $productPropertiesInfo->{'Product GPSR Manual'} ?? null,
            'gpsr_class_category_danger'   => $productPropertiesInfo->{'Product GPSR Class Category Danger'} ?? null,
            'gpsr_class_languages'         => $productPropertiesInfo->{'Product GPSR Class Languages'} ?? null,
            'pictogram_toxic'              => $productPropertiesInfo->{'Product Pictogram Toxic'} == 'Yes',
            'pictogram_corrosive'          => $productPropertiesInfo->{'Product Pictogram Corrosive'} == 'Yes',
            'pictogram_explosive'          => $productPropertiesInfo->{'Product Pictogram Explosive'} == 'Yes',
            'pictogram_flammable'          => $productPropertiesInfo->{'Product Pictogram Flammable'} == 'Yes',
            'pictogram_gas'                => $productPropertiesInfo->{'Product Pictogram Gas'} == 'Yes',
            'pictogram_environment'        => $productPropertiesInfo->{'Product Pictogram Environment'} == 'Yes',
            'pictogram_health'             => $productPropertiesInfo->{'Product Pictogram Health'} == 'Yes',
            'pictogram_oxidising'          => $productPropertiesInfo->{'Product Pictogram Oxidising'} == 'Yes',
            'pictogram_danger'             => $productPropertiesInfo->{'Product Pictogram Danger'} == 'Yes',
            'cpnp_number'                  => $productPropertiesInfo->{'Product CPNP Number'} ?? null,
            'country_of_origin'            => $productPropertiesInfo->{'Product Origin Country Code'} ?? null,
            'tariff_code'                  => $productPropertiesInfo->{'Product Tariff Code'} ?? null,
            'duty_rate'                    => $dutyRate,
            'hts_us'                       => $productPropertiesInfo->{'Product HTSUS Code'} ?? null,
        ];
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
