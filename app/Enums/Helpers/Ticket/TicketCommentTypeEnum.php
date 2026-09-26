<?php

/*
 * Author Louis Perez
 * Created on 24-09-2026-09h-13m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Enums\Helpers\Ticket;

use App\Enums\EnumHelperTrait;

enum TicketCommentTypeEnum: string
{
    use EnumHelperTrait;

    case COMMENT = 'comment';
    case WAITING_FOR_DEPLOYMENT = 'waiting_for_deployment';
    case POST_MORTEM = 'post_mortem';
}
