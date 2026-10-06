<?php

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateTradeUnitsForExternalProduct;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Symfony\Component\Console\Helper\ProgressBar;

class SetTradeUnitsForWixShops
{
    use WithActionUpdate;

    public function handle(Product $product, ?Command $command = null): void
    {
        $seederShop = $product->shop->seederShop;

        if (!$seederShop) {
            return;
        }

        $seederProduct = Product::whereRaw('lower(code) = lower(?)', [$product->code])
            ->where('shop_id', $seederShop->id)
            ->first();

        if (!$seederProduct) {
            return;
        }

        $productTradeUnits     = $product->tradeUnits->pluck('pivot.quantity', 'id');
        $masterAssetTradeUnits = $seederProduct->tradeUnits->pluck('pivot.quantity', 'id');

        $diffFromMaster  = $masterAssetTradeUnits->diffAssoc($productTradeUnits);
        $diffFromProduct = $productTradeUnits->diffAssoc($masterAssetTradeUnits);

        if (($diffFromMaster->isEmpty() && $diffFromProduct->isEmpty() && $product->units == $seederProduct->units) || $masterAssetTradeUnits->isEmpty()) {
            return;
        }

        if ($product->units != $seederProduct->units) {
            $product = UpdateProduct::make()->action($product, ['units' => $seederProduct->units], strict: false);
        }

        $command?->info('Product '.$product->slug.' units '.$product->units.' from seeder '.$seederProduct->slug);

        $tradeUnitsData = [];
        foreach ($seederProduct->tradeUnits as $tradeUnit) {
            if ($tradeUnit->slug != 'ial01') {
                $tradeUnitsData[] = [
                    'id'       => $tradeUnit->id,
                    'quantity' => $tradeUnit->pivot->quantity
                ];
            }
        }

        if (!empty($tradeUnitsData)) {
            UpdateTradeUnitsForExternalProduct::make()->action($product, [
                'trade_units' => $tradeUnitsData
            ]);
        }
    }

    public string $commandSignature = 'repair:set_trade_units_for_wix_shops {wix_shop?} {--product=}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        if ($command->option('product')) {
            $product = Product::where('slug', $command->option('product'))->firstOrFail();
            $this->handle($product, $command);

            return 0;
        }

        $wixShop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('slug', $command->argument('wix_shop'))
            ->firstOrFail();

        if (!$wixShop->seederShop) {
            $command->error('Seeder shop not found for '.$wixShop->name);

            return 1;
        }

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar(Product::where('shop_id', $wixShop->id)->count());
        $bar->setFormat('aiku_eta');
        $bar->start();

        Product::where('shop_id', $wixShop->id)
            ->orderBy('id')
            ->chunk(100, function (Collection $products) use ($bar, $command) {
                foreach ($products as $product) {
                    $this->handle($product, $command);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine();

        return 0;
    }
}
