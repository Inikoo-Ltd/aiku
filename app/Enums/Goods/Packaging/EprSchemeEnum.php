<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Goods\Packaging;

use App\Enums\EnumHelperTrait;

/**
 * The packaging EPR schemes a return can be produced for.
 */
enum EprSchemeEnum: string
{
    use EnumHelperTrait;

    case UK = 'uk';
    case SK = 'sk';
    case DE = 'de';
    case ES = 'es';
    case FR = 'fr';

    public static function labels(): array
    {
        return [
            'uk' => __('UK pEPR'),
            'sk' => __('Slovakia (ENVI-PAK / NATUR-PACK)'),
            'de' => __('Germany (LUCID)'),
            'es' => __('Spain (Ecoembes)'),
            'fr' => __('France (Citeo)'),
        ];
    }
}
