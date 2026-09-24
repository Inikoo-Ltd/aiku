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

    case BATCH_CODE        = 'batch_code';
    case EXPIRY_DATE       = 'expiry_date';
    case BARCODE           = 'barcode';
    case PRODUCT_NAME      = 'product_name';
    case NET_WEIGHT        = 'net_weight';
    case MADE_IN           = 'made_in';
    case MANUFACTURED_BY   = 'manufactured_by';
    case IMPORTER          = 'importer';
    case INGREDIENTS       = 'ingredients';
    case CPNP_NUMBER       = 'cpnp_number';
    case UFI_NUMBER        = 'ufi_number';
    case SCPN_NUMBER       = 'scpn_number';
    case WARNINGS          = 'warnings';
    case HAZARD_PICTOGRAMS = 'hazard_pictograms';

    public static function labels(): array
    {
        return [
            'batch_code'        => __('Batch code'),
            'expiry_date'       => __('Expiry date'),
            'barcode'           => __('Barcode'),
            'product_name'      => __('Product name'),
            'net_weight'        => __('Net weight'),
            'made_in'           => __('Country of origin'),
            'manufactured_by'   => __('Manufacturer'),
            'importer'          => __('Importer / responsible person'),
            'ingredients'       => __('Ingredients'),
            'cpnp_number'       => __('CPNP number'),
            'ufi_number'        => __('UFI number'),
            'scpn_number'       => __('SCPN number'),
            'warnings'          => __('Warnings'),
            'hazard_pictograms' => __('Hazard pictograms'),
        ];
    }

    /**
     * Pictograms are images, so they can only be confirmed as printed on the artwork, never placed as a text.
     */
    public function isPlaceable(): bool
    {
        return $this !== self::HAZARD_PICTOGRAMS;
    }

    /**
     * @return array<int, string>
     */
    public static function placeableValues(): array
    {
        return array_values(array_map(
            fn (self $information) => $information->value,
            array_filter(self::cases(), fn (self $information) => $information->isPlaceable())
        ));
    }
}
