<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Sep 2026 12:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Gives back its own sku to a portfolio that carries the code of another product of the shop.
 *
 * Only our own row is written, never the store of the customer. A portfolio linked to a listing is
 * left alone: the listing carries the borrowed sku, the stock push finds its variant through it, and
 * only the customer can change the sku in their store.
 */
class RepairPortfoliosBorrowedSku
{
    use AsAction;

    public const string REPAIRED = 'repaired';
    public const string LINKED   = 'linked';
    public const string NO_SKU   = 'no_sku';

    public string $commandSignature = 'repair:portfolios_borrowed_sku {customerSalesChannel? : Slug of one channel, all channels when omitted} {--platform= : Only channels of this platform, for example shopify} {--dry-run : Report what would be repaired without writing}';

    public function handle(Portfolio $portfolio, bool $dryRun = false): string
    {
        if ($portfolio->platform_product_id || $portfolio->platform_product_variant_id) {
            return self::LINKED;
        }

        $sku = $portfolio->item ? StorePortfolio::make()->getSKU($portfolio->item) : null;

        if (!$sku || $this->isCodeOfAnotherProduct($portfolio, $sku)) {
            return self::NO_SKU;
        }

        if (!$dryRun) {
            $portfolio->update(['sku' => $sku]);
        }

        return self::REPAIRED;
    }

    public function borrowedSkuQuery(?CustomerSalesChannel $customerSalesChannel = null, ?PlatformTypeEnum $platformType = null): Builder
    {
        return Portfolio::where('portfolios.item_type', class_basename(Product::class))
            ->where('portfolios.status', true)
            ->whereNotNull('portfolios.sku')
            ->when($customerSalesChannel, fn (Builder $query) => $query->where('portfolios.customer_sales_channel_id', $customerSalesChannel->id))
            ->when($platformType, fn (Builder $query) => $query->whereHas('platform', fn (Builder $query) => $query->where('type', $platformType)))
            ->whereRaw('exists (select 1 from products owner where owner.shop_id = portfolios.shop_id and owner.deleted_at is null and owner.id <> portfolios.item_id and lower(owner.code collate "C") = lower(portfolios.sku collate "C"))')
            ->whereRaw('not exists (select 1 from products own where own.id = portfolios.item_id and lower(own.code collate "C") = lower(portfolios.sku collate "C"))');
    }

    private function isCodeOfAnotherProduct(Portfolio $portfolio, string $sku): bool
    {
        if (Str::lower($sku) === Str::lower((string) $portfolio->item->code)) {
            return false;
        }

        return Product::where('shop_id', $portfolio->shop_id)
            ->where('id', '!=', $portfolio->item_id)
            ->whereRaw('lower(code collate "C") = ?', [Str::lower($sku)])
            ->exists();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $customerSalesChannel = null;
        if ($command->argument('customerSalesChannel')) {
            $customerSalesChannel = CustomerSalesChannel::where('slug', $command->argument('customerSalesChannel'))->first();
            if (!$customerSalesChannel) {
                $command->error('Customer sales channel not found');

                return 1;
            }
        }

        $platformType = null;
        if ($command->option('platform')) {
            $platformType = PlatformTypeEnum::tryFrom($command->option('platform'));
            if (!$platformType) {
                $command->error('Unknown platform, use one of: '.implode(', ', array_column(PlatformTypeEnum::cases(), 'value')));

                return 1;
            }
        }

        $dryRun = (bool) $command->option('dry-run');
        $query  = $this->borrowedSkuQuery($customerSalesChannel, $platformType);
        $counts = [self::REPAIRED => 0, self::LINKED => 0, self::NO_SKU => 0];
        $linked = [];

        $progressBar = $command->getOutput()->createProgressBar($query->count());

        $query->with('item.orgStocks.stock')->chunkById(500, function ($portfolios) use (&$counts, &$linked, $dryRun, $customerSalesChannel, $progressBar) {
            foreach ($portfolios as $portfolio) {
                $outcome = $this->handle($portfolio, $dryRun);
                $counts[$outcome]++;

                if ($customerSalesChannel && $outcome === self::LINKED) {
                    $linked[] = [$portfolio->id, $portfolio->item_code, $portfolio->sku, $portfolio->platform_product_id];
                }

                $progressBar->advance();
            }
        }, 'portfolios.id', 'id');

        $progressBar->finish();
        $command->newLine(2);

        $command->table(
            [$dryRun ? 'Would repair' : 'Repaired', 'Left alone, linked to a listing', 'Left alone, no sku of its own'],
            [array_values($counts)]
        );

        if ($linked) {
            $command->table(['Portfolio', 'Product', 'Sku it carries', 'Listing'], $linked);
        }

        return 0;
    }
}
