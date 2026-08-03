<?php

/*
 * Author Louis Perez
 * Created on 26-06-2026-11h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewReactionTypeEnum: string
{
    use EnumHelperTrait;

    case LIKE = 'like';
    case DISLIKE = 'dislike';

    public static function getValue(?string $value = ''): ?bool
    {
        return match ($value) {
            self::LIKE->value       => true,
            self::DISLIKE->value    => false,
            default                 => null
        };
    }
}
