<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Production\Production;

use App\Enums\EnumHelperTrait;

enum ProductionStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case OPEN = 'open';
    case CLOSING_DOWN = 'closing_down';
    case CLOSED = 'closed';

    public static function label(string $value): string
    {
        return match ($value) {
            self::IN_PROCESS => 'In Process',
            self::OPEN => 'Open',
            self::CLOSING_DOWN => 'Closing Down',
            self::CLOSED => 'Closed',
            default => $value,
        };
    }

    public static function stateIcon(string $value): array
    {
        return match ($value) {
            self::IN_PROCESS->value => [
                'tooltip' => self::label($value),
                'icon' => 'fal fa-seedling',
                'class' => 'text-red-500',  // Color for normal icon (Aiku)
            ],
            self::OPEN->value => [
                'tooltip' => self::label($value),
                'icon' => 'fal fa-seedling',
                'class' => 'text-green-500 animate-pulse',  // Color for normal icon (Aiku)
            ],
            self::CLOSING_DOWN => [
                'tooltip' => self::label($value),
                'icon' => 'fal fa-do-not-enter',
                'class' => 'text-gray-300',  // Color for normal icon (Aiku)
            ],
            self::CLOSED->value => [
                'tooltip' => self::label($value),
                'icon' => 'fal fa-do-not-enter',
                'class' => 'text-red-500',  // Color for normal icon (Aiku)
            ],
            default => [
                'tooltip' => self::label($value),
                'icon' => 'fal fa-do-not-enter',
                'class' => 'text-blue-500',  // Color for normal icon (Aiku)
            ],
        };
    }
}
