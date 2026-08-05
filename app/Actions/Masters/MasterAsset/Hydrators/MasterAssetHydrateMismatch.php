<?php

/*
 * author Louis Perez
 * created on 09-03-2026-09h-10m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Hydrators;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateNumberMismatches;
use App\Actions\Traits\WithEnumStats;
use App\Actions\Masters\MasterAsset\GetMasterAssetAnomalies;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class MasterAssetHydrateMismatch implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(int|null $masterAssetID): string
    {
        return $masterAssetID ?? 'empty';
    }

    public function handle(MasterAsset $masterProduct, bool $hydrateMasterShopStats = true): void
    {
        $anomalies = GetMasterAssetAnomalies::run($masterProduct);

        /*
         * Flags are set by two bulk updates rather than by loading every product again:
         * this runs once per master and the shop wide rehydrate walks 11k of them.
         */
        $mismatchedIds = array_keys(array_filter($anomalies, fn ($anomaly) => !empty($anomaly['issues'])));
        $anyMismatch   = (bool)$mismatchedIds;

        if ($mismatchedIds) {
            $masterProduct->products()
                ->whereIn('products.id', $mismatchedIds)
                ->where(function ($query) {
                    // never migrated products hold NULL, which no !=  comparison matches
                    $query->where('mismatch_with_master_detected', '!=', true)
                        ->orWhereNull('mismatch_with_master_detected');
                })
                ->update(['mismatch_with_master_detected' => true]);
        }

        $masterProduct->products()
            ->whereNotIn('products.id', $mismatchedIds)
            ->where(function ($query) {
                $query->where('mismatch_with_master_detected', '!=', false)
                    ->orWhereNull('mismatch_with_master_detected');
            })
            ->update(['mismatch_with_master_detected' => false]);

        $masterProduct->updateQuietly([
            'mismatch_detected' => $anyMismatch
        ]);

        /*
         * Recomputed across the family's masters rather than set to true and left there:
         * a flag that only ever turns on stops meaning anything after the first mismatch.
         */
        $masterFamily = $masterProduct->masterFamily;
        if ($masterFamily) {
            $familyHasMismatch = $anyMismatch || $masterFamily->masterAssets()
                ->where('master_assets.id', '!=', $masterProduct->id)
                ->where('mismatch_detected', true)
                ->exists();

            if ($masterFamily->mismatch_detected !== $familyHasMismatch) {
                $masterFamily->updateQuietly([
                    'mismatch_detected' => $familyHasMismatch
                ]);
            }
        }

        /*
         * Six counts over the whole master shop, so it is skipped while walking every
         * master of that shop and run once when the walk is done instead.
         */
        if ($hydrateMasterShopStats) {
            MasterShopHydrateNumberMismatches::run($masterProduct->masterShop);
        }
    }

    public function getCommandSignature(): string
    {
        return 'master_asset:hydrate_mismatch {--master_asset=} {--master_shop=}';
    }

    public function asCommand(Command $command): void
    {
        if ($command->option('master_asset')) {
            $masterAsset = MasterAsset::where('slug', $command->option('master_asset'))->firstOrFail();
            $this->handle($masterAsset);

            return;
        }

        $masterShop = null;
        $masterShopSlug = $command->option('master_shop');
        if ($masterShopSlug) {
            $masterShop = MasterShop::where('slug', $masterShopSlug)->first();
        }

        /*
         * Inactive masters are cleared in one statement rather than walked: they are 28,598
         * of aw's 40,436 and nothing reports on them, but a flag left set from before they
         * closed would keep showing.
         */
        $clearedInactive = MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)
            ->when($masterShop, fn ($q) => $q->where('master_shop_id', $masterShop->id))
            ->where('status', false)
            ->where(fn ($q) => $q->where('mismatch_detected', true)->orWhereNull('mismatch_detected'))
            ->update(['mismatch_detected' => false]);

        if ($clearedInactive) {
            $command->info($clearedInactive.' inactive masters cleared');
        }

        $baseQuery = fn () => MasterAsset::where('type', MasterAssetTypeEnum::PRODUCT)
            ->when($masterShop, fn ($q) => $q->where('master_shop_id', $masterShop->id))
            ->where('status', true);

        $total = $baseQuery()->count();

        $bar   = $command->getOutput()->createProgressBar($total);
        $bar->setFormat('debug');
        $bar->start();

        $baseQuery()
            ->orderBy('id')
            ->chunkById(1000, function ($masterProducts) use ($bar) {
                foreach ($masterProducts as $masterProduct) {
                    $this->handle($masterProduct, hydrateMasterShopStats: false);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();

        $masterShops = $masterShop
            ? collect([$masterShop])
            : MasterShop::all();
        $masterShops->each(fn (MasterShop $shop) => MasterShopHydrateNumberMismatches::run($shop));
    }


}
