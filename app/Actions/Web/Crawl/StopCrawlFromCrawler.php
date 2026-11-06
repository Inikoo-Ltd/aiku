<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Stops a running crawl from the site crawler. The pages fetched so far are kept and checked, as
 * when a crawl reaches its page limit.
 */
class StopCrawlFromCrawler extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(Crawl $crawl): Crawl
    {
        if ($crawl->state !== CrawlStateEnum::FINISH) {
            StopCrawl::run($crawl);
        }

        return $crawl;
    }

    public function asController(Crawl $crawl, ActionRequest $request): Crawl
    {
        abort_unless($crawl->type === CrawlTypeEnum::AUDIT, 404);

        $this->initialisationFromShop($crawl->website->shop, $request);

        return $this->handle($crawl);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
