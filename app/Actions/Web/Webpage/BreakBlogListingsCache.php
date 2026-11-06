<?php

/*
 * Author: YudhistiraA
 * Created: Fri, 06 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Traits\WithVarnishBan;
use App\Actions\Web\Website\Cloudflare\PurgeCloudflareWebsitePages;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;

class BreakBlogListingsCache
{
    use AsAction;
    use WithVarnishBan;

    /**
     * The blog dashboard and the category dashboards list blogs while they render and are cached whole,
     * so a blog published, renamed or closed only shows up on them once their cache is dropped too.
     *
     * @return array<int, string> the listing paths banned
     */
    public function handle(Webpage $blog): array
    {
        if ($blog->type !== WebpageTypeEnum::BLOG) {
            return [];
        }

        $website = $blog->website;

        if ($website->blogDashboardPage && $website->blogDashboardPage->id !== $blog->id) {
            BreakWebpageCache::run($website->blogDashboardPage);
        }

        $paths = array_values(array_filter([
            '/blog',
            $blog->getBlogCategory()?->blogCategoryUrl(),
        ]));

        $host = strtolower('www.'.$website->domain);

        $this->sendVarnishBansHttpPool(
            array_map(fn (string $path) => [
                'x-ban-host' => $host,
                'x-ban-url'  => $path,
            ], $paths)
        );

        PurgeCloudflareWebsitePages::schedule($website);

        return $paths;
    }
}
