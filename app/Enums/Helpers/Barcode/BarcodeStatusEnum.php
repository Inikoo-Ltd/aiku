<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 20:15:32 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Barcode;

use App\Enums\EnumHelperTrait;
use App\Models\SysAdmin\Group;

enum BarcodeStatusEnum: string
{
    use EnumHelperTrait;

    case AVAILABLE = 'available';
    case USED      = 'used';
    case RESERVED  = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::AVAILABLE => __('Available'),
            self::USED      => __('Used'),
            self::RESERVED  => __('Reserved'),
        };
    }

    public function icon(): array
    {
        return match ($this) {
            self::AVAILABLE => [
                    'icon' => 'fal fa-circle',
                    'class' => 'text-green-600'
                ],
            self::USED      => [
                    'icon' => 'fal fa-check-circle',
                    'class' => 'text-orange-600'
                ],
            self::RESERVED  => [
                    'icon' => 'fal fa-hourglass-half',
                    'class' => 'text-lime-600'
                ],
        };
    }

    public static function labels(): array
    {
        $arr = [];

        foreach (self::cases() as $case) {
            $arr[$case->value] = $case->label();
        }

        return $arr;
    }

    public static function count(Group $parent, $bucket = null): array
    {
        $stats  = $parent->goodsStats;
        $counts = [];

        foreach (self::cases() as $case) {
            $counts[$case->value] = $stats->{"number_barcodes_status_{$case->value}"};
        }

        return $counts;
    }
}
