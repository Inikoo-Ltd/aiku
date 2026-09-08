<?php

namespace App\Enums\Production\RawMaterial;

use App\Enums\EnumHelperTrait;

enum RawMaterialStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS     = 'in_process';
    case IN_USE         = 'in_use';
    case ORPHAN         = 'orphan';
    case DISCONTINUED   = 'discontinued';

    public static function labels(): array
    {
        return [
            self::IN_PROCESS->value   => 'In Process',
            self::IN_USE->value       => 'In Use',
            self::ORPHAN->value       => 'Orphan',
            self::DISCONTINUED->value => 'Discontinued',
        ];
    }

    public static function stateIcon(): array
    {
        return [
            self::IN_PROCESS->value   => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-indigo-500'
            ],
            self::IN_USE->value       => [
                'tooltip' => __('In use'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500'
            ],
            self::ORPHAN->value       => [
                'tooltip' => __('Orphan, not used in any recipe'),
                'icon'    => 'fal fa-ghost',
                'class'   => 'text-gray-400'
            ],
            self::DISCONTINUED->value => [
                'tooltip' => __('Discontinued'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500'
            ],
        ];
    }




}
