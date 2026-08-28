<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:28:55 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\Product\CloneProductAttachmentsFromTradeUnits;
use App\Actions\Goods\TradeUnit\StoreTradeUnit;
use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
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

                // Nothing else: dangerous-goods fill-null and attachment refresh used to
                // run here, but even those are Aurora writing a trade unit staff maintain
                // in aiku, so an existing trade unit is resolved and returned untouched.
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
