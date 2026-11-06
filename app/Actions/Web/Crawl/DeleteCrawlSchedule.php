<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Removes a website's own crawl schedule. The weekly audit of every website still runs.
 */
class DeleteCrawlSchedule extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(Website $website): void
    {
        $website->crawlSchedule()->delete();
    }

    public function asController(Website $website, ActionRequest $request): void
    {
        abort_unless($website->shop, 404);

        $this->initialisationFromShop($website->shop, $request);

        $this->handle($website);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
