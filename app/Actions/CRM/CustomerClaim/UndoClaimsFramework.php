<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The only way back from claims only for a shop, for engineers in an emergency: organisation admins
 * can switch a shop to claims only but never back. Claims already made stay.
 */
class UndoClaimsFramework
{
    use AsAction;

    public string $commandSignature = 'claims:undo-framework {shop : shop slug}';

    public string $commandDescription = 'Take a shop off claims only (emergency, engineers only)';

    public function handle(Shop $shop): Shop
    {
        $settings = $shop->settings ?? [];
        data_set($settings, 'claims.framework_since', null);
        $shop->update(['settings' => $settings]);

        return $shop;
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->first();
        if (!$shop) {
            $command->error('No shop '.$command->argument('shop'));

            return 1;
        }

        $this->handle($shop);
        $command->info("{$shop->name} no longer works through claims only.");

        return 0;
    }
}
