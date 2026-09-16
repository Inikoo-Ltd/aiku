<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Mon, 14 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Enums\Production\Artefact;

use App\Enums\EnumHelperTrait;

enum ArtefactLabelStateEnum: string
{
    use EnumHelperTrait;

    case RAW       = 'raw';
    case PROCESSED = 'processed';
    case PUBLISHED = 'published';

    public static function labels(): array
    {
        return [
            'raw'       => __('Raw'),
            'processed' => __('Processed'),
            'published' => __('Published'),
        ];
    }
}
