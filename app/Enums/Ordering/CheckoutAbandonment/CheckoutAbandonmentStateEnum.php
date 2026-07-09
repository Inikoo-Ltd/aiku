<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 08 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\Ordering\CheckoutAbandonment;

use App\Enums\EnumHelperTrait;

enum CheckoutAbandonmentStateEnum: string
{
    use EnumHelperTrait;

    case ABANDONED = 'abandoned';
    case RECOVERED = 'recovered';
}
