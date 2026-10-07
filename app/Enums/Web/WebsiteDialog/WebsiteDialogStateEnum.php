<?php

namespace App\Enums\Web\WebsiteDialog;

use App\Enums\EnumHelperTrait;

enum WebsiteDialogStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in-process';
    case READY = 'ready';

    public function stateIcon(): array
    {
        return [
            'in-process' => [
                'icon'    => 'fad fa-stop',
                'class'   => 'text-red-500',
                'tooltip' => __('Not published yet')
            ],
            'ready'      => [
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-green-500',
                'tooltip' => __('Published')
            ],
        ];
    }

    public static function labels(): array
    {
        return [
            'in-process' => __('In construction'),
            'ready'      => __('Ready'),
        ];
    }
}
