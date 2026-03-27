<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateNumberMismatches;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterFamiliesHydrateMismatch
{
    use AsAction;


    public function handle(MasterProductCategory $masterFamily): void
    {
        $hasMismatch = $masterFamily->masterAssets()->where('mismatch_detected', true)->exists();

        if ($hasMismatch) {
            $masterFamily->updateQuietly(['mismatch_detected' => true]);
        } else {
            $masterFamily->updateQuietly(['mismatch_detected' => false]);
        }

        MasterShopHydrateNumberMismatches::dispatch($masterFamily->masterShop)->delay(now()->addSeconds(60));
    }

    public function getCommandSignature(): string
    {
        return 'master_product_categories:hydrate_mismatch {master_product_category?}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->argument('master_product_category')) {
            $masterProductCategory = MasterProductCategory::where('slug', $command->argument('master_product_category'))->firstOrFail();
            $this->handle($masterProductCategory);

            return;
        }

        MasterProductCategory::where('type', MasterProductCategoryTypeEnum::FAMILY)
            ->orderBy('id')
            ->chunkById(1000, function ($masterFamilies) {
                foreach ($masterFamilies as $masterFamily) {
                    $this->handle($masterFamily);
                }
            });
    }


}
