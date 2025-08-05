<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 12:29:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\TaxNumber;

use App\Enums\EnumHelperTrait;

enum TaxNumberTypeEnum: string
{
    use EnumHelperTrait;


    case EU_VAT = 'eu-vat';
    case GB_VAT = 'gb-vat';
    case OTHER  = 'other';

       public function label(): string
    {
        return match ($this) {
            TaxNumberTypeEnum::EU_VAT => 'EU VAT',
            TaxNumberTypeEnum::GB_VAT => 'GB VAT',
            TaxNumberTypeEnum::OTHER => 'Other',
        };
    }

    public static function getOptions(): array
    {
        return array_map(
            fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ],
            self::cases()
        );
    }
}
