<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\CRM\Customer;

use App\Enums\EnumHelperTrait;

enum CustomerWebActivityTypeEnum: string
{
    use EnumHelperTrait;

    case PageView    = 'page_view';
    case ProductView = 'product_view';
    case AddToBasket = 'add_to_basket';
}
