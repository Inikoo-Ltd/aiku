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

    public function getValue(): ?bool
    {
        return match ($this) {
            self::LIKE      => true,
            self::DISLIKE   => false,
            default         => null
        };
    }
}
