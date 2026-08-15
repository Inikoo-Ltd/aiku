<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:28:55 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\Product\CloneProductAttachmentsFromTradeUnits;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Enums\Catalogue\Product\ProductUnitRelationshipType;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Catalogue\Product;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use Exception;
use Throwable;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\Aurora\WithAuroraImages;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraTradeUnits extends FetchAuroraAction
{
    use WithAuroraParsers;
    use WithAuroraImages;
    use WithAuroraAttachments;

    public string $commandSignature = 'fetch:trade_units {organisations?*} {--s|source_id=} {--d|db_suffix=}';
    private Organisation $organisation;


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?TradeUnit
    {
        $this->organisationSource = $organisationSource;


        $organisation       = $organisationSource->getOrganisation();
        $this->organisation = $organisation;

        $tradeUnitData = $organisationSource->fetchTradeUnit($organisationSourceId);

        if ($tradeUnitData) {
            $tradeUnit     = null;
            $metaTradeUnit = TradeUnit::withTrashed()->where('source_slug', $tradeUnitData['trade_unit']['source_slug'])->first();
            if ($metaTradeUnit) {
                // Trade units carry group_id only: every organisation reads the same row
                // and staff maintain them in aiku. Aurora may still send one aiku has
                // never seen, it may not rewrite an existing one.
                $tradeUnit = TradeUnit::withTrashed()->where('source_id', $tradeUnitData['trade_unit']['source_id'])->first();


                // Ingredients are maintained in aiku for every organisation now. Aurora
                // used to be trusted for them via sk, which deleted and rewrote the whole
                // trade_unit_has_ingredients set on each fetch.

                if ($tradeUnit) {
                    $this->fetchTradeUnitProductPropertiesInfo(
                        $tradeUnit,
                    );
                    $this->processFetchAttachments($tradeUnit, 'Part', $tradeUnitData['trade_unit']['source_id']);
                }
            } else {
                // Create-only path for parts born in Aurora after the cutover: aiku has
                // never seen this source_slug, so nothing staff maintain can be rewritten.
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

                    $this->updateTradeUnitSources($tradeUnit, $tradeUnitData['trade_unit']['source_id']);

                    if (count(Arr::get($tradeUnitData, 'barcodes', [])) > 0) {
                        $tradeUnit->barcodes()->sync($tradeUnitData['barcodes']);

                        foreach ($tradeUnitData['barcodes'] as $barcodeKey => $barcodeData) {
                            if ($barcodeData['status']) {
                                $barcode = Barcode::find($barcodeKey);
                                $tradeUnit->updateQuietly([
                                    'barcode_id' => $barcode->id,
                                    'barcode'    => $barcode->number,
                                ]);
                                break;
                            }
                        }
                    }

                    $this->processFetchAttachments($tradeUnit, 'Part', $tradeUnitData['trade_unit']['source_id']);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $tradeUnitData['trade_unit'], 'TradeUnit', 'store');

                    return null;
                }
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

            // Fill gaps only, for every organisation. aw used to overwrite these
            // outright, which is the one way Aurora could still rewrite a group level
            // trade unit that staff had already corrected.
            $filteredDangerousGoodsInfo = array_filter($dangerousGoodsInfo, function ($value) {
                return !is_null($value);
            });

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


        $data = [
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
            'tariff_code'                  => $productPropertiesInfo->{'Product Tariff Code'} ?? null,
            'duty_rate'                    => $dutyRate,
            'hts_us'                       => $productPropertiesInfo->{'Product HTSUS Code'} ?? null,
        ];

        if ($productPropertiesInfo->{'Product Origin Country Code'} != '') {
            $data['origin_country_id'] = $this->parseCountryOrigin($productPropertiesInfo->{'Product Origin Country Code'});
        }

        return $data;
    }


    public function parseCountryOrigin(?string $countryOrigin): ?int
    {
        if (!$countryOrigin || is_numeric($countryOrigin)) {
            return null;
        }

        $countryOrigin = Str::upper($countryOrigin);

        if ($countryOrigin == 'UK' || $countryOrigin == 'GB') {
            $countryOrigin = 'GBR';
        }

        if ($countryOrigin == 'CHI' || $countryOrigin == 'CNY') {
            $countryOrigin = 'CHN';
        }

        if ($countryOrigin == 'IDR' || $countryOrigin == 'IDO') {
            $countryOrigin = 'IDN';
        }

        $country = null;
        if (strlen($countryOrigin) == 3) {
            $country = Country::where('iso3', $countryOrigin)->first();
        }

        //        if (!$country) {
        //            print "\nXXXXX-->".$countryOrigin.'<--\n';
        //        }

        return $country?->id;
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

    protected function processFetchAttachments(TradeUnit $tradeUnit, string $modelType, string $modelSourceID): void
    {
        if (!$tradeUnit) {
            return;
        }
        $attachmentModelType = 'TradeUnit';

        $modelSourceIDData = explode(':', $modelSourceID);
        if (Arr::get($modelSourceIDData, 0) != '1') {
            return;
        }


        foreach ($this->parseAttachments($modelSourceID, $modelType) as $attachmentData) {
            if ($attachmentData === null) {
                continue;
            }

            $scope = $this->parseTradeUnitAttachmentScope($attachmentData['modelData']);

            if (!$attachmentData['is_public']) {
                $scope = match ($scope) {
                    TradeAttachmentScopeEnum::IFRA_PRIVATE => TradeAttachmentScopeEnum::IFRA,
                    TradeAttachmentScopeEnum::SDS_PRIVATE => TradeAttachmentScopeEnum::SDS,
                    TradeAttachmentScopeEnum::MSDS_PRIVATE => TradeAttachmentScopeEnum::MSDS,
                    TradeAttachmentScopeEnum::CLP_PRIVATE => TradeAttachmentScopeEnum::CLP,
                    TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS_PRIVATE => TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS,
                    TradeAttachmentScopeEnum::DOC_PRIVATE => TradeAttachmentScopeEnum::DOC,
                    TradeAttachmentScopeEnum::CPSR_PRIVATE => TradeAttachmentScopeEnum::CPSR,
                    TradeAttachmentScopeEnum::OTHER_PRIVATE => TradeAttachmentScopeEnum::OTHER,
                    default => $scope,
                };
            }

            data_set($attachmentData['modelData'], 'scope', $scope->value);

            $media = SaveModelAttachment::make()->action(
                model: $tradeUnit,
                modelData: $attachmentData['modelData'],
                hydratorsDelay: 30,
                strict: false
            );

            $modelAttachment = $tradeUnit->attachments()->where('media_id', $media->id)->first();


            $sources = json_decode($modelAttachment->pivot->sources, true);

            $bridgeSources     = Arr::get($sources, 'bridge', []);
            $bridgeSources[]   = $attachmentData['modelData']['source_id'];
            $bridgeSources     = array_unique($bridgeSources);
            $sources['bridge'] = $bridgeSources;

            $modelSources                  = Arr::get($sources, $attachmentModelType, []);
            $modelSources[]                = $tradeUnit->source_id;
            $modelSources                  = array_unique($modelSources);
            $sources[$attachmentModelType] = $modelSources;

            $tradeUnit->attachments()->updateExistingPivot(
                $media->id,
                [
                    "sources" =>
                        json_encode($sources)

                ]
            );

            foreach ($tradeUnit->products as $product) {
                CloneProductAttachmentsFromTradeUnits::run($product);
            }
        }
    }

    public function parseTradeUnitAttachmentScope($attachmentData): TradeAttachmentScopeEnum
    {
        $scope = TradeAttachmentScopeEnum::OTHER;

        $caption = strtolower(Arr::get($attachmentData, 'caption', ''));

        if ($caption === 'sds') {
            return TradeAttachmentScopeEnum::SDS;
        } elseif (Str::endsWith(rtrim($caption), ' sds')) {
            return TradeAttachmentScopeEnum::SDS;
        } elseif (Str::endsWith(rtrim($caption), ' msds')) {
            return TradeAttachmentScopeEnum::MSDS;
        } elseif (Str::contains($caption, 'ifra')) {
            return TradeAttachmentScopeEnum::IFRA;
        } elseif (Str::contains($caption, 'msds file')) {
            return TradeAttachmentScopeEnum::MSDS;
        } elseif (Str::contains($caption, 'allergen')) {
            return TradeAttachmentScopeEnum::ALLERGEN_DECLARATIONS;
        }


        if (Arr::get($attachmentData, 'scope') == 'MSDS') {
            return TradeAttachmentScopeEnum::MSDS;
        }

        return $scope;
    }
}
