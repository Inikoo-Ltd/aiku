<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 09 Nov 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Enums\Catalogue\Product\ProductBadgeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakNewInPagesCache
{
    use AsAction;

    public string $commandSignature = 'webpages:break_new_in_pages_cache';

    /**
     * Arrivals reach the NEW IN page when they are stamped, and leave it when their period runs out,
     * which nothing else notices. Run once a day: a page is broken only when one of its products left
     * in the last day, because every break purges the whole website at the Cloudflare edge.
     *
     * @return int the NEW IN pages broken
     */
    public function handle(): int
    {
        $leftSince = ProductBadgeEnum::newInSince()->subDay();
        $broken    = 0;

        Website::whereNotNull('new_in_page_id')->with('newInPage')->get()
            ->each(function (Website $website) use ($leftSince, &$broken) {
                if ($website->newInPage?->state != WebpageStateEnum::LIVE) {
                    return;
                }

                $hasProductsThatLeft = Product::where('shop_id', $website->shop_id)
                    ->whereBetween('first_available_at', [$leftSince, ProductBadgeEnum::newInSince()])
                    ->exists();

                if ($hasProductsThatLeft) {
                    BreakWebpageCache::run($website->newInPage);
                    $broken++;
                }
            });

        return $broken;
    }

    public function asCommand(Command $command): int
    {
        $command->info('NEW IN pages broken: '.$this->handle());

        return 0;
    }
}
