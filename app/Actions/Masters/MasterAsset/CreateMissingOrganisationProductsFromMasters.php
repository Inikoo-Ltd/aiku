<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 05 Aug 2026 00:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Catalogue\Product\StoreProductFromMasterProduct;
use App\Actions\Masters\MasterProductCategory\StoreDepartmentFromMasterDepartment;
use App\Actions\Masters\MasterProductCategory\StoreFamilyFromMasterFamily;
use App\Actions\Masters\MasterProductCategory\StoreSubDepartmentFromMasterSubDepartment;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Creates the products an organisation's shops are missing, from the masters a
 * reference shop already sells.
 *
 * Shops of an organisation that follow the same master shop are meant to carry the
 * same catalogue; where one lacks a product the others sell, it dropped out rather
 * than being excluded on purpose. The reference shop decides what "should be there":
 * a master is only created where that shop sells it and the master is still active.
 *
 * Shops attached to a different master shop are never touched — an organisation also
 * holds dropshipping and specialty shops whose catalogue is deliberately its own.
 */
class CreateMissingOrganisationProductsFromMasters
{
    use AsAction;

    /**
     * @return array{masters: int, created: int, changes: list<string>, failures: list<string>}
     */
    public function handle(Organisation $organisation, Shop $referenceShop, bool $dryRun = true, ?int $limit = null, ?callable $report = null): array
    {
        /*
         * Open shops only: creating the shop's copy of a master family is limited to
         * them, so a closed shop would be reported as due products it can never get.
         */
        $targetShops = Shop::where('organisation_id', $organisation->id)
            ->where('master_shop_id', $referenceShop->master_shop_id)
            ->where('state', ShopStateEnum::OPEN)
            ->where('id', '!=', $referenceShop->id)
            ->get();

        if ($targetShops->isEmpty()) {
            return ['masters' => 0, 'created' => 0, 'changes' => [], 'failures' => []];
        }

        $masters  = 0;
        $created  = 0;
        $changes  = [];
        $failures = [];

        /*
         * status and is_for_sale are the signals that hold up: of 28,600 inactive aw
         * masters only 27 still have a live product. discontinued_at does not — 917
         * masters carry one while selling, 719 of them sold after that very date — so
         * it is deliberately not used to exclude anything here.
         */
        MasterAsset::where('master_shop_id', $referenceShop->master_shop_id)
            ->where('type', MasterAssetTypeEnum::PRODUCT)
            ->where('status', true)
            ->where('is_for_sale', true)
            ->where(fn ($query) => $query->whereNull('mark_for_discontinued')->orWhere('mark_for_discontinued', false))
            ->whereHas('products', fn ($query) => $query
                ->where('shop_id', $referenceShop->id)
                ->where('products.status', '!=', ProductStatusEnum::DISCONTINUED)
                ->where('is_for_sale', true))
            ->with('masterFamily')
            ->chunkById(50, function ($masterAssets) use ($targetShops, $dryRun, $limit, $report, &$masters, &$created, &$changes, &$failures) {
                foreach ($masterAssets as $masterAsset) {
                    if ($limit && $created >= $limit) {
                        return false;
                    }

                    if (!$masterAsset->masterFamily) {
                        continue;
                    }

                    $existingShopIds = $masterAsset->products()->pluck('shop_id')->all();
                    $missingShops    = $targetShops->whereNotIn('id', $existingShopIds);

                    if ($missingShops->isEmpty()) {
                        continue;
                    }

                    $masters++;

                    if ($dryRun) {
                        foreach ($missingShops as $shop) {
                            $created++;
                            $changes[] = $masterAsset->code.' → '.$shop->code;
                            $report && $report($masterAsset->code.' → '.$shop->code);
                        }

                        continue;
                    }

                    /*
                     * One shop that cannot take a product — a webpage url already used,
                     * a family that will not build — must not end a sweep of hundreds:
                     * it is recorded and the rest continue. Re-running resumes, since
                     * products that now exist are skipped.
                     */
                    foreach ($missingShops as $shop) {
                        try {
                            $this->createIn($masterAsset, collect([$shop]));
                            $created++;
                            $changes[] = $masterAsset->code.' → '.$shop->code;
                            $report && $report($masterAsset->code.' → '.$shop->code);
                        } catch (Throwable $exception) {
                            $failures[] = $masterAsset->code.' → '.$shop->code.': '.$exception->getMessage();
                            $report && $report('FAILED '.$masterAsset->code.' → '.$shop->code.': '.$exception->getMessage());
                        }
                    }

                    /*
                     * Relations are dropped as we go: this walks tens of thousands of
                     * masters and each one drags its family, its shop copies and every
                     * product created from it, which is what exhausted memory before.
                     */
                    $masterAsset->unsetRelation('masterFamily');
                    $masterAsset->unsetRelation('products');
                }
            });

        return ['masters' => $masters, 'created' => $created, 'changes' => $changes, 'failures' => $failures];
    }

    private function createIn(MasterAsset $masterAsset, $missingShops): void
    {
        $shopProducts = [];
        foreach ($missingShops as $shop) {
            /*
             * A family hangs off the shop's copy of the master department, and creating
             * one without it throws, so the branch above it is built first when missing.
             */
            $this->ensureAncestors($masterAsset, $shop);

            /*
             * The product hangs off the shop's copy of the master family, so a shop that
             * never carried anything from that family needs the family created first.
             */
            $hasFamily = $masterAsset->masterFamily->productCategories()
                ->where('product_categories.shop_id', $shop->id)
                ->exists();

            if (!$hasFamily) {
                StoreFamilyFromMasterFamily::make()->action($masterAsset->masterFamily, [
                    /* A shop with no website cannot hold webpages. */
                    'shop_family' => [
                        $shop->id => ['create_webpage' => (bool)$shop->website],
                    ],
                ]);
            }

            $shopProducts[$shop->id] = ['create_in_shop' => 'Yes'];
        }

        /*
         * StoreProductFromMasterProduct walks the master family's shop copies off a
         * loaded relation, so a family created a moment ago is invisible to it unless
         * the relation is read again.
         */
        $masterAsset->load('masterFamily.productCategories');

        /*
         * is_for_sale is passed rather than left to the master: a product must never
         * arrive in a new shop on sale when its source is not.
         */
        StoreProductFromMasterProduct::make()->action($masterAsset, [
            'shop_products'     => $shopProducts,
            'is_for_sale'       => (bool)$masterAsset->is_for_sale,
            'is_minion_variant' => $masterAsset->is_minion_variant,
        ]);
    }

    private function ensureAncestors(MasterAsset $masterAsset, Shop $shop): void
    {
        $masterFamily = $masterAsset->masterFamily;

        if ($masterFamily->masterDepartment
            && !$masterFamily->masterDepartment->productCategories()->where('shop_id', $shop->id)->exists()) {
            StoreDepartmentFromMasterDepartment::make()->action($masterFamily->masterDepartment, [
                'shop_department' => [
                    $shop->id => ['create_webpage' => (bool)$shop->website],
                ],
            ]);
        }

        if ($masterFamily->masterSubDepartment
            && !$masterFamily->masterSubDepartment->productCategories()->where('shop_id', $shop->id)->exists()) {
            StoreSubDepartmentFromMasterSubDepartment::make()->action($masterFamily->masterSubDepartment, [
                'shop_sub_department' => [
                    $shop->id => ['create_webpage' => (bool)$shop->website],
                ],
            ]);
        }
    }

    public function getCommandSignature(): string
    {
        return 'master_product:create_missing_organisation_products {organisation} {--reference_shop=eu} {--apply} {--limit=}';
    }

    public function asCommand(Command $command): int
    {
        $organisation  = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $referenceShop = Shop::where('slug', $command->option('reference_shop'))
            ->where('organisation_id', $organisation->id)
            ->firstOrFail();

        if (!$referenceShop->master_shop_id) {
            $command->error('Reference shop '.$referenceShop->code.' follows no master shop.');

            return 1;
        }

        $dryRun = !$command->option('apply');
        $limit  = $command->option('limit') ? (int)$command->option('limit') : null;

        /*
         * Reported as it goes, not at the end: an interrupted sweep used to leave no
         * trace of what it had already done.
         */
        $result = $this->handle(
            $organisation,
            $referenceShop,
            $dryRun,
            $limit,
            $dryRun ? null : fn (string $line) => $command->line('  '.$line)
        );

        $command->info(($dryRun ? 'DRY RUN — ' : '')
            .$result['created'].' products '.($dryRun ? 'would be ' : '').'created across '
            .$result['masters'].' masters, from '.$referenceShop->code
            .' ('.MasterShop::find($referenceShop->master_shop_id)?->slug.')');

        if ($dryRun) {
            foreach (array_slice($result['changes'], 0, 40) as $change) {
                $command->line('  '.$change);
            }
            if (count($result['changes']) > 40) {
                $command->line('  … and '.(count($result['changes']) - 40).' more');
            }
        }

        if ($result['failures']) {
            $command->newLine();
            $command->warn(count($result['failures']).' could not be created:');
            foreach (array_slice($result['failures'], 0, 30) as $failure) {
                $command->line('  '.$failure);
            }
            if (count($result['failures']) > 30) {
                $command->line('  … and '.(count($result['failures']) - 30).' more');
            }
        }

        return 0;
    }
}
