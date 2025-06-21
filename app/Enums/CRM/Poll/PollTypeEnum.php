<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:02:50 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\CRM\Poll;

use App\Enums\EnumHelperTrait;

enum PollTypeEnum: string
{
    use EnumHelperTrait;

    case OPEN_QUESTION     = 'open_question';
    case OPTION            = 'option';

    public function label(): string
    {
        return match ($this) {
            self::OPEN_QUESTION => __('Open Question'),
            self::OPTION        => __('Multiple Choice'),
        };
    }

    public static function stateIcon(): array
    {
        return [
            self::OPEN_QUESTION->value => [
                'tooltip' => __('Open Question'),
                'icon'    => 'fal fa-question-circle',
                'class'   => 'text-blue-500'
            ],
            self::OPTION->value => [
                'tooltip' => __('Option'),
                'icon'    => 'fal fa-list',
                'class'   => 'text-green-500'
            ]
        ];
    }
}
