<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 15 Jun 2026 15:48:16 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateMasterFamiliesWithVolGrDiscount;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterFamilyGrVolFromShops
{
    use AsAction;

    private const array PRIORITY_SHOP_CODES = ['eu', 'uk', 'sk', 'es'];

    public string $commandSignature = 'repair:master_family_gr_vol_from_shops {master_shop : MasterShop slug)}';

    public function handle(MasterShop $masterShop, Command $command): void
    {
        $query = MasterProductCategory::where('master_shop_id', $masterShop->id)
            ->where('type', ProductCategoryTypeEnum::FAMILY->value);


        $shopsIds=Shop::where('master_shop_id', $masterShop->id)->pluck('id')->toArray();

        $offerCampaignIds = OfferCampaign::whereIn('shop_id', $shopsIds)->where('type', OfferCampaignTypeEnum::VOLUME_DISCOUNT)->pluck('id')->toArray();


        $total = (clone $query)->count();

        $bar = $command->getOutput()->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->start();

        $foundAnyOffer = false;

        $query->chunkById(200, function (Collection $masterFamilies) use ($bar, &$foundAnyOffer,$offerCampaignIds) {
            foreach ($masterFamilies as $masterFamily) {
                if ($this->repairFamily($masterFamily,$offerCampaignIds)) {
                    $foundAnyOffer = true;
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $command->newLine();
        $command->info('Completed.');

        if ($foundAnyOffer && !$masterShop->gold_reward_eligible) {
            $masterShop->updateQuietly(['gold_reward_eligible' => true]);
        }

        MasterShopHydrateMasterFamiliesWithVolGrDiscount::run($masterShop);
    }

    private function repairFamily(MasterProductCategory $masterFamily, array $offerCampaignIds): bool
    {



        $children = $masterFamily->productCategories()->with('shop')->get();

        if ($children->isEmpty()) {
            return false;
        }

        $activeOffers = Offer::whereIn('trigger_id', $children->pluck('id'))
            ->whereIn('offer_campaign_id',$offerCampaignIds)
            ->where('trigger_type', class_basename(ProductCategory::class))
            ->where('type', OfferTypeEnum::CATEGORY_QUANTITY_ORDERED_ORDER_INTERVAL->value)
            ->where('state', OfferStateEnum::ACTIVE)
            ->with('offerAllowances')
            ->get()
            ->keyBy('trigger_id');

        if ($activeOffers->isEmpty()) {
            return false;
        }

        /** @var ProductCategory $child */
        foreach ($children as $child) {
            $child->has_gr_vol_discount = $activeOffers->has($child->id);
        }

        ProductCategory::whereIn('id', $children->where('has_gr_vol_discount', true)->pluck('id'))
            ->update(['has_gr_vol_discount' => true]);
        ProductCategory::whereIn('id', $children->where('has_gr_vol_discount', false)->pluck('id'))
            ->update(['has_gr_vol_discount' => false]);

        $sourceChild = $this->resolveSourceChild($children);

        /** @var Offer $sourceOffer */
        $sourceOffer = $activeOffers->get($sourceChild->id);


        $this->updateMasterFamily($masterFamily, $sourceOffer);
        $this->syncFollowMasterGr($children, (bool)$sourceChild->has_gr_vol_discount);

        return true;
    }

    private function resolveSourceChild(Collection $children): ProductCategory
    {
        $childrenWithOffer = $children->where('has_gr_vol_discount', true);

        foreach (self::PRIORITY_SHOP_CODES as $shopCode) {
            $child = $childrenWithOffer->first(fn($c) => $c->shop?->code === $shopCode);
            if ($child) {
                return $child;
            }
        }

        return $childrenWithOffer->first();
    }

    private function updateMasterFamily(MasterProductCategory $masterFamily, ?Offer $sourceOffer): void
    {
        $quantity      = data_get($sourceOffer?->trigger_data, 'item_quantity');
        $percentageOff = data_get($sourceOffer?->offerAllowances->first()?->data, 'percentage_off');
        $percentage    = $percentageOff !== null ? (float)$percentageOff * 100 : null;

        $changes = [];

        if ($masterFamily->has_gr_vol_discount !== ($sourceOffer !== null)) {
            $changes['has_gr_vol_discount'] = $sourceOffer !== null;
        }

        if ($quantity !== null && $masterFamily->gr_vol_discount_quantity !== (int)$quantity) {
            $changes['gr_vol_discount_quantity'] = (int)$quantity;
        }

        if ($percentage !== null && (float)$masterFamily->gr_vol_discount_percentage !== $percentage) {
            $changes['gr_vol_discount_percentage'] = $percentage;
        }

        if (!empty($changes)) {



            $masterFamily->updateQuietly($changes);
        }
    }

    private function syncFollowMasterGr(Collection $children, bool $masterValue): void
    {
        $followIds = $children->where('has_gr_vol_discount', $masterValue)->pluck('id');
        $optOutIds = $children->where('has_gr_vol_discount', '!=', $masterValue)->pluck('id');

        if ($followIds->isNotEmpty()) {
            ProductCategory::whereIn('id', $followIds)->update(['follow_master_gr' => true]);
        }

        if ($optOutIds->isNotEmpty()) {
            ProductCategory::whereIn('id', $optOutIds)->update(['follow_master_gr' => false]);
        }
    }

    public function asCommand(Command $command): void
    {
        $slug = $command->argument('master_shop');

        $masterShop = MasterShop::where('slug', $slug)->firstOrFail();

        if (!$masterShop) {
            $command->error('Master shop not found.');

            return;
        }

        $command->info("Repairing: {$masterShop->slug}");
        $this->handle($masterShop, $command);
    }
}
