<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2026 14:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping;

use Illuminate\Support\Facades\Log;

/**
 * Sales channel platforms without a sandbox (Shopify, WooCommerce, Magento) are reached at the
 * customer's own live store, using the credentials held in the database. A non production
 * environment restored from a production database would therefore write to real customer shops,
 * so every outbound call to those platforms is refused outside production.
 */
class PlatformOutboundGuard
{
    public static function blocks(string $platform, string $context = ''): bool
    {
        if (app()->isProduction()) {
            return false;
        }

        Log::warning(sprintf(
            '%s call blocked: %s runs against live customer stores and is only allowed in production (env: %s)%s',
            $platform,
            $platform,
            app()->environment(),
            $context ? ' - '.$context : ''
        ));

        return true;
    }
}
