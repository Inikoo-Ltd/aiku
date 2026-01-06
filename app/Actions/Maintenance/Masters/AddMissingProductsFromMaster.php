<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jan 2026 15:47:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Masters\CloneProductsFromMaster;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMissingProductsFromMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $masterShop): void
    {
        /** @var MasterAsset $masterProduct */
        foreach ($masterShop->masterAssets()->where('type', MasterAssetTypeEnum::PRODUCT)->where('is_main', true)->get() as $masterProduct) {
            /** @var Shop $shop */
            foreach ($masterShop->shops()->where('is_aiku', true)->get() as $shop) {
                CloneProductsFromMaster::make()->upsertProduct($shop, $masterProduct);
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'repair:add_missing_product_s_from_master {master}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master'))->firstOrFail();

        $this->handle($masterShop);

        return 0;
    }


}
