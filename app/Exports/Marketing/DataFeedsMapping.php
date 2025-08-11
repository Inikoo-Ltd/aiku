<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2025 11:42:49 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Exports\Marketing;

use App\Helpers\NaturalLanguage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait DataFeedsMapping
{
    public function map($row): array
    {
        $dimensions      = NaturalLanguage::make()->dimensions($row->marketing_dimensions);
        $htmlDescription = trim($row->description_title.' '.$row->description.' '.$row->description_extra);
        $description     = strip_tags($htmlDescription);

        $images = '';

        if ($row->web_images && $rawImages = json_decode($row->web_images, true)) {
            foreach (Arr::get($rawImages, 'all', []) as $image) {
                $images .= Arr::get($image, 'original.original').' ';
            }
        }
        $images = preg_replace('/ $/', '', $images);


        $status       = $row->state;
        $statusString = (string)$status;
        $status       = Str::studly($statusString);


        $availableQuantity = $row->available_quantity;
        if ($availableQuantity < 0) {
            $availableQuantity = 0;
        }

        if ($row->state == 'discontinued') {
            $availabilityStatus = 'Discontinued';
        } elseif ($row->state == 'discontinuing') {
            $availabilityStatus = 'Discontinuing';
        } else {
            $availabilityStatus = 'Normal';
            if ($availableQuantity == 0) {
                $availabilityStatus = 'OutofStock';
            } elseif ($availableQuantity < 5) {
                $availabilityStatus = 'VeryLow';
            } elseif ($availableQuantity < 20) {
                $availabilityStatus = 'Low';
            }
        }


        return [
            $status,
            $row->code,
            $row->family_name,
            $row->barcode,
            $row->cpnp_number ?? '',
            round($row->price, 2),
            round($row->units, 3),
            $row->unit,
            $row->price / $row->units,
            $row->name,
            $row->rrp / $row->units,
            $row->marketing_weight / 1000,
            $row->gross_weight / 1000,
            $dimensions,
            $row->marketing_ingredients ?? '',
            $htmlDescription,
            $description,
            $row->country_of_origin ?? '',
            $row->tariff_code ?? '',
            $row->duty_rate ?? '',
            $row->hts_us ?? '',
            $availabilityStatus,
            $images,
            $row->updated_at,
            $row->available_quantity_updated_at ?? '',
            $row->price_updated_at ?? '',
            $row->images_updated_at ?? '',
            $availableQuantity


        ];
    }


    public function headings(): array
    {
        return [
            'Status',
            'Product code',
            'Family',
            'Barcode',
            'CPNP number',
            'Price',
            'Units per outer',
            'Unit label',
            'Unit price',
            'Unit Name',
            'Unit RRP',
            'Unit net weight',
            'Package weight (shipping)',
            'Unit dimensions',
            'Materials/Ingredients',
            'Webpage description (html)',
            'Webpage description (plain text)',
            'Country of origin',
            'Tariff code',
            'Duty rate',
            'HTS US',
            'Stock',
            'Images',
            'Data updated',
            'Stock updated',
            'Price updated',
            'Images updated',
            'Available Quantity'
        ];
    }


}
