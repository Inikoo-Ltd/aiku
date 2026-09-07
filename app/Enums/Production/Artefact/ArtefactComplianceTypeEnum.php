<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\Artefact;

use App\Enums\EnumHelperTrait;

enum ArtefactComplianceTypeEnum: string
{
    use EnumHelperTrait;

    case SAFETY_TEST  = 'safety_test';
    case CERTIFICATE  = 'certificate';
    case BARCODE      = 'barcode';
    case TARIFF_CODE  = 'tariff_code';
    case LABEL        = 'label';
    case OTHER        = 'other';

    public static function labels($forElements = false): array
    {
        return [
            'safety_test' => __('Safety test'),
            'certificate' => __('Certificate'),
            'barcode'     => __('Barcode'),
            'tariff_code' => __('Tariff code'),
            'label'       => __('Label'),
            'other'       => __('Other'),
        ];
    }
}
