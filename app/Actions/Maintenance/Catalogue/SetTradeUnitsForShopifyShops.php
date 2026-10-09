<?php

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateTradeUnitsForExternalProduct;
use App\Actions\Catalogue\Shop\External\Shopify\UpdateShopifyProductInventoryQuantity;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopEngineEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Nightwatch\Facades\Nightwatch;

class SetTradeUnitsForShopifyShops
{
    use WithActionUpdate;

    private const string EXCLUDED_TRADE_UNIT_SLUG = 'ial01';

    /**
     * @return array{status: string, code: string, seeder: ?string, trade_units: array<int, array{id: int, quantity: float}>, units: ?float, summary: string}
     */
    public function handle(Product $product, ?Command $command = null, bool $dryRun = false): array
    {
        $proposal = $this->getProposal($product);

        if ($dryRun || $proposal['status'] !== 'link') {
            return $proposal;
        }

        try {
            DB::transaction(function () use ($product, $proposal) {
                $product = UpdateProduct::make()->action($product, ['units' => $proposal['units']], strict: false);

                UpdateTradeUnitsForExternalProduct::make()->action($product, [
                    'trade_units' => $proposal['trade_units']
                ]);
            });
        } catch (ValidationException $e) {
            return [...$proposal, 'status' => 'skip', 'summary' => collect($e->errors())->flatten()->join(' ')];
        }

        UpdateShopifyProductInventoryQuantity::dispatch($product->refresh())->delay(30);

        $command?->info('Linked '.$product->code.' => '.$proposal['summary']);

        return $proposal;
    }

    /**
     * @return array{status: string, code: string, seeder: ?string, trade_units: array<int, array{id: int, quantity: float}>, units: ?float, summary: string}
     */
    public function getProposal(Product $product): array
    {
        $proposal = [
            'status'      => 'skip',
            'code'        => $product->code,
            'seeder'      => null,
            'trade_units' => [],
            'units'       => null,
            'summary'     => '',
        ];

        $seederShop = $product->shop->seederShop;

        if (!$seederShop) {
            return [...$proposal, 'summary' => 'shop has no seeder shop'];
        }

        if ($product->state !== ProductStateEnum::IN_PROCESS) {
            return [...$proposal, 'summary' => 'not in process ('.$product->state->value.')'];
        }

        if ($product->tradeUnits()->exists()) {
            return [...$proposal, 'summary' => 'already has trade units'];
        }

        $seederProducts = Product::where('shop_id', $seederShop->id)
            ->whereRaw('lower(code) = lower(?)', [$product->code])
            ->get();

        if ($seederProducts->count() > 1) {
            return [...$proposal, 'summary' => 'more than one product with this code in seeder'];
        }

        /** @var Product|null $seederProduct */
        $seederProduct = $seederProducts->first();

        if (!$seederProduct) {
            return [...$proposal, 'summary' => 'no product with this code in seeder'];
        }

        $tradeUnits = $seederProduct->tradeUnits
            ->filter(fn (TradeUnit $tradeUnit) => $tradeUnit->slug != self::EXCLUDED_TRADE_UNIT_SLUG)
            ->map(fn (TradeUnit $tradeUnit) => [
                'id'       => $tradeUnit->id,
                'quantity' => (float) $tradeUnit->pivot->quantity,
            ])
            ->filter(fn (array $tradeUnit) => $tradeUnit['quantity'] > 0)
            ->values()
            ->all();

        if (empty($tradeUnits)) {
            return [...$proposal, 'seeder' => $seederProduct->code, 'summary' => 'seeder product has no trade units'];
        }

        $tradeUnitCodes = TradeUnit::whereIn('id', array_column($tradeUnits, 'id'))->pluck('code', 'id');

        return [
            ...$proposal,
            'status'      => 'link',
            'seeder'      => $seederProduct->code,
            'trade_units' => $tradeUnits,
            'units'       => (float) $seederProduct->units,
            'summary'     => collect($tradeUnits)
                ->map(fn (array $tradeUnit) => $tradeUnitCodes[$tradeUnit['id']].' x '.$tradeUnit['quantity'])
                ->join(', '),
        ];
    }

    public string $commandSignature = 'repair:set_trade_units_for_shopify_shops {shopify_shop} {--product=} {--dry-run}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $dryRun = (bool) $command->option('dry-run');

        if ($command->option('product')) {
            $product  = Product::where('slug', $command->option('product'))->firstOrFail();
            $proposal = $this->handle($product, $command, $dryRun);
            $command->line($proposal['status'].': '.$proposal['code'].' => '.($proposal['seeder'] ?? '-').' | '.$proposal['summary']);

            return 0;
        }

        $shopifyShop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::SHOPIFY)
            ->where('slug', $command->argument('shopify_shop'))
            ->firstOrFail();

        if (!$shopifyShop->seederShop) {
            $command->error('Seeder shop not found for '.$shopifyShop->name);

            return 1;
        }

        $query = Product::where('shop_id', $shopifyShop->id)
            ->where('state', ProductStateEnum::IN_PROCESS)
            ->whereDoesntHave('tradeUnits');

        $bar = $command->getOutput()->createProgressBar((clone $query)->count());
        $bar->start();

        $proposals = [];

        $query->orderBy('id')
            ->chunkById(100, function (Collection $products) use ($bar, $dryRun, &$proposals) {
                foreach ($products as $product) {
                    $proposals[] = $this->handle($product, null, $dryRun);
                    $bar->advance();
                }
            });

        $bar->finish();
        $command->newLine(2);

        $linked  = collect($proposals)->where('status', 'link');
        $skipped = collect($proposals)->where('status', 'skip');

        $command->table(
            ['Code', 'Seeder product', 'Trade units', 'Units'],
            $linked->map(fn (array $proposal) => [$proposal['code'], $proposal['seeder'], $proposal['summary'], $proposal['units']])->all()
        );

        $command->table(
            ['Skipped code', 'Seeder product', 'Reason'],
            $skipped->map(fn (array $proposal) => [$proposal['code'], $proposal['seeder'] ?? '-', $proposal['summary']])->all()
        );

        $command->info(($dryRun ? 'Would link ' : 'Linked ').$linked->count().' products, skipped '.$skipped->count());

        return 0;
    }
}
