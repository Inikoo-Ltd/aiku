<?php

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewRatingDimensionEnum: string
{
    use EnumHelperTrait;

    case A = 'a';
    case B = 'b';
    case C = 'c';
    case D = 'd';
    case E = 'e';

    public static function labels(): array
    {
        return [
            self::A->value => 'A',
            self::B->value => 'B',
            self::C->value => 'C',
            self::D->value => 'D',
            self::E->value => 'E',
        ];
    }
}
