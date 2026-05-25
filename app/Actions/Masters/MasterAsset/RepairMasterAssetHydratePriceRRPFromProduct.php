<?php

/*
 * author Louis Perez
 * created on 21-05-2026-14h-57m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairMasterAssetHydratePriceRRPFromProduct
{
    use AsAction;

    protected Shop $shop;
    protected bool $useFallback;

    public function handle(MasterAsset $masterAsset, Command $command): void
    {
        // TODO MasterLevel Price RRP (Raul)
        // TODO CHECK Hydrate Up From Shop Logic

        $childBase = $masterAsset->products()->where('shop_id', $this->shop->id)->first();
        $isFallback = false;

        if (!$childBase && $this->useFallback) {
            $childBase = $masterAsset->products()->first();
            $isFallback = true;
        }

        if (!$childBase) {
            $command->error("X [{$masterAsset->code}]: No children found in ANY shop. Cannot repair.");
            return;
        }

        $sourceShop = $childBase->shop;

        $rate = GetCurrencyExchange::run($sourceShop->currency, $masterAsset->group->currency);

        $newPrice = round($childBase->price * $rate, 2);

        $sourceRrp = $childBase->rrp ?? ($childBase->price * 2.4);
        $newRrp = round($sourceRrp * $rate, 2);

        if ($newPrice == $masterAsset->price && $newRrp == $masterAsset->rrp) {
            $command->info("Skipping: [{$masterAsset->code}] (Prices match)");
            return;
        }

        $masterData = $masterAsset->data ?? [];
        data_set($masterData, 'repair_pricing_meta', [
            'source_shop_id'   => $sourceShop->id,
            'source_shop_slug' => $sourceShop->slug,
            'source_currency'  => $sourceShop->currency->code,
            'is_fallback'      => $isFallback,
            'original_price'   => $childBase->price,
            'conversion_rate'  => $rate,
            'converted_at'     => now()->toDateTimeString(),
        ]);

        UpdateMasterAsset::make()->action($masterAsset, [
            'price' => $newPrice,
            'rrp'   => $newRrp,
            'data'  => $masterData,
        ]);

        $status = $isFallback ? "Fallback ({$sourceShop->slug})" : "Primary";
        $command->info("[{$masterAsset->code}]: Source: {$status} | Price: {$masterAsset->price} -> {$newPrice}");
    }

    public string $commandSignature = 'repair:master_asset_hydrate_price_rrp_from_product {master_shop} {shop_slug} {--use_fallback}';

    public function asCommand(Command $command): void
    {
        $this->useFallback = $command->option('use_fallback');
        $masterShop = MasterShop::where('slug', $command->argument('master_shop') ?? '')->first();

        if (!$masterShop) {
            $command->error('No valid master shop is chosen, process is stopped');
            return;
        }

        $defaultSourceShop = $command->argument('shop_slug');

        $this->shop = Shop::where('slug', $defaultSourceShop)->where('master_shop_id', $masterShop->id)->first();

        if (!$this->shop) {
            $command->error("Shop [{$defaultSourceShop}] not found.");
            return;
        }

        $confirm = $command->ask("Are you sure you want to move Price & RRP from [Shop: {$this->shop->slug}] to [Master Shop: {$masterShop->slug}]? (y/n)", 'n');

        if (strtoupper($confirm) === 'Y') {
            MasterAsset::where('master_shop_id', $masterShop->id)
                ->where('status', true)
                ->chunkById(500, function ($chunk) use (&$command) {
                    $chunk->each(function ($masterAsset) use (&$command) {
                        $this->handle($masterAsset, $command);
                    });
                });

            $command->info('Repair process completed.');
            return;
        }

        $command->info('Process cancelled by user.');
    }
}
