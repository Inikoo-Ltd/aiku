<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-15h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\TradeUnit\UI\Traits;

use App\Actions\Helpers\Language\UI\GetLanguagesOptions;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Models\Goods\TradeUnit;

trait WithTradeUnitEditSections
{
    public function getTradeUnitsBulkEdit(): array
    {
        return [
            'sections' => [
                'gpsr'       => [
                    'label'       => __('GPSR'),
                    'icon'        => 'fa-light fa-biohazard',
                    'fields'      => $this->getTradeUnitGpsrFields(null),
                    'updateRoute' => [
                        'name'       => 'grp.models.trade_units.bulk_update_gpsr',
                        'parameters' => [],
                        'method'     => 'patch',
                    ],
                ],
                'label_info' => [
                    'label'       => __('Labeling & Compliance Marks'),
                    'icon'        => 'fa-light fa-stamp',
                    'fields'      => $this->getTradeUnitLabelInfoFields(null),
                    'updateRoute' => [
                        'name'       => 'grp.models.trade_units.bulk_update_label_info',
                        'parameters' => [],
                        'method'     => 'patch',
                    ],
                ],
            ],
        ];
    }

    public function getTradeUnitGpsrFields(?TradeUnit $tradeUnit): array
    {
        return [
            'gpsr_manufacturer' => [
                'type'        => 'textarea',
                'label'       => __('Manufacturer Details'),
                'information' => __('Name and postal address of the manufacturer. Shown on the product page. If empty, the Part GPSR is used.'),
                'placeholder' => __('e.g. Company name, street, city, postcode, country'),
                'rows'        => 2,
                'value'       => $tradeUnit?->gpsr_manufacturer
            ],
            'gpsr_eu_responsible' => [
                'type'        => 'textarea',
                'label'       => __('EU Responsible Person'),
                'information' => __('Name and postal address of the economic operator responsible for the product in the EU. If empty, the Part GPSR is used.'),
                'placeholder' => __('e.g. Company name, street, city, postcode, country'),
                'rows'        => 2,
                'value'       => $tradeUnit?->gpsr_eu_responsible
            ],
            'gpsr_warnings' => [
                'type'        => 'textarea',
                'label'       => __('Warnings & Precautions'),
                'information' => __('Safety warnings printed on the label and shown on the product page. If empty, the Part GPSR is used.'),
                'placeholder' => __('e.g. Keep out of reach of children. Avoid contact with eyes.'),
                'value'       => $tradeUnit?->gpsr_warnings
            ],
            'gpsr_manual' => [
                'type'        => 'textarea',
                'label'       => __('Directions for Use'),
                'information' => __('How to use the product safely. Shown on the product page. If empty, the Part GPSR is used.'),
                'placeholder' => __('e.g. Apply a small amount to clean skin twice a day.'),
                'value'       => $tradeUnit?->gpsr_manual
            ],
            'gpsr_class_category_danger' => [
                'type'        => 'textarea',
                'label'       => __('Hazard Class & Category'),
                'information' => __('CLP / GHS hazard classification, with hazard statements when relevant. If empty, the Part GPSR is used.'),
                'placeholder' => __('e.g. Flam. Liq. 3, H226; Eye Irrit. 2, H319'),
                'rows'        => 2,
                'value'       => $tradeUnit?->gpsr_class_category_danger,
            ],
            'pictogram_toxic' => [
                'type'        => 'toggle',
                'label'       => __('Acute Toxicity'),
                'information' => __('CLP / GHS hazard pictograms. Each one turned on is shown on the product page.'),
                'value'       => (bool) $tradeUnit?->pictogram_toxic,
                'suffixImage' => '/hazardIcon/toxic-icon.png'
            ],
            'pictogram_corrosive' => [
                'type'        => 'toggle',
                'label'       => __('Corrosive'),
                'value'       => (bool) $tradeUnit?->pictogram_corrosive,
                'suffixImage' => '/hazardIcon/corrosive-icon.png'
            ],
            'pictogram_explosive' => [
                'type'        => 'toggle',
                'label'       => __('Explosive'),
                'value'       => (bool) $tradeUnit?->pictogram_explosive,
                'suffixImage' => '/hazardIcon/explosive.jpg'
            ],
            'pictogram_flammable' => [
                'type'        => 'toggle',
                'label'       => __('Flammable'),
                'value'       => (bool) $tradeUnit?->pictogram_flammable,
                'suffixImage' => '/hazardIcon/flammable.png'
            ],
            'pictogram_gas' => [
                'type'        => 'toggle',
                'label'       => __('Gas Under Pressure'),
                'value'       => (bool) $tradeUnit?->pictogram_gas,
                'suffixImage' => '/hazardIcon/gas.png'
            ],
            'pictogram_environment' => [
                'type'        => 'toggle',
                'label'       => __('Hazardous to the Environment'),
                'value'       => (bool) $tradeUnit?->pictogram_environment,
                'suffixImage' => '/hazardIcon/hazard-env.png'
            ],
            'pictogram_health' => [
                'type'        => 'toggle',
                'label'       => __('Health Hazard'),
                'value'       => (bool) $tradeUnit?->pictogram_health,
                'suffixImage' => '/hazardIcon/health-hazard.png'
            ],
            'pictogram_oxidising' => [
                'type'        => 'toggle',
                'label'       => __('Oxidising'),
                'value'       => (bool) $tradeUnit?->pictogram_oxidising,
                'suffixImage' => '/hazardIcon/oxidising.png'
            ],
            'pictogram_danger' => [
                'type'        => 'toggle',
                'label'       => __('Serious Health Hazard'),
                'value'       => (bool) $tradeUnit?->pictogram_danger,
                'suffixImage' => '/hazardIcon/serious-health-hazard.png'
            ],
        ];
    }

    public function getTradeUnitLabelInfoFields(?array $labelInfo): array
    {
        return [
            'label_info_approved' => [
                'type'               => 'toggle',
                'label'              => __('Publish Regulatory & Label Information'),
                'value'              => data_get($labelInfo, 'label_info_approved', false),
                'single_description' => __('Switch on only once all the regulatory and label information below has been checked and completed. While off, the Regulatory & Label Information tab stays hidden on the website.'),
                'saveConfirmation'   => [
                    'description' => __('The Regulatory & Label Information tab is only published on the website when every trade unit of a product has been approved.'),
                ],
            ],
            'markets' => [
                'type'         => 'checkbox',
                'label'        => __('Markets'),
                'mode'         => 'inline',
                'emptyWarning' => __('Markets are shown on the product page. With none selected, the product page may not show market details.'),
                'value'        => TradeUnitMarketEnum::checkboxValue(data_get($labelInfo, 'markets')),
            ],
            'languages' => [
                'type'         => 'select-improved',
                'label'        => __('Languages'),
                'placeholder'  => __('Select languages'),
                'options'      => array_values(array_map(
                    fn (array $language) => $language + ['label' => '('.strtoupper($language['code']).') '.$language['name']],
                    GetLanguagesOptions::make()->all()
                )),
                'labelProp'    => 'label',
                'valueProp'    => 'code',
                'tagLabelProp' => 'code',
                'tagUppercase' => true,
                'value'        => data_get($labelInfo, 'languages', []),
            ],
            'best_before' => [
                'type'        => 'select-improved',
                'label'       => __('PAO / Expiry Date / Best Before'),
                'placeholder' => __('Select an option'),
                'multiple'    => false,
                'options'     => TradeUnitBestBeforeEnum::options(),
                'labelProp'   => 'label',
                'valueProp'   => 'value',
                'value'       => data_get($labelInfo, 'best_before'),
            ],
            'packaging_material_codes' => [
                'type'             => 'select-improved',
                'label'            => __('Packaging Material Codes'),
                'placeholder'      => __('Select packaging materials'),
                'options'          => TradeUnitPackagingMaterialEnum::options(),
                'labelProp'        => 'label',
                'valueProp'        => 'value',
                'tagLabelProp'     => 'code',
                'enableHideToggle' => true,
                'toggle_value'     => data_get($labelInfo, 'packaging_material_codes.show', false),
                'hasOther'         => [
                    'name'  => 'packaging_material_codes_show',
                    'value' => data_get($labelInfo, 'packaging_material_codes.show', false),
                ],
                'value'            => data_get($labelInfo, 'packaging_material_codes.value', []),
            ],
            'batch_number' => [
                'type'               => 'toggle',
                'label'              => __('Batch Number'),
                'value'              => data_get($labelInfo, 'batch_number', false),
                'single_description' => __("When enabled, this will be marked as 'Present'"),
            ],
            'ce_marking' => [
                'type'               => 'toggle',
                'label'              => __('CE Markings'),
                'value'              => data_get($labelInfo, 'ce_marking', false),
                'single_description' => __("When enabled, this will be marked as 'Present'"),
            ],
            'ukca_marking' => [
                'type'               => 'toggle',
                'label'              => __('UKCA Markings'),
                'value'              => data_get($labelInfo, 'ukca_marking', false),
                'single_description' => __("When enabled, this will be marked as 'Present'"),
            ],
            'weee_symbol' => [
                'type'               => 'toggle',
                'label'              => __('WEEE Symbol'),
                'value'              => data_get($labelInfo, 'weee_symbol', false),
                'single_description' => __("When enabled, this will be marked as 'Present'"),
            ],
            'ip_rating' => [
                'type'               => 'toggle',
                'label'              => __('IP Rating'),
                'value'              => data_get($labelInfo, 'ip_rating', false),
                'single_description' => __("When enabled, this will be marked as 'Present'"),
            ],
            'sorting_recycling_information' => [
                'type'               => 'toggle',
                'label'              => __('Sorting / Recycling Information'),
                'value'              => data_get($labelInfo, 'sorting_recycling_information', false),
                'single_description' => __("When enabled, this will be marked as 'Present'"),
            ],
            'safety_icons' => [
                'type'               => 'toggle',
                'label'              => __('Safety Icons'),
                'value'              => data_get($labelInfo, 'safety_icons', false),
                'single_description' => __('Candles only. When enabled, candle safety warning pictograms are shown on the product page'),
            ],
            'show_net_quantity' => [
                'type'               => 'toggle',
                'label'              => __('Show Net Quantity'),
                'value'              => data_get($labelInfo, 'show_net_quantity', true) !== false,
                'single_description' => __('When disabled, the net quantity is not shown on the product page'),
            ],
        ];
    }
}
