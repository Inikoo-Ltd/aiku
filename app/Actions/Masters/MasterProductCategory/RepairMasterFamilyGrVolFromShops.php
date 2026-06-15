<?php

namespace App\Actions\Masters\MasterProductCategory;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterFamilyGrVolFromShops
{
    use AsAction;

    private const PRIORITY_SHOP_CODES = ['eu', 'uk', 'sk', 'es'];

    public function handle(MasterShop $masterShop, Command $command): void
    {
        MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY->value)
            ->chunkById(200, function (Collection $masterFamilies) use ($command) {
                foreach ($masterFamilies as $masterFamily) {
                    $this->repairFamily($masterFamily, $command);
                }
            });

        $command->info('Repair completed.');
    }

    private function repairFamily(MasterProductCategory $masterFamily, Command $command): void
    {
        $children = $masterFamily->productCategories()
            ->with('shop')
            ->get();

        if ($children->isEmpty()) {
            $command->line("[{$masterFamily->code}] No children found, skipping.");
            return;
        }

        $this->fillNullChildrenFromMaster($children, $masterFamily);

        $masterValue = $this->determineMasterValue($children, $masterFamily, $command);

        if ($masterFamily->has_gr_vol_discount !== $masterValue) {
            $masterFamily->updateQuietly(['has_gr_vol_discount' => $masterValue]);
            $command->info("[{$masterFamily->code}] Master updated to: " . ($masterValue ? 'true' : 'false'));
        }

        $this->syncFollowMasterGr($children, $masterValue, $command, $masterFamily->code);
    }

    private function fillNullChildrenFromMaster(Collection $children, MasterProductCategory $masterFamily): void
    {
        $nullChildren = $children->whereNull('has_gr_vol_discount');

        if ($nullChildren->isEmpty()) {
            return;
        }

        ProductCategory::whereIn('id', $nullChildren->pluck('id'))
            ->update([
                'has_gr_vol_discount' => $masterFamily->has_gr_vol_discount,
                'follow_master_gr'    => true,
            ]);

        foreach ($nullChildren as $child) {
            $child->has_gr_vol_discount = $masterFamily->has_gr_vol_discount;
            $child->follow_master_gr    = true;
        }
    }

    private function determineMasterValue(Collection $children, MasterProductCategory $masterFamily, Command $command): bool
    {
        $distinctValues = $children->pluck('has_gr_vol_discount')->unique();

        if ($distinctValues->count() === 1) {
            return (bool) $distinctValues->first();
        }

        $command->warn("[{$masterFamily->code}] Children disagree on GR/VOL — resolving by priority shop.");

        foreach (self::PRIORITY_SHOP_CODES as $shopCode) {
            $child = $children->first(fn ($c) => $c->shop?->code === $shopCode);
            if ($child) {
                $command->info("[{$masterFamily->code}] Using shop [{$shopCode}] value: " . ($child->has_gr_vol_discount ? 'true' : 'false'));
                return (bool) $child->has_gr_vol_discount;
            }
        }

        $fallback = $children->first();
        $command->warn("[{$masterFamily->code}] No priority shop found, falling back to [{$fallback->shop?->code}].");

        return (bool) $fallback->has_gr_vol_discount;
    }

    private function syncFollowMasterGr(Collection $children, bool $masterValue, Command $command, string $familyCode): void
    {
        $matching    = $children->where('has_gr_vol_discount', $masterValue)->pluck('id');
        $notMatching = $children->where('has_gr_vol_discount', '!=', $masterValue)->pluck('id');

        if ($matching->isNotEmpty()) {
            ProductCategory::whereIn('id', $matching)->update(['follow_master_gr' => true]);
        }

        if ($notMatching->isNotEmpty()) {
            ProductCategory::whereIn('id', $notMatching)->update(['follow_master_gr' => false]);
            $command->warn("[{$familyCode}] {$notMatching->count()} child(ren) marked follow_master_gr = false.");
        }
    }

    public string $commandSignature = 'repair:master_family_gr_vol_from_shops {master_shop : MasterShop slug}';

    public function asCommand(Command $command): void
    {
        $masterShop = MasterShop::where('slug', $command->argument('master_shop'))->first();

        if (!$masterShop) {
            $command->error('Master shop not found.');
            return;
        }

        $command->info("Repairing GR/VOL for master shop: {$masterShop->slug}");

        $this->handle($masterShop, $command);
    }
}
