<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 28 Feb 2023 22:52:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums;

use App\Actions\Utils\Abbreviate;

trait EnumHelperTrait
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function snake(): string
    {
        return str_replace('-', '_', $this->value);
    }

    public static function shortLabels(): array
    {
        $shortLabels = [];
        foreach (self::cases() as $case) {
            $shortLabels[$case->value] = Abbreviate::run($case->value);
        }

        return $shortLabels;
    }

}
