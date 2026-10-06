<?php

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\Catalogue\Product\UpdateTradeUnitsForExternalProduct;
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
use Laravel\Nightwatch\Facades\Nightwatch;
use Symfony\Component\Console\Helper\ProgressBar;

class SetTradeUnitsForWixShops
{
    use WithActionUpdate;

    private const string SIZE_PATTERN = '/-\s*(\d+(?:\.\d+)?)\s*(ml|ltr|l|kg|g)$/i';

    private const float SIZE_TOLERANCE = 0.01;

    public function handle(Product $product, ?Command $command = null, bool $dryRun = false): array
    {
        $proposal = $this->getProposal($product);

        if ($dryRun || $proposal['status'] !== 'link') {
            return $proposal;
        }

        $product = UpdateProduct::make()->action($product, ['units' => $proposal['units']], strict: false);

        UpdateTradeUnitsForExternalProduct::make()->action($product, [
            'trade_units' => $proposal['trade_units']
        ]);

        $command?->info('Linked '.$product->code.' => '.$proposal['summary']);

        return $proposal;
    }

    /**
     * @return array{status: string, code: string, seeder: ?string, method: ?string, trade_units: array<int, array{id: int, quantity: float}>, units: ?float, summary: string}
     */
    public function getProposal(Product $product): array
    {
        $proposal = [
            'status'      => 'skip',
            'code'        => $product->code,
            'seeder'      => null,
            'method'      => null,
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

        $seederProduct = $this->findSeederProduct($seederShop, $product->code);

        if ($seederProduct) {
            $tradeUnits = $seederProduct->tradeUnits
                ->filter(fn (TradeUnit $tradeUnit) => $tradeUnit->slug != 'ial01')
                ->map(fn (TradeUnit $tradeUnit) => [
                    'id'       => $tradeUnit->id,
                    'quantity' => (float) $tradeUnit->pivot->quantity,
                ])
                ->values()
                ->all();

            if (empty($tradeUnits)) {
                return [...$proposal, 'seeder' => $seederProduct->code, 'summary' => 'seeder product has no trade units'];
            }

            return $this->getLinkProposal($proposal, $seederProduct, 'code', $tradeUnits);
        }

        $size = $this->parseSize($product->code);

        if (!$size) {
            return [...$proposal, 'summary' => 'no product with this code in seeder'];
        }

        $baseProduct = $this->findSeederProduct($seederShop, preg_replace(self::SIZE_PATTERN, '', $product->code));

        if (!$baseProduct) {
            return [...$proposal, 'summary' => 'no product with this code or its base code in seeder'];
        }

        $baseTradeUnits = $baseProduct->tradeUnits->filter(fn (TradeUnit $tradeUnit) => $tradeUnit->slug != 'ial01');

        if ($baseTradeUnits->count() != 1) {
            return [...$proposal, 'seeder' => $baseProduct->code, 'summary' => 'base product has '.$baseTradeUnits->count().' trade units'];
        }

        /** @var TradeUnit $tradeUnit */
        $tradeUnit     = $baseTradeUnits->first();
        $tradeUnitSize = $this->getTradeUnitSize($tradeUnit, $baseProduct, [$seederShop->id, $product->shop_id]);

        if (!$tradeUnitSize) {
            return [...$proposal, 'seeder' => $baseProduct->code, 'summary' => 'size of trade unit '.$tradeUnit->code.' unknown or inconsistent'];
        }

        $quantity = round($size / $tradeUnitSize, 6);

        if ($quantity <= 0) {
            return [...$proposal, 'seeder' => $baseProduct->code, 'summary' => 'quantity rounds to zero'];
        }

        return $this->getLinkProposal($proposal, $baseProduct, 'size', [
            [
                'id'       => $tradeUnit->id,
                'quantity' => $quantity,
            ]
        ]);
    }

    private function getLinkProposal(array $proposal, Product $seederProduct, string $method, array $tradeUnits): array
    {
        $tradeUnitCodes = TradeUnit::whereIn('id', array_column($tradeUnits, 'id'))->pluck('code', 'id');

        return [
            ...$proposal,
            'status'      => 'link',
            'seeder'      => $seederProduct->code,
            'method'      => $method,
            'trade_units' => $tradeUnits,
            'units'       => count($tradeUnits) == 1 ? $tradeUnits[0]['quantity'] : (float) $seederProduct->units,
            'summary'     => collect($tradeUnits)
                ->map(fn (array $tradeUnit) => $tradeUnitCodes[$tradeUnit['id']].' x '.$tradeUnit['quantity'])
                ->join(', '),
        ];
    }

    private function findSeederProduct(Shop $seederShop, string $code): ?Product
    {
        return Product::where('shop_id', $seederShop->id)
            ->whereRaw('lower(code) = lower(?)', [$code])
            ->first();
    }

    public function parseSize(?string $text): ?float
    {
        if (!$text || !preg_match(self::SIZE_PATTERN, trim($text), $matches)) {
            return null;
        }

        $amount = (float) $matches[1];

        return match (strtolower($matches[2])) {
            'l', 'ltr', 'kg' => $amount * 1000,
            default => $amount,
        };
    }

    /**
     * @param array<int, int> $shopIds
     */
    private function getTradeUnitSize(TradeUnit $tradeUnit, Product $baseProduct, array $shopIds): ?float
    {
        $sizes = DB::table('model_has_trade_units as link')
            ->join('products', 'products.id', '=', 'link.model_id')
            ->where('link.model_type', 'Product')
            ->where('link.trade_unit_id', $tradeUnit->id)
            ->where('link.quantity', '>', 0)
            ->whereIn('products.shop_id', $shopIds)
            ->whereNull('products.deleted_at')
            ->whereRaw("(select count(*) from model_has_trade_units other where other.model_type = 'Product' and other.model_id = products.id) = 1")
            ->select('products.code', 'link.quantity')
            ->get()
            ->map(fn ($row) => ($size = $this->parseSize($row->code)) ? $size / (float) $row->quantity : null)
            ->filter()
            ->values();

        if ($sizes->isEmpty()) {
            $baseSize     = $this->parseSize(preg_replace('/\s*(pot|bottle|jar|tub)$/i', '', (string) $baseProduct->name));
            $baseQuantity = (float) $tradeUnit->pivot->quantity;

            return $baseSize && $baseQuantity > 0 ? $baseSize / $baseQuantity : null;
        }

        $reference = $sizes->first();

        if ($sizes->contains(fn (float $size) => abs($size - $reference) > $reference * self::SIZE_TOLERANCE)) {
            return null;
        }

        return $reference;
    }

    public string $commandSignature = 'repair:set_trade_units_for_wix_shops {wix_shop?} {--product=} {--dry-run}';

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

        $wixShop = Shop::where('type', ShopTypeEnum::EXTERNAL)
            ->where('engine', ShopEngineEnum::WIX)
            ->where('slug', $command->argument('wix_shop'))
            ->firstOrFail();

        if (!$wixShop->seederShop) {
            $command->error('Seeder shop not found for '.$wixShop->name);

            return 1;
        }

        $query = Product::where('shop_id', $wixShop->id)
            ->where('state', ProductStateEnum::IN_PROCESS)
            ->whereDoesntHave('tradeUnits');

        ProgressBar::setFormatDefinition(
            'aiku_eta',
            ' %current%/%max% [%bar%] %percent:3s%% | Elapsed: %elapsed:6s% | ETA: %remaining:6s%'
        );
        $bar = $command->getOutput()->createProgressBar((clone $query)->count());
        $bar->setFormat('aiku_eta');
        $bar->start();

        $proposals = [];

        $query->orderBy('id')
            ->chunk(100, function (Collection $products) use ($bar, $dryRun, &$proposals) {
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
            ['Code', 'Seeder product', 'Match', 'Trade units', 'Units'],
            $linked->map(fn (array $proposal) => [$proposal['code'], $proposal['seeder'], $proposal['method'], $proposal['summary'], $proposal['units']])->all()
        );

        $command->table(
            ['Skipped code', 'Seeder product', 'Reason'],
            $skipped->map(fn (array $proposal) => [$proposal['code'], $proposal['seeder'] ?? '-', $proposal['summary']])->all()
        );

        $command->info(($dryRun ? 'Would link ' : 'Linked ').$linked->count().' products ('.$linked->where('method', 'code')->count().' by code, '.$linked->where('method', 'size')->count().' by size), skipped '.$skipped->count());

        return 0;
    }
}
