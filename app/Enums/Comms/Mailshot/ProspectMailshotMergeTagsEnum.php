<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 7 May 2026 10:45:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Enums\Comms\Mailshot;

use App\Enums\EnumHelperTrait;

enum ProspectMailshotMergeTagsEnum: string
{
    use EnumHelperTrait;

    case PROSPECT_NAME = 'Prospect Name';
    case PROSPECT_EMAIL = 'Prospect Email';
    case PROSPECT_PHONE = 'Prospect Phone';
    case PROSPECT_COMPANY_NAME = 'Prospect Company Name';
    case UNSUBSCRIBE = 'Unsubscribe';


    public static function tags(): array
    {
        return [
            [
                'name'  => __('Prospect Name'),
                'value' => '[Prospect Name]'
            ],
            [
                'name'  => __('Prospect Email'),
                'value' => '[Prospect Email]'
            ],
            [
                'name'  => __('Prospect Phone'),
                'value' => '[Prospect Phone]'
            ],
            [
                'name'  => __('Prospect Company Name'),
                'value' => '[Prospect Company Name]'
            ],
            [
                'name'  => __('Unsubscribe'),
                'value' => '[Unsubscribe]'
            ],
        ];
    }
}
