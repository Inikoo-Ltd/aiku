<?php

/*
 * Author Louis Perez
 * Created on 26-06-2026-11h-42m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Catalogue\Review;

use App\Enums\EnumHelperTrait;

enum ReviewReactionTargetEnum: string
{
    use EnumHelperTrait;

    case REVIEW = 'review';
    case REVIEW_REPLY = 'review_reply';
}
