<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\Artefact;

use App\Enums\EnumHelperTrait;

enum ArtefactLabelInformationEnum: string
{
    use EnumHelperTrait;

    case BATCH_CODE            = 'batch_code';
    case EXPIRY_DATE           = 'expiry_date';
    case BARCODE               = 'barcode';
    case PRODUCT_NAME          = 'product_name';
    case NET_WEIGHT            = 'net_weight';
    case MADE_IN               = 'made_in';
    case MANUFACTURED_BY       = 'manufactured_by';
    case IMPORTER              = 'importer';
    case UK_RESPONSIBLE_PERSON = 'uk_responsible_person';
    case EU_RESPONSIBLE_PERSON = 'eu_responsible_person';
    case INGREDIENTS           = 'ingredients';
    case CPNP_NUMBER           = 'cpnp_number';
    case UFI_NUMBER            = 'ufi_number';
    case SCPN_NUMBER           = 'scpn_number';
    case WARNINGS              = 'warnings';
    case DIRECTIONS_FOR_USE    = 'directions_for_use';
    case HAZARD_PICTOGRAMS     = 'hazard_pictograms';
    case PACKAGING_MATERIALS   = 'packaging_materials';
    case CE_MARKING            = 'ce_marking';
    case UKCA_MARKING          = 'ukca_marking';
    case WEEE_SYMBOL           = 'weee_symbol';
    case PERIOD_AFTER_OPENING  = 'period_after_opening';
    case SORTING_INFORMATION   = 'sorting_information';
    case FREE_TEXT             = 'free_text';

    public const LANGUAGE_SEPARATOR = ':';

    public static function labels(): array
    {
        return [
            'batch_code'            => __('Batch code'),
            'expiry_date'           => __('Expiry date'),
            'barcode'               => __('Barcode'),
            'product_name'          => __('Product name'),
            'net_weight'            => __('Net weight'),
            'made_in'               => __('Country of origin'),
            'manufactured_by'       => __('Manufacturer'),
            'importer'              => __('Importer / responsible person'),
            'uk_responsible_person' => __('UK responsible person'),
            'eu_responsible_person' => __('EU responsible person'),
            'ingredients'           => __('Ingredients'),
            'cpnp_number'           => __('CPNP number'),
            'ufi_number'            => __('UFI number'),
            'scpn_number'           => __('SCPN number'),
            'warnings'              => __('Warnings'),
            'directions_for_use'    => __('Directions for use'),
            'hazard_pictograms'     => __('Hazard pictograms'),
            'packaging_materials'   => __('Packaging material marks'),
            'ce_marking'            => __('CE marking'),
            'ukca_marking'          => __('UKCA marking'),
            'weee_symbol'           => __('WEEE symbol'),
            'period_after_opening'  => __('Period after opening'),
            'sorting_information'   => __('Sorting information (France)'),
            'free_text'             => __('Free text'),
        ];
    }

    /**
     * An icon prints the pictures its text names, a comma separated list of icon keys.
     */
    public function isIcon(): bool
    {
        return in_array($this, [self::HAZARD_PICTOGRAMS, self::PACKAGING_MATERIALS, self::CE_MARKING, self::UKCA_MARKING, self::WEEE_SYMBOL, self::PERIOD_AFTER_OPENING, self::SORTING_INFORMATION], true);
    }

    /**
     * Placed once per language the label is printed in, as "warnings:de".
     */
    public function isTranslated(): bool
    {
        return in_array($this, [self::PRODUCT_NAME, self::WARNINGS, self::DIRECTIONS_FOR_USE], true);
    }

    public static function inLanguage(self $information, string $languageCode): string
    {
        return $information->value.self::LANGUAGE_SEPARATOR.$languageCode;
    }

    /**
     * @return array{0: self|null, 1: string|null}
     */
    public static function parse(string $source): array
    {
        [$information, $languageCode] = array_pad(explode(self::LANGUAGE_SEPARATOR, $source, 2), 2, null);

        return [self::tryFrom($information), $languageCode];
    }

    /**
     * @param  array<string, string>  $languageNames  language code => name
     */
    public static function label(string $source, array $languageNames = []): string
    {
        [$information, $languageCode] = self::parse($source);

        if (!$information) {
            return $source;
        }

        $label = self::labels()[$information->value];

        return $languageCode ? $label.' ('.($languageNames[$languageCode] ?? $languageCode).')' : $label;
    }

    /**
     * @return array<int, string>
     */
    public static function sourceRule(): array
    {
        $translated = implode('|', array_map(
            fn (self $information) => $information->value,
            array_filter(self::cases(), fn (self $information) => $information->isTranslated())
        ));

        return ['string', 'regex:/^(?:'.implode('|', self::values()).'|(?:'.$translated.')'.self::LANGUAGE_SEPARATOR.'[A-Za-z-]{2,10})$/'];
    }
}
