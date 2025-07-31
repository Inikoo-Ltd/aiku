<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2025 11:42:49 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Exports\Marketing;

use App\Actions\Helpers\Images\GetImgProxyUrl;
use App\Models\Web\Webpage;

trait DataFeedsMapping
{

    public function map($row): array
    {
        /** @var \App\Models\Catalogue\Product $product */
        $product=$row;



        $webpage = Webpage::where('model_id', $product->id)->where('model_type', 'Product')->first();
        return [
            $product->status->value,
            $product->code,
            '',
            $product->family?->name,
            $product->barcode,
            '', // CPNP number
            '', // TODO: need add column for total price in protfolio
            $product->units,
            $product->unit,
            $product->price, // unit price
            $product->name,
            $product->rrp, // unit RRP check this is correct or not
            '', // TODO: unit net weight
            $product->gross_weight,
            '', // TODO: unit dimensions
            '', // TODO: materials/ingredients
            '', // TODO: webpage description (html)
            $webpage?->description, // webpage description (plain text)
            $product->currency->code, // country of origin
            '', // TODO: tariff code
            '', // TODO: duty rate
            '', // TODO: HTS US
            $product->available_quantity,
            $product->image ? GetImgProxyUrl::run($product->image?->getImage()) : '',
            $product->updated_at,
            '', // TODO: stock updated
            '', // TODO: price updated
            $product->images->sortByDesc('updated_at')->first()?->updated_at,
        ];
    }

    public function headings(): array
    {
        return [
            'Status',
            'Product code',
            'Product user reference',
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
        ];
    }


}
