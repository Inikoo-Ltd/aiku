<?php

namespace App\Enums\Web\WebsiteDialog;

use App\Enums\EnumHelperTrait;

enum WebsiteDialogTriggerEnum: string
{
    use EnumHelperTrait;

    case AUTOMATIC = 'automatic';
    case ON_CLICK = 'on_click';

    public static function labels(): array
    {
        return [
            'automatic' => __('Automatically, when the page opens'),
            'on_click'  => __('Only when a button or link opens it'),
        ];
    }
}
