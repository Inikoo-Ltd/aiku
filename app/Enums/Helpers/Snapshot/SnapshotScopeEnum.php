<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Jun 2024 13:08:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Snapshot;

use App\Enums\EnumHelperTrait;

enum SnapshotScopeEnum: string
{
    use EnumHelperTrait;

    case WEBPAGE        = 'webpage';
    case HEADER         = 'header';
    case FOOTER         = 'footer';
    case MENU           = 'menu';
    case COLOR          = 'color';
    case BANNER         = 'banner';
    case EMAIL_TEMPLATE = 'email_template';
    case PRODUCT_TEMPLATE = 'product_template';

    public static function labels(): array
    {
        return [
            'webpage'        => __('Webpage'),
            'header'         => __('Header'),
            'footer'         => __('Footer'),
            'banner'         => __('Banner'),
            'color'          => __('Color'),
            'menu'           => __('Menu'),
            'email_template' => __('Email Template')
        ];
    }
}
