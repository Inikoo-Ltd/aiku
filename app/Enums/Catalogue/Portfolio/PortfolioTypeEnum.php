<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Portfolio;

use App\Enums\EnumHelperTrait;

enum PortfolioTypeEnum: string
{
    use EnumHelperTrait;

    case MANUAL = 'manual';
    case SHOPIFY = 'shopify';
    case TIKTOK = 'tiktok';
    case WOOCOMMERCE = 'woocommerce';
}
