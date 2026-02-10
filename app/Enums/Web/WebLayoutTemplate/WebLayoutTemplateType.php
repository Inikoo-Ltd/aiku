<?php

/*
 * author Louis Perez
 * created on 10-02-2026-13h-11m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Enums\Web\WebLayoutTemplate;

use App\Enums\EnumHelperTrait;

enum WebLayoutTemplateType: string
{
    use EnumHelperTrait;

    case WEBPAGE  = 'webpage';
    case WEBBLOCK = 'web_block';
}
