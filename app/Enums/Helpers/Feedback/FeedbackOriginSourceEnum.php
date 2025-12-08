<?php

/*
 * author Arya Permana - Kirin
 * created on 14-11-2024-11h-01m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\Helpers\Feedback;

use App\Enums\EnumHelperTrait;

enum FeedbackOriginSourceEnum: string
{
    use EnumHelperTrait;

    case REFUND = 'refund';
    case RETURN = 'return';
    case REPLACEMENT = 'replacement';
    case COMPLAIN = 'complain';
    case REVIEW = 'review';
    case INSPECTION = 'inspection';
    case OTHER = 'other';

}
