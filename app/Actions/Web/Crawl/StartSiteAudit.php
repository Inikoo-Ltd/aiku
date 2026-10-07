<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl;

use App\Actions\OrgAction;
use App\Enums\Web\Crawl\CrawlStateEnum;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Enums\Web\Crawl\CrawlTypeEnum;
use App\Models\Web\Crawl;
use App\Models\Web\Website;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class StartSiteAudit extends OrgAction
{
    public function handle(Website $website): Crawl
    {
        $unfinishedAudit = Crawl::where('website_id', $website->id)
            ->where('type', CrawlTypeEnum::AUDIT)
            ->where('state', '!=', CrawlStateEnum::FINISH)
            ->latest('id')
            ->first();

        if ($unfinishedAudit) {
            return $unfinishedAudit;
        }

        $crawl = AuditWebsite::startFor($website, CrawlTriggerEnum::USER);

        AuditWebsite::dispatch($crawl);

        return $crawl;
    }

    public function asController(Website $website, ActionRequest $request): Crawl
    {
        $this->initialisationFromShop($website->shop, $request);

        abort_unless($request->user()->authTo([
            "websites-view.$website->organisation_id",
            "web.$website->shop_id.edit",
            "group-webmaster.edit",
        ]), 403);

        return $this->handle($website);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
