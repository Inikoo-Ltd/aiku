<?php

namespace App\Enums\Comms\Email;

use App\Enums\EnumHelperTrait;

enum EmailEditorEnum: string
{
    use EnumHelperTrait;

    case AIKU    = 'aiku';
    case BEEFREE = 'beefree';

    public static function labels(): array
    {
        return [
            'aiku'    => __('Aiku email editor'),
            'beefree' => __('BeeFree'),
        ];
    }
}
