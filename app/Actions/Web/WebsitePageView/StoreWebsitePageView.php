<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebsitePageView;

use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Models\Web\WebsitePageView;
use App\Models\Web\WebsiteVisitor;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreWebsitePageView
{
    use AsAction;

    public function handle(
        WebsiteVisitor $visitor,
        Website $website,
        string $url
    ): WebsitePageView {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';

        $lastPageView = WebsitePageView::where('website_visitor_id', $visitor->id)
            ->latest('id')
            ->first();

        if ($lastPageView) {
            $duration = min((int) abs(now()->diffInSeconds($lastPageView->created_at)), 1800);
            $lastPageView->update(['duration_seconds' => $duration]);
        }

        $webpage = $this->resolveWebpage($website, $path);

        return WebsitePageView::create([
            'group_id'           => $visitor->group_id,
            'organisation_id'    => $visitor->organisation_id,
            'website_visitor_id' => $visitor->id,
            'webpage_id'         => $webpage?->id,
            'website_id'         => $website->id,
            'shop_id'            => $website->shop_id,
            'page_url'           => $url,
            'page_path'          => $path,
            'page_type'          => $webpage?->type,
            'page_sub_type'      => $webpage?->sub_type,
            'view_date'          => now()->toDateString(),
        ]);
    }

    protected function resolveWebpage(Website $website, string $path): ?Webpage
    {
        $cacheKey = "webpage_resolve:{$website->id}:" . md5($path);

        return Cache::remember($cacheKey, 86400, function () use ($website, $path) {
            return Webpage::query()
                ->where('website_id', $website->id)
                ->where('state', WebpageStateEnum::LIVE)
                ->where(function ($query) use ($path) {
                    $query->whereRaw("substring(canonical_url from 'https?://[^/]+(/.*)$') = ?", [$path])
                        ->orWhere('url', ltrim($path, '/'));
                })
                ->first();
        });
    }
}
