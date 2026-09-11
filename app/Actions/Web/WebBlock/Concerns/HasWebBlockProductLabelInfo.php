<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-15h-23m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Actions\Goods\TradeUnit\GetLabelInfoLanguages;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\Organisation;

trait HasWebBlockProductLabelInfo
{
    protected function getProductLabelInfo(Product $product): array
    {
        $labelInfo = $product->label_info;
        $markets   = (array) data_get($labelInfo, 'markets', []);

        $marketsData                = TradeUnitMarketEnum::marketsFromLabelInfo($labelInfo);
        $languagesData              = GetLabelInfoLanguages::run($labelInfo);
        $packagingMaterialCodesData = TradeUnitPackagingMaterialEnum::packagingMaterialCodesFromLabelInfo($labelInfo);
        $bestBeforeData             = TradeUnitBestBeforeEnum::bestBeforeFromLabelInfo($labelInfo);

        return [
            'markets'                       => $this->labelInfoItem(__('Markets'), $marketsData['show'], $marketsData['value']),
            'batch_number'                  => $this->presenceItem(__('Batch Number'), $labelInfo, 'batch_number'),
            'country_of_origin'             => $this->getCountryOfOrigin($product),
            'barcode'                       => $this->textItem(__('Barcode / EAN'), $product->barcode),
            'manufacturer'                  => $this->textItem(__('Manufacturer Details'), $product->gpsr_manufacturer),
            'uk_responsible_person'         => $this->getResponsiblePerson(__('UK Responsible Person'), 'aw', in_array(TradeUnitMarketEnum::UK->value, $markets, true)),
            'eu_responsible_person'         => $this->getResponsiblePerson(__('EU Responsible Person'), 'eu', in_array(TradeUnitMarketEnum::EU->value, $markets, true)),
            'languages'                     => $this->labelInfoItem(__('Languages'), $languagesData['show'], $languagesData['value']),
            'best_before'                   => $this->labelInfoItem(__('PAO / Expiry Date / Best Before'), $bestBeforeData['show'], $bestBeforeData['value']),
            'ingredients'                   => $this->textItem(__('Ingredients'), $product->marketing_ingredients),
            'direction_for_use'             => $this->textItem(__('Direction For Use'), $product->gpsr_manual),
            'warnings_and_precautions'      => $this->textItem(__('Warning & Precautions'), $product->gpsr_warnings),
            'clp_ghs_pictograms'            => $this->getPictograms($product),
            'ufi_number'                    => $this->textItem(__('UFI Number'), $product->ufi_number),
            'safety_icons'                  => $this->presenceItem(__('Safety Icons'), $labelInfo, 'safety_icons'),
            'net_quantity'                  => $this->getNetQuantity($product),
            'packaging_material_codes'      => $this->labelInfoItem(__('Packaging Material Codes'), $packagingMaterialCodesData['show'], $packagingMaterialCodesData['value']),
            'ce_marking'                    => $this->presenceItem(__('CE Markings'), $labelInfo, 'ce_marking'),
            'ukca_marking'                  => $this->presenceItem(__('UKCA Marking'), $labelInfo, 'ukca_marking'),
            'weee_symbol'                   => $this->presenceItem(__('WEEE Symbol'), $labelInfo, 'weee_symbol'),
            'ip_rating'                     => $this->presenceItem(__('IP Rating'), $labelInfo, 'ip_rating'),
            'sorting_recycling_information' => $this->presenceItem(__('Sorting / Recycling Information'), $labelInfo, 'sorting_recycling_information'),
        ];
    }

    private function labelInfoItem(string $label, bool $show, mixed $value): array
    {
        return [
            'show'  => $show,
            'label' => $label,
            'value' => $value,
        ];
    }

    private function presenceItem(string $label, ?array $labelInfo, string $field): array
    {
        $isPresent = data_get($labelInfo, $field, false) === true;

        return $this->labelInfoItem($label, $isPresent, $isPresent);
    }

    private function textItem(string $label, ?string $value): array
    {
        $value = blank($value) ? null : trim($value);

        return $this->labelInfoItem($label, $value !== null, $value);
    }

    private function getCountryOfOrigin(Product $product): array
    {
        $country = $product->origin_country_id ? Country::find($product->origin_country_id) : null;

        return $this->labelInfoItem(
            __('Country Of Origin'),
            $country !== null,
            $country ? [
                'code' => $country->code,
                'name' => $country->name,
            ] : null
        );
    }

    private function getResponsiblePerson(string $label, string $organisationSlug, bool $isMarketSelected): array
    {
        $organisation = Organisation::where('slug', $organisationSlug)->with('address')->first();

        return $this->labelInfoItem(
            $label,
            $isMarketSelected,
            $organisation ? [
                'name'    => $organisation->name,
                'address' => $organisation->address?->formatted_address,
            ] : null
        );
    }

    private function getPictograms(Product $product): array
    {
        $pictograms = [
            'pictogram_toxic'       => ['label' => __('Acute Toxicity'), 'image' => '/hazardIcon/toxic-icon.png'],
            'pictogram_corrosive'   => ['label' => __('Corrosive'), 'image' => '/hazardIcon/corrosive-icon.png'],
            'pictogram_explosive'   => ['label' => __('Explosive'), 'image' => '/hazardIcon/explosive.jpg'],
            'pictogram_flammable'   => ['label' => __('Flammable'), 'image' => '/hazardIcon/flammable.png'],
            'pictogram_gas'         => ['label' => __('Gas Under Pressure'), 'image' => '/hazardIcon/gas.png'],
            'pictogram_environment' => ['label' => __('Hazardous to the Environment'), 'image' => '/hazardIcon/hazard-env.png'],
            'pictogram_health'      => ['label' => __('Health Hazard'), 'image' => '/hazardIcon/health-hazard.png'],
            'pictogram_oxidising'   => ['label' => __('Oxidising'), 'image' => '/hazardIcon/oxidising.png'],
            'pictogram_danger'      => ['label' => __('Serious Health Hazard'), 'image' => '/hazardIcon/serious-health-hazard.png'],
        ];

        $activePictograms = [];
        foreach ($pictograms as $column => $pictogram) {
            if ($product->$column) {
                $activePictograms[] = [
                    'key'   => str_replace('pictogram_', '', $column),
                    ...$pictogram,
                ];
            }
        }

        return $this->labelInfoItem(__('CLP / GHS Pictograms'), $activePictograms !== [], $activePictograms);
    }

    private function getNetQuantity(Product $product): array
    {
        $grams = (float) $product->marketing_weight;

        if ($grams <= 0) {
            return $this->labelInfoItem(__('Net Quantity'), false, null);
        }

        $formatted = $grams >= 1000
            ? rtrim(rtrim(number_format($grams / 1000, 3, '.', ''), '0'), '.').' kg'
            : rtrim(rtrim(number_format($grams, 2, '.', ''), '0'), '.').' g';

        return $this->labelInfoItem(__('Net Quantity'), true, [
            'grams'     => $grams,
            'formatted' => $formatted,
        ]);
    }
}
