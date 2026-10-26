<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-16h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Goods\ProductDevelopment;

use App\Enums\EnumHelperTrait;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Models\Goods\TradeUnit;
use App\Models\SupplyChain\SupplierProduct;

enum ProductDevelopmentTargetFieldEnum: string
{
    use EnumHelperTrait;

    case TRADE_UNIT_NET_WEIGHT            = 'trade_unit.net_weight';
    case TRADE_UNIT_GROSS_WEIGHT          = 'trade_unit.gross_weight';
    case TRADE_UNIT_MARKETING_DIMENSIONS  = 'trade_unit.marketing_dimensions';
    case TRADE_UNIT_ORIGIN_COUNTRY        = 'trade_unit.origin_country_id';
    case TRADE_UNIT_BARCODE               = 'trade_unit.barcode';
    case TRADE_UNIT_TARIFF_CODE           = 'trade_unit.tariff_code';
    case TRADE_UNIT_CPNP_NUMBER           = 'trade_unit.cpnp_number';
    case TRADE_UNIT_UFI_NUMBER            = 'trade_unit.ufi_number';
    case TRADE_UNIT_SCPN_NUMBER           = 'trade_unit.scpn_number';
    case TRADE_UNIT_UN_NUMBER             = 'trade_unit.un_number';
    case TRADE_UNIT_MARKETING_INGREDIENTS = 'trade_unit.marketing_ingredients';
    case TRADE_UNIT_GPSR_MANUFACTURER     = 'trade_unit.gpsr_manufacturer';
    case TRADE_UNIT_GPSR_EU_RESPONSIBLE   = 'trade_unit.gpsr_eu_responsible';
    case TRADE_UNIT_GPSR_WARNINGS         = 'trade_unit.gpsr_warnings';
    case TRADE_UNIT_GPSR_CLASS_LANGUAGES  = 'trade_unit.gpsr_class_languages';
    case TRADE_UNIT_INGREDIENTS           = 'trade_unit.ingredients';
    case TRADE_UNIT_PICTOGRAMS            = 'trade_unit.pictograms';
    case TRADE_UNIT_LABEL_MARKS           = 'trade_unit.label_info';

    case SUPPLIER_PRODUCT_UNITS_PER_PACK   = 'supplier_product.units_per_pack';
    case SUPPLIER_PRODUCT_UNITS_PER_CARTON = 'supplier_product.units_per_carton';
    case SUPPLIER_PRODUCT_CBM              = 'supplier_product.cbm';
    case SUPPLIER_PRODUCT_CARTON_WEIGHT    = 'supplier_product.carton_weight';
    case SUPPLIER_PRODUCT_LEAD_TIME        = 'supplier_product.estimated_lead_time_days';
    case SUPPLIER_PRODUCT_COST             = 'supplier_product.cost';

    public static function labels(): array
    {
        return [
            'trade_unit.net_weight'                     => __('Net weight'),
            'trade_unit.gross_weight'                   => __('Gross weight'),
            'trade_unit.marketing_dimensions'           => __('Dimensions'),
            'trade_unit.origin_country_id'              => __('Country of origin'),
            'trade_unit.barcode'                        => __('Barcode'),
            'trade_unit.tariff_code'                    => __('Tariff code'),
            'trade_unit.cpnp_number'                    => __('CPNP number'),
            'trade_unit.ufi_number'                     => __('UFI number'),
            'trade_unit.scpn_number'                    => __('SCPN number'),
            'trade_unit.un_number'                      => __('UN number'),
            'trade_unit.marketing_ingredients'          => __('Ingredients'),
            'trade_unit.gpsr_manufacturer'              => __('GPSR manufacturer'),
            'trade_unit.gpsr_eu_responsible'            => __('GPSR EU responsible'),
            'trade_unit.gpsr_warnings'                  => __('GPSR warnings'),
            'trade_unit.gpsr_class_languages'           => __('GPSR languages'),
            'trade_unit.ingredients'                    => __('Ingredients'),
            'trade_unit.pictograms'                     => __('Hazard pictograms'),
            'trade_unit.label_info'                     => __('Label & compliance marks'),
            'supplier_product.units_per_pack'           => __('Units per pack'),
            'supplier_product.units_per_carton'         => __('Units per carton'),
            'supplier_product.cbm'                      => __('Carton CBM'),
            'supplier_product.carton_weight'            => __('Carton weight'),
            'supplier_product.estimated_lead_time_days' => __('Lead time (days)'),
            'supplier_product.cost'                     => __('Supplier cost'),
        ];
    }

    public function column(): string
    {
        return explode('.', $this->value, 2)[1];
    }

    public function isOnSupplierProduct(): bool
    {
        return str_starts_with($this->value, 'supplier_product.');
    }

    public function isCost(): bool
    {
        return $this === self::SUPPLIER_PRODUCT_COST;
    }

    /**
     * The key of the pack text this field prints, for the fields compliance can mark "Include on label".
     */
    public function labelField(): ?string
    {
        return match ($this) {
            self::TRADE_UNIT_INGREDIENTS => 'ingredients',
            self::TRADE_UNIT_GPSR_WARNINGS => 'warnings',
            self::TRADE_UNIT_GPSR_MANUFACTURER => 'manufacturer',
            self::TRADE_UNIT_GPSR_EU_RESPONSIBLE => 'eu_responsible',
            self::TRADE_UNIT_UFI_NUMBER => 'ufi',
            self::TRADE_UNIT_UN_NUMBER => 'un_number',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function labelFields(): array
    {
        return collect(self::cases())->map(fn (self $field) => $field->labelField())->filter()->values()->all();
    }

    /**
     * A group of switches where "all off" is a real answer: it is done when compliance saves it, not
     * because a value happens to be there.
     */
    public function isConfirmedBySaving(): bool
    {
        return $this === self::TRADE_UNIT_PICTOGRAMS || $this === self::TRADE_UNIT_LABEL_MARKS;
    }

    /**
     * @return array<string, array{label: string, icon: string}>
     */
    public static function pictograms(): array
    {
        return [
            'pictogram_toxic'       => ['label' => __('Acute Toxicity'), 'icon' => '/hazardIcon/toxic-icon.png'],
            'pictogram_corrosive'   => ['label' => __('Corrosive'), 'icon' => '/hazardIcon/corrosive-icon.png'],
            'pictogram_explosive'   => ['label' => __('Explosive'), 'icon' => '/hazardIcon/explosive.jpg'],
            'pictogram_flammable'   => ['label' => __('Flammable'), 'icon' => '/hazardIcon/flammable.png'],
            'pictogram_gas'         => ['label' => __('Gas Under Pressure'), 'icon' => '/hazardIcon/gas.png'],
            'pictogram_environment' => ['label' => __('Hazardous to the Environment'), 'icon' => '/hazardIcon/hazard-env.png'],
            'pictogram_health'      => ['label' => __('Health Hazard'), 'icon' => '/hazardIcon/health-hazard.png'],
            'pictogram_oxidising'   => ['label' => __('Oxidising'), 'icon' => '/hazardIcon/oxidising.png'],
            'pictogram_danger'      => ['label' => __('Serious Health Hazard'), 'icon' => '/hazardIcon/serious-health-hazard.png'],
        ];
    }

    /**
     * The keys of the label-information group that UpdateTradeUnit takes as plain fields.
     *
     * @return array<int, string>
     */
    public static function labelMarkKeys(): array
    {
        return ['markets', 'languages', 'best_before', 'packaging_material_codes', 'packaging_material_codes_show', 'show_net_quantity', ...TradeUnitLabelPresenceEnum::values()];
    }

    /**
     * The current value of a grouped field, shaped the way the checklist edits it.
     *
     * @return array<string, mixed>
     */
    public function groupValue(TradeUnit $tradeUnit): array
    {
        if ($this === self::TRADE_UNIT_PICTOGRAMS) {
            return collect(array_keys(self::pictograms()))->mapWithKeys(fn (string $key) => [$key => (bool) $tradeUnit->getAttribute($key)])->all();
        }

        $labelInfo = $tradeUnit->label_info ?? [];

        return [
            'markets'                       => array_values((array) data_get($labelInfo, 'markets', [])),
            'languages'                     => array_values((array) data_get($labelInfo, 'languages', [])),
            'best_before'                   => data_get($labelInfo, 'best_before'),
            'packaging_material_codes'      => array_values((array) data_get($labelInfo, 'packaging_material_codes.value', [])),
            'packaging_material_codes_show' => (bool) data_get($labelInfo, 'packaging_material_codes.show', false),
            'show_net_quantity'             => (bool) data_get($labelInfo, 'show_net_quantity', false),
            ...collect(TradeUnitLabelPresenceEnum::values())->mapWithKeys(fn (string $key) => [$key => data_get($labelInfo, $key) === true])->all(),
        ];
    }

    public function isFilledOn(TradeUnit $tradeUnit, ?SupplierProduct $supplierProduct): bool
    {
        $model = $this->isOnSupplierProduct() ? $supplierProduct : $tradeUnit;
        if (!$model) {
            return false;
        }

        if ($this === self::TRADE_UNIT_INGREDIENTS) {
            return $tradeUnit->ingredients()->exists();
        }

        $value = $model->getAttribute($this->column());

        if ($this === self::TRADE_UNIT_MARKETING_DIMENSIONS) {
            return collect(is_array($value) ? $value : [])->only(['h', 'l', 'w'])->filter(fn ($measure) => (float) $measure > 0)->isNotEmpty();
        }

        return filled($value);
    }

    /**
     * The placeholder the same field has on the trade unit edit page, so both read alike.
     */
    public function placeholder(): ?string
    {
        return match ($this) {
            self::TRADE_UNIT_GPSR_MANUFACTURER, self::TRADE_UNIT_GPSR_EU_RESPONSIBLE => __('e.g. Company name, street, city, postcode, country'),
            self::TRADE_UNIT_GPSR_WARNINGS => __('e.g. Keep out of reach of children. Avoid contact with eyes.'),
            default => null,
        };
    }

    /**
     * The help text the same field has on the trade unit edit page.
     */
    public function information(): ?string
    {
        return match ($this) {
            self::TRADE_UNIT_GPSR_MANUFACTURER => __('Name and postal address of the manufacturer. Shown on the product page. If empty, the Part GPSR is used.'),
            self::TRADE_UNIT_GPSR_EU_RESPONSIBLE => __('Name and postal address of the economic operator responsible for the product in the EU. Leave empty to use the EU responsible person of the organisation.'),
            self::TRADE_UNIT_GPSR_WARNINGS => __('Safety warnings printed on the label and shown on the product page. If empty, the Part GPSR is used.'),
            self::TRADE_UNIT_PICTOGRAMS => __('CLP / GHS hazard pictograms. Each one turned on is shown on the product page.'),
            default => null,
        };
    }

    public function inputType(): string
    {
        return match ($this) {
            self::TRADE_UNIT_NET_WEIGHT, self::TRADE_UNIT_GROSS_WEIGHT, self::SUPPLIER_PRODUCT_UNITS_PER_PACK,
            self::SUPPLIER_PRODUCT_UNITS_PER_CARTON, self::SUPPLIER_PRODUCT_CARTON_WEIGHT, self::SUPPLIER_PRODUCT_LEAD_TIME => 'integer',
            self::SUPPLIER_PRODUCT_CBM, self::SUPPLIER_PRODUCT_COST => 'decimal',
            self::TRADE_UNIT_MARKETING_DIMENSIONS => 'dimensions',
            self::TRADE_UNIT_ORIGIN_COUNTRY => 'country',
            self::TRADE_UNIT_MARKETING_INGREDIENTS, self::TRADE_UNIT_GPSR_WARNINGS, self::TRADE_UNIT_GPSR_MANUFACTURER, self::TRADE_UNIT_GPSR_EU_RESPONSIBLE => 'textarea',
            self::TRADE_UNIT_INGREDIENTS => 'ingredients',
            self::TRADE_UNIT_PICTOGRAMS => 'pictograms',
            self::TRADE_UNIT_LABEL_MARKS => 'label_marks',
            default => 'text',
        };
    }

    public function unit(): ?string
    {
        return match ($this) {
            self::TRADE_UNIT_NET_WEIGHT, self::TRADE_UNIT_GROSS_WEIGHT, self::SUPPLIER_PRODUCT_CARTON_WEIGHT => 'g',
            self::SUPPLIER_PRODUCT_CBM => 'm³',
            self::SUPPLIER_PRODUCT_LEAD_TIME => __('days'),
            default => null,
        };
    }

    public function writesTo(): string
    {
        return $this->isOnSupplierProduct() ? 'supplier_product' : 'trade_unit';
    }
}
