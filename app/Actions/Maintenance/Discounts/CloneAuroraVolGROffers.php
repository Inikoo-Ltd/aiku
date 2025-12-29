<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Dec 2025 22:48:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Discounts\Offer\StoreVolumeGRDiscount;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneAuroraVolGROffers
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'clone:aurora_vol_gr_offers {organisation} {from_shop} {to_shop}';

    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);

        $fromShop = Shop::where('organisation_id', $organisation->id)->where('slug', $command->argument('from_shop'))->firstOrFail();
        $toShop   = Shop::where('organisation_id', $organisation->id)->where('slug', $command->argument('to_shop'))->firstOrFail();
        if ($fromShop->id == $toShop->id) {
            $command->error('From and To shops must be different.');

            return 1;
        }

        $fromShopSource = explode(':', $fromShop->source_id);


        $volCampaign = DB::connection('aurora')->table('Deal Campaign Dimension')->select('Deal Campaign Key')->where('Deal Campaign Store Key', $fromShopSource[1])->where('Deal Campaign Code', 'VL')->first();

        DB::connection('aurora')
            ->table('Deal Dimension')
            ->join('Deal Component Dimension', 'Deal Dimension.Deal Key', '=', 'Deal Component Dimension.Deal Component Deal Key')
            ->where('Deal Status', 'Active')
            ->whereIn('Deal Campaign Key', [
                $volCampaign->{'Deal Campaign Key'}
            ])->orderBy('Deal Key', 'desc')
            ->chunk(100, function ($auroraOffers) use ($toShop, $organisation, $command) {
                foreach ($auroraOffers as $auroraOffer) {
                    $fromFamily = ProductCategory::where('source_family_id', $organisation->id.':'.$auroraOffer->{'Deal Trigger Key'})->first();


                    if ($fromFamily) {
                        $toFamily = ProductCategory::where('shop_id', $toShop->id)->where('type', ProductCategoryTypeEnum::FAMILY)
                            ->whereRaw("lower(code) = lower(?)", [$fromFamily->code])
                            ->first();

                        if ($toFamily && !Offer::where('shop_id', $toShop->id)
                                ->where('type', 'Category Quantity Ordered Order Interval')
                                ->where('trigger_id', $toFamily->id)->exists()) {
                            StoreVolumeGRDiscount::make()->action(
                                $toFamily,
                                [
                                    'trigger_data_item_quantity' => $auroraOffer->{'Deal Terms'},
                                    'percentage_off'             => $auroraOffer->{'Deal Component Allowance'},
                                    'interval'                   => 30
                                ]
                            );
                            $command->info("Offer created for $toFamily->code");
                        }


                    }
                }
            });


        return 0;
    }


}
