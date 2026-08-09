<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Discounts\Offer\OfferTypeEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Actions\Discounts\Offer\StoreProductStepDiscount;
use App\Actions\Web\Webpage\CloseWebpage;
use App\Actions\Web\Webpage\ReopenWebpage;
use App\Models\Web\Redirect;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AromaRetireQuantityVariants
{
    use AsAction;

    public string $commandSignature = 'aroma:retire_quantity_variants {--live : Actually write, otherwise dry run}';

    public function asCommand(Command $command): int
    {
        ini_set('memory_limit', '4G');
        DB::disableQueryLog();

        $live = (bool)$command->option('live');
        $shop = Shop::where('code', 'AROMA')->firstOrFail();

        $rows = DB::connection('aurora_4')->select(
            'select `Product ID` id, `Product Code` code, variant_parent_id pid,
                    `Product Units Per Case`+0 upc, `Product Price`+0 price,
                    `Product Show Variant` = "Yes" shown
             from `Product Dimension`
             where `Product Store Key` = 1
               and variant_parent_id is not null
               and (is_variant = "Yes" or has_variants = "Yes")
               and `Product Status` in ("Active", "Discontinuing")'
        );

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row->pid][] = $row;
        }

        $recentOrders = [];
        foreach (array_chunk(array_column($rows, 'id'), 800) as $chunk) {
            $sales = DB::connection('aurora_4')->select(
                'select `Product ID` id, count(distinct `Order Key`) orders
                 from `Order Transaction Fact`
                 where `Product ID` in ('.implode(',', $chunk).')
                   and `Order Date` >= date_sub(now(), interval 12 month)
                 group by 1'
            );
            foreach ($sales as $sale) {
                $recentOrders[$sale->id] = (int)$sale->orders;
            }
        }

        $stats = [
            'groups'            => 0,
            'kept'              => 0,
            'smaller_sizes_dropped' => 0,
            'flag_fixes'        => 0,
            'hidden'            => 0,
            'discontinued_not_shown' => 0,
            'flagged_for_cutover' => 0,
            'webpages_closed'   => 0,
            'webpages_adopted'  => 0,
            'offers_created'    => 0,
            'offers_existing'   => 0,
            'offers_skipped_illogical_pricing' => 0,
            'skipped_steps'     => 0,
            'missing_in_aiku'   => 0,
            'trade_unit_mismatch_groups' => 0,
        ];

        $tradeUnits = [];
        foreach (array_chunk(array_map(fn ($r) => '4:'.$r->id, $rows), 1000) as $chunk) {
            $pivot = DB::table('products as p')
                ->join('model_has_trade_units as m', fn ($join) => $join->on('m.model_id', 'p.id')->where('m.model_type', 'Product'))
                ->where('p.shop_id', $shop->id)->whereIn('p.source_id', $chunk)
                ->select('p.source_id', 'm.trade_unit_id')->get();
            foreach ($pivot as $row) {
                $tradeUnits[$row->source_id][] = $row->trade_unit_id;
            }
        }

        $bar = $command->getOutput()->createProgressBar(count($groups));
        $bar->setFormat(" %current%/%max% groups [%bar%] %percent:3s%%  elapsed %elapsed:6s%  left %estimated:-6s%\n %message%");
        $bar->setMessage('starting');
        $bar->start();

        foreach ($groups as $pid => $group) {
            $bar->advance();
            if (count($group) < 2) {
                continue;
            }
            $bar->setMessage($group[0]->code);

            $tradeUnitSets = [];
            foreach ($group as $member) {
                if (!$member->shown && $member->id != $pid) {
                    continue;
                }
                $set = $tradeUnits['4:'.$member->id] ?? [];
                sort($set);
                if ($set) {
                    $tradeUnitSets[] = implode(',', $set);
                }
            }
            if (count(array_unique($tradeUnitSets)) > 1) {
                $stats['trade_unit_mismatch_groups']++;
                $this->detail($command, 'skip group, trade units differ (maybe real variants), review manually: '.$group[0]->code, 'warn');
                continue;
            }

            $stats['groups']++;
            usort($group, fn ($a, $b) => $a->upc <=> $b->upc);

            $keep = null;
            foreach ($group as $candidate) {
                if ($candidate->shown && (($recentOrders[$candidate->id] ?? 0) > 0 || $candidate->id == $pid)) {
                    $keep = $candidate;
                    break;
                }
            }
            $keep ??= $group[0];
            if ($keep->id != $group[0]->id) {
                $stats['smaller_sizes_dropped']++;
            }
            $group = array_values(array_filter($group, fn ($r) => $r->id != $keep->id));

            $keepProduct = Product::where('shop_id', $shop->id)->where('source_id', '4:'.$keep->id)->first();
            if (!$keepProduct) {
                $command->error("keep missing in aiku: $keep->code");
                $stats['missing_in_aiku']++;
                continue;
            }
            $stats['kept']++;

            if (!$keepProduct->is_main || !$keepProduct->is_for_sale) {
                $stats['flag_fixes']++;
                $this->detail($command, "flag fix: $keep->code (is_main={$keepProduct->is_main} is_for_sale={$keepProduct->is_for_sale})");
                if ($live) {
                    UpdateProduct::make()->action($keepProduct, [
                        'is_main'     => true,
                        'is_for_sale' => true,
                    ]);
                }
            }

            $this->ensureKeepHasContentWebpage($keepProduct, $group, $shop, $command, $stats, $live);
            $keepProduct->refresh();

            $steps         = [];
            $keepUnitPrice = $keep->upc > 0 ? $keep->price / $keep->upc : 0;
            $seenQty       = [];
            $illogicalPricing = false;
            foreach ($group as $variant) {
                $variantProduct = Product::where('shop_id', $shop->id)->where('source_id', '4:'.$variant->id)->first();
                if (!$variantProduct) {
                    $stats['missing_in_aiku']++;
                } else {
                    $this->flagForCutover($variantProduct, $keepProduct, $stats, $live);

                    if ($variantProduct->webpage?->state == WebpageStateEnum::LIVE) {
                        $stats['webpages_closed']++;
                        $this->detail($command, "close webpage: $variant->code");
                        if ($live) {
                            $this->closeWebpage($variantProduct->webpage, $keepProduct->webpage);
                        }
                    }
                    if (!$variant->shown && $variantProduct->state != ProductStateEnum::DISCONTINUED) {
                        $stats['discontinued_not_shown']++;
                        $this->detail($command, "discontinue (not shown in aurora): $variant->code");
                        if ($live) {
                            UpdateProduct::make()->action($variantProduct, [
                                'is_main'     => false,
                                'is_for_sale' => false,
                                'status'      => ProductStatusEnum::DISCONTINUED,
                                'state'       => ProductStateEnum::DISCONTINUED,
                            ]);
                        }
                    } elseif ($variant->shown && ($variantProduct->is_main || $variantProduct->is_for_sale)) {
                        $stats['hidden']++;
                        $this->detail($command, "hide: $variant->code");
                        if ($live) {
                            UpdateProduct::make()->action($variantProduct, [
                                'is_main'     => false,
                                'is_for_sale' => false,
                            ]);
                        }
                    }
                }

                if (!$variant->shown) {
                    continue;
                }
                $ratio = $keep->upc > 0 ? $variant->upc / $keep->upc : 0;
                $minQuantity = (int)round($ratio);
                $percentageOff = $keepUnitPrice > 0 && $variant->upc > 0
                    ? round(1 - ($variant->price / $variant->upc) / $keepUnitPrice, 4)
                    : 0;
                if ($ratio > 1 && $percentageOff <= 0) {
                    $illogicalPricing = true;
                }
                if ($minQuantity < 2 || abs($ratio - $minQuantity) > 1e-6 || $percentageOff <= 0 || $percentageOff > 1 || isset($seenQty[$minQuantity])) {
                    $stats['skipped_steps']++;
                    $this->detail($command, sprintf('skip step: %s qty=%.2f off=%.4f', $variant->code, $ratio, $percentageOff), 'warn');
                    continue;
                }
                $seenQty[$minQuantity]     = true;
                $steps[] = ['min_quantity' => $minQuantity, 'percentage_off' => $percentageOff];
            }

            if ($illogicalPricing) {
                $stats['offers_skipped_illogical_pricing']++;
                $this->detail($command, "no offer, illogical pricing, review manually: $keep->code", 'warn');
                continue;
            }
            if (!$steps) {
                continue;
            }

            // Keyed on the product, not the offer code, so it survives the campaign and code
            // naming changing under us
            $hasOffer = Offer::where('shop_id', $shop->id)
                ->where('type', OfferTypeEnum::PRODUCT_QUANTITY_ORDERED)
                ->where('trigger_type', 'Product')
                ->where('trigger_id', $keepProduct->id)
                ->exists();
            if ($hasOffer) {
                $stats['offers_existing']++;
                continue;
            }
            $stats['offers_created']++;
            $this->detail($command, "offer: $keepProduct->code ".collect($steps)->map(fn ($s) => $s['min_quantity'].'→'.($s['percentage_off'] * 100).'%')->implode(' '));
            if ($live) {
                StoreProductStepDiscount::make()->action($keepProduct, [
                    'name'     => 'Step Discount '.$keepProduct->code,
                    'duration' => 'permanent',
                    'start_at' => now()->toDateString(),
                    'steps'    => $steps,
                ]);
            }
        }

        $bar->finish();
        $command->newLine(2);

        $command->table(array_keys($stats), [array_values($stats)]);
        $command->info($live ? 'LIVE run done' : 'Dry run, nothing written. Use --live to apply.');

        return 0;
    }

    /**
     * Aurora may still hold these in open orders, so they cannot be discontinued yet. The flag is
     * the durable record of the decision, aurora is gone by the time the cutover reads it.
     */
    private function flagForCutover(Product $variantProduct, Product $keepProduct, array &$stats, bool $live): void
    {
        $data = $variantProduct->data ?? [];
        if (Arr::get($data, 'retire_at_cutover') === true
            && Arr::get($data, 'replaced_by_product_id') === $keepProduct->id) {
            return;
        }

        $stats['flagged_for_cutover']++;
        if (!$live) {
            return;
        }

        DB::table('products')->where('id', $variantProduct->id)->update([
            'data' => json_encode(array_merge($data, [
                'retire_at_cutover'      => true,
                'replaced_by_product_id' => $keepProduct->id,
            ]))
        ]);
    }

    private function detail(Command $command, string $message, ?string $style = null): void
    {
        if (!$command->getOutput()->isVerbose()) {
            return;
        }

        if ($style === 'warn') {
            $command->warn($message);

            return;
        }

        $command->line($message);
    }

    private function closeWebpage(Webpage $webpage, ?Webpage $target): void
    {
        // CloseWebpage only accepts a live redirect target, the storefront is the safe fallback
        $toWebpageId = null;
        foreach ([$target, Webpage::find($webpage->website->storefront_id)] as $candidate) {
            if ($candidate && $candidate->id != $webpage->id && $candidate->state == WebpageStateEnum::LIVE) {
                $toWebpageId = $candidate->id;
                break;
            }
        }
        if (!$toWebpageId) {
            return;
        }

        // A webpage whose canonical url is already redirected elsewhere cannot get a second
        // redirect row, so close it without one
        if (Redirect::where('website_id', $webpage->website_id)->where('from_url', $webpage->canonical_url)->exists()) {
            $webpage->update(['state' => WebpageStateEnum::CLOSED]);

            return;
        }

        CloseWebpage::make()->action($webpage, [
            'redirect_type' => RedirectTypeEnum::PERMANENT,
            'to_webpage_id' => $toWebpageId,
        ]);
    }

    private function publishedBlocksCount(?Webpage $webpage): int
    {
        if (!$webpage) {
            return 0;
        }

        return count(Arr::get($webpage->published_layout ?? [], 'web_blocks', []));
    }

    private function ensureKeepHasContentWebpage(Product $keepProduct, array $group, Shop $shop, Command $command, array &$stats, bool $live): void
    {
        $keepWebpage = $keepProduct->webpage;
        if ($this->publishedBlocksCount($keepWebpage) > 0) {
            return;
        }

        $donorProduct = null;
        $donorWebpage = null;
        foreach ($group as $member) {
            $memberProduct = Product::where('shop_id', $shop->id)->where('source_id', '4:'.$member->id)->first();
            $memberWebpage = $memberProduct?->webpage;
            if ($this->publishedBlocksCount($memberWebpage) > 0) {
                $donorProduct = $memberProduct;
                $donorWebpage = $memberWebpage;
                break;
            }
        }
        if (!$donorWebpage) {
            return;
        }

        $stats['webpages_adopted']++;
        $this->detail($command, "adopt webpage: $keepProduct->code takes $donorWebpage->url from $donorProduct->code");
        if (!$live) {
            return;
        }

        DB::table('webpages')->where('id', $donorWebpage->id)->update(['model_id' => $keepProduct->id]);
        DB::table('products')->where('id', $keepProduct->id)->update(['webpage_id' => $donorWebpage->id]);
        DB::table('products')->where('id', $donorProduct->id)->update(['webpage_id' => null]);

        $donorWebpage->refresh();
        if ($donorWebpage->state == WebpageStateEnum::CLOSED) {
            ReopenWebpage::run($donorWebpage);
        }
        if ($keepWebpage && $keepWebpage->refresh()->state == WebpageStateEnum::LIVE) {
            $this->closeWebpage($keepWebpage, $donorWebpage);
        }
    }
}
