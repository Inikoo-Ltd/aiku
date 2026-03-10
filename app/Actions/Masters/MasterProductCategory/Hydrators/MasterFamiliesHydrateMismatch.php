<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-47m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterFamiliesHydrateMismatch implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $masterFamilyID): string
    {
        return $masterFamilyID ?? 'empty';
    }

    public function handle($onlyStats = false): void
    {
        if (!$onlyStats) {
            MasterProductCategory::where('type', MasterProductCategoryTypeEnum::FAMILY)
                ->orderBy('id')
                ->chunkById(1000, function ($masterFamilies) {
                    foreach ($masterFamilies as $masterFamily) {
                        $hasMismatch = $masterFamily->masterAssets()->where('mismatch_detected', true)->exists();

                        if ($hasMismatch) {
                            $masterFamily->updateQuietly(['mismatch_detected' => true]);
                        } else {
                            $masterFamily->updateQuietly(['mismatch_detected' => false]);
                        }
                    }
                });
        }

        MasterShop::each(function ($masterShop) {
            $countMismatch = MasterProductCategory::where('master_shop_id', $masterShop->id)
                ->where('type', MasterProductCategoryTypeEnum::FAMILY)
                ->where('mismatch_detected', true)
                ->count();

            $masterShop->stats()->update(['number_mismatched_master_families' => $countMismatch]);
        });
    }


}
