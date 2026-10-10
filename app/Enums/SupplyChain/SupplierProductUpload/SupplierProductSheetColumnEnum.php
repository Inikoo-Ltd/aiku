<?php

namespace App\Enums\SupplyChain\SupplierProductUpload;

/**
 * The columns the supplier product upload reads, matched by their row 5 heading.
 * Columns can move around in the sheet; only the heading text ties a column to a field.
 */
enum SupplierProductSheetColumnEnum: string
{
    case FAMILY                     = 'family';
    case PART_REFERENCE             = 'part_reference';
    case UNIT_NAME                  = 'unit_name';
    case SUPPLIER_CODE              = 'supplier_code';
    case UNIT_LABEL                 = 'unit_label';
    case UNITS_PER_SKO              = 'units_per_sko';
    case SKOS_PER_OUTER             = 'skos_per_outer';
    case SKOS_PER_CARTON            = 'skos_per_carton';
    case MINIMUM_ORDER_CARTONS      = 'minimum_order_cartons';
    case UNIT_COST                  = 'unit_cost';
    case UNIT_EXPENSE               = 'unit_expense';
    case EXTRA_COSTS                = 'extra_costs';
    case RECOMMENDED_PRICE          = 'recommended_price';
    case RECOMMENDED_RRP            = 'recommended_rrp';
    case RECOMMENDED_PRICE_EUR      = 'recommended_price_eur';
    case RECOMMENDED_RRP_EUR        = 'recommended_rrp_eur';
    case UNIT_BARCODE               = 'unit_barcode';
    case DELIVERY_DAYS              = 'delivery_days';
    case UNIT_WEIGHT                = 'unit_weight';
    case UNIT_DIMENSIONS            = 'unit_dimensions';
    case SKO_WEIGHT                 = 'sko_weight';
    case CARTON_WEIGHT              = 'carton_weight';
    case SKO_DIMENSIONS             = 'sko_dimensions';
    case CARTON_CBM                 = 'carton_cbm';
    case MATERIALS                  = 'materials';
    case TARIFF_CODE                = 'tariff_code';
    case GPSR_MANUFACTURER          = 'gpsr_manufacturer';
    case GPSR_EU_RESPONSIBLE        = 'gpsr_eu_responsible';
    case GPSR_WARNINGS              = 'gpsr_warnings';
    case GPSR_INSTRUCTIONS          = 'gpsr_instructions';
    case GPSR_LANGUAGES             = 'gpsr_languages';
    case BRAND                      = 'brand';
    case BATCH_TRACEABILITY         = 'batch_traceability';
    case REGULATORY_CATEGORY        = 'regulatory_category';
    case TOY_STATUS                 = 'toy_status';
    case BATTERIES_MAGNETS          = 'batteries_magnets';
    case SVHC                       = 'svhc';
    case SVHC_SUBSTANCE             = 'svhc_substance';
    case CLP_SIGNAL_WORD            = 'clp_signal_word';
    case MATERIAL_COMPOSITION       = 'material_composition';
    case EUDR_STATUS                = 'eudr_status';
    case EUDR_COMMODITY             = 'eudr_commodity';
    case EUDR_SPECIES               = 'eudr_species';
    case EUDR_COUNTRY               = 'eudr_country';
    case EUDR_REGION                = 'eudr_region';
    case EUDR_GEOLOCATION           = 'eudr_geolocation';
    case EUDR_CERTIFICATION         = 'eudr_certification';
    case EUDR_LEGALITY_EVIDENCE     = 'eudr_legality_evidence';

    public const string ORDER_CARTONS_PREFIX = 'order cartons ';

    public function heading(): string
    {
        return match ($this) {
            self::FAMILY                => 'Family',
            self::PART_REFERENCE        => 'Part reference',
            self::UNIT_NAME             => 'Unit recommended description (website)',
            self::SUPPLIER_CODE         => "Supplier's product code",
            self::UNIT_LABEL            => 'Unit label',
            self::UNITS_PER_SKO         => 'Units per SKO',
            self::SKOS_PER_OUTER        => 'Recommended SKOs per selling outer',
            self::SKOS_PER_CARTON       => 'SKOs per carton',
            self::MINIMUM_ORDER_CARTONS => 'Minimum order (cartons)',
            self::UNIT_COST             => 'Unit cost (Sup Cur)',
            self::UNIT_EXPENSE          => 'Unit expense (Sup Cur)',
            self::EXTRA_COSTS           => 'Unit Est True Extra costs %',
            self::RECOMMENDED_PRICE     => 'Unit recommended price (£)',
            self::RECOMMENDED_RRP       => 'Unit recommended RRP (£)',
            self::RECOMMENDED_PRICE_EUR => 'Unit recommended price (€)',
            self::RECOMMENDED_RRP_EUR   => 'Unit recommended RRP (€)',
            self::UNIT_BARCODE          => 'Unit barcode (EAN-13, for website)',
            self::DELIVERY_DAYS         => 'Average delivery time (days)',
            self::UNIT_WEIGHT           => 'Unit weight (kg)',
            self::UNIT_DIMENSIONS       => 'Unit dimensions (l x w x h) in cm',
            self::SKO_WEIGHT            => 'SKO weight (kg)',
            self::CARTON_WEIGHT         => 'Carton Weight',
            self::SKO_DIMENSIONS        => 'SKO dimensions (l x w x h) in cm',
            self::CARTON_CBM            => 'Carton CBM',
            self::MATERIALS             => 'Materials',
            self::TARIFF_CODE           => 'Tariff code',
            self::GPSR_MANUFACTURER      => 'Manufacturer (name, postal address, email)',
            self::GPSR_EU_RESPONSIBLE    => 'EU responsible person (name, postal address, email)',
            self::GPSR_WARNINGS          => 'Warnings and safety information',
            self::GPSR_INSTRUCTIONS      => 'Instructions for use',
            self::GPSR_LANGUAGES         => 'Languages of warnings and instructions',
            self::BRAND                  => 'Brand',
            self::BATCH_TRACEABILITY     => 'Batch traceability',
            self::REGULATORY_CATEGORY    => 'Regulatory category',
            self::TOY_STATUS             => 'Toy status',
            self::BATTERIES_MAGNETS      => 'Batteries / magnets',
            self::SVHC                   => 'SVHC above 0.1%',
            self::SVHC_SUBSTANCE         => 'SVHC substance',
            self::CLP_SIGNAL_WORD        => 'CLP signal word',
            self::MATERIAL_COMPOSITION   => 'Material composition (% by weight)',
            self::EUDR_STATUS            => 'EUDR status',
            self::EUDR_COMMODITY         => 'EUDR commodity',
            self::EUDR_SPECIES           => 'EUDR species (scientific name)',
            self::EUDR_COUNTRY           => 'EUDR country of production',
            self::EUDR_REGION            => 'EUDR region of production',
            self::EUDR_GEOLOCATION       => 'EUDR plot geolocation',
            self::EUDR_CERTIFICATION     => 'EUDR certification',
            self::EUDR_LEGALITY_EVIDENCE => 'EUDR legality evidence',
        };
    }

    public function isRequired(): bool
    {
        return in_array($this, [
            self::FAMILY,
            self::PART_REFERENCE,
            self::UNIT_NAME,
            self::SUPPLIER_CODE,
            self::UNIT_LABEL,
            self::UNITS_PER_SKO,
            self::SKOS_PER_OUTER,
            self::SKOS_PER_CARTON,
            self::MINIMUM_ORDER_CARTONS,
            self::UNIT_COST,
            self::UNIT_EXPENSE,
            self::EXTRA_COSTS,
            self::RECOMMENDED_PRICE,
            self::RECOMMENDED_RRP,
            self::RECOMMENDED_PRICE_EUR,
            self::RECOMMENDED_RRP_EUR,
            self::UNIT_BARCODE,
            self::MATERIALS,
        ], true);
    }

    /**
     * The trade unit's GPSR field a v7 compliance column fills.
     */
    public function tradeUnitField(): ?string
    {
        return match ($this) {
            self::GPSR_MANUFACTURER   => 'gpsr_manufacturer',
            self::GPSR_EU_RESPONSIBLE => 'gpsr_eu_responsible',
            self::GPSR_WARNINGS       => 'gpsr_warnings',
            self::GPSR_INSTRUCTIONS   => 'gpsr_manual',
            self::GPSR_LANGUAGES      => 'gpsr_class_languages',
            default                   => null,
        };
    }

    /**
     * Where a v7 compliance column goes in the trade unit's compliance data, as a dotted key.
     */
    public function complianceKey(): ?string
    {
        return match ($this) {
            self::BRAND, self::BATCH_TRACEABILITY, self::REGULATORY_CATEGORY, self::TOY_STATUS, self::BATTERIES_MAGNETS,
            self::SVHC, self::SVHC_SUBSTANCE, self::CLP_SIGNAL_WORD => $this->value,
            self::EUDR_STATUS, self::EUDR_COMMODITY, self::EUDR_SPECIES, self::EUDR_COUNTRY, self::EUDR_REGION,
            self::EUDR_GEOLOCATION, self::EUDR_CERTIFICATION, self::EUDR_LEGALITY_EVIDENCE => 'eudr.'.substr($this->value, 5),
            default => null,
        };
    }

    /**
     * GBP / EUR for the recommended prices, "supplier" for the supplier's own currency, null when not money.
     */
    public function currency(): ?string
    {
        return match ($this) {
            self::RECOMMENDED_PRICE, self::RECOMMENDED_RRP         => 'GBP',
            self::RECOMMENDED_PRICE_EUR, self::RECOMMENDED_RRP_EUR => 'EUR',
            self::UNIT_COST, self::UNIT_EXPENSE                    => 'supplier',
            default                                                => null,
        };
    }

    public static function normaliseHeading(mixed $heading): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim((string)$heading)));
    }

    public static function fromHeading(mixed $heading): ?self
    {
        $heading = self::normaliseHeading($heading);
        foreach (self::cases() as $column) {
            if (self::normaliseHeading($column->heading()) === $heading) {
                return $column;
            }
        }

        return null;
    }
}
