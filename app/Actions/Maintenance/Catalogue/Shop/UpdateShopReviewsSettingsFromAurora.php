<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Dec 2025 21:03:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue\Shop;

use App\Actions\Traits\WithOrganisationSource;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateShopReviewsSettingsFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;


    public function getCommandSignature(): string
    {
        return 'maintenance:update_shop_reviews_settings {organisation}';
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $organisation       = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $auroraShops = DB::connection('aurora')->table('Store Dimension')->select(['Store Key','Store Reviews Settings'])->get();

        foreach ($auroraShops as $auroraShop) {
            $shop = $organisation->shops()->where('source_id', $organisation->id.':'.$auroraShop->{'Store Key'})->first();
            if ($shop) {
                $settings = $shop->settings;


                if ($auroraShop->{'Store Reviews Settings'} != '') {
                    $command->info('Updating shop '.$shop->slug);
                    $reviewSettings=json_decode($auroraShop->{'Store Reviews Settings'}, true);

                    data_set($reviewSettings, 'enabled', true);

                    data_set($settings, 'reviews', $reviewSettings);
                } else {
                    data_set($settings, 'reviews.enabled', false);
                }

                $shop->updateQuietly(['settings' => $settings]);

            }
        }


        return 0;
    }

}
