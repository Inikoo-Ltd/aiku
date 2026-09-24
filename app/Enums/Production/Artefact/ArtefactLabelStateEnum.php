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

    public static function stateIcon(): array
    {
        return [
            'raw'       => [
                'tooltip' => __('Raw, nothing designed yet'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-gray-400'
            ],
            'processed' => [
                'tooltip' => __('Designed, not published'),
                'icon'    => 'fal fa-pencil-ruler',
                'class'   => 'text-amber-500'
            ],
            'published' => [
                'tooltip' => __('Published, ready to print from the to produce board'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500'
            ],
        ];
    }
}
