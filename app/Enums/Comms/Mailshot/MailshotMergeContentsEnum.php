<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 1 Apr 2026 14:33:14 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum MailshotMergeContentsEnum: string
{
    use EnumHelperTrait;

    case UNSUBSCRIBE = 'Unsubscribe';

    public static function contents(): array
    {
        return [
            [
                'name'  => __('Unsubscribe'),
                'value' => '[Unsubscribe]'
            ]
        ];
    }
}
