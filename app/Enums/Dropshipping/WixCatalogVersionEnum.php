<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Dropshipping;

use App\Enums\EnumHelperTrait;

/**
 * Wix Stores ships two mutually incompatible catalogue APIs. Which one a site answers is fixed
 * for that site, and only the Catalog Versioning API can tell us which.
 *
 * @see https://dev.wix.com/docs/rest/business-solutions/stores/catalog-versioning/introduction
 */
enum WixCatalogVersionEnum: string
{
    use EnumHelperTrait;

    case V1 = 'V1_CATALOG';
    case V3 = 'V3_CATALOG';
    case STORES_NOT_INSTALLED = 'STORES_NOT_INSTALLED';

    public function labels(): array
    {
        return [
            'V1_CATALOG'           => 'Catalog V1',
            'V3_CATALOG'           => 'Catalog V3',
            'STORES_NOT_INSTALLED' => 'Wix Stores not installed',
        ];
    }
}
