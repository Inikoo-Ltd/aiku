<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Production\Artefact;

use App\Enums\EnumHelperTrait;

enum ArtefactComplianceStatusEnum: string
{
    use EnumHelperTrait;

    case NOT_CONFIGURED = 'not_configured';
    case OK              = 'ok';
    case EXPIRING        = 'expiring';
    case PROBLEM         = 'problem';

    public static function labels($forElements = false): array
    {
        return [
            'not_configured' => __('Not configured'),
            'ok'             => __('Compliant'),
            'expiring'       => __('Expiring soon'),
            'problem'        => __('Not compliant'),
        ];
    }
}
