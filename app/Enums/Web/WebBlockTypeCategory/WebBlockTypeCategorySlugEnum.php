<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Jul 2023 14:40:23 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Web\WebBlockTypeCategory;

use App\Enums\EnumHelperTrait;

enum WebBlockTypeCategorySlugEnum: string
{
    use EnumHelperTrait;

    case PRODUCT = 'product';
    case FAMILY = 'family';
    case CATEGORY = 'category';
    case CHECKOUT = 'checkout';
    case FOOTER = 'footer';
    case HEADER = 'header';
    case BANNER = 'banner';
    case TEXT = 'text';
    case PICTURE = 'picture';
    case MAPS = 'maps';
    case CTE = 'cta';
    case PRODUCTS = 'products';
    case REVIEWS  = 'reviews';
    case IFRAME   = 'iframe';
    case BENTO_GRID   = 'bento-grid';
    case MENU   = 'menu';
    case GRID   = 'grid';
    case OVERVIEW   = 'overview';
    case FAMILY   = 'family';
    case SCRIPT   = 'script';
}
