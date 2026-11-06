<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Fri, 06 Nov 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Crawl\UI;

use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;

/**
 * Who sees the site crawler: everyone who sees the SEO of the whole group, and everyone who sees the
 * SEO of a shop, for that shop's website only, as the site audit of the shop allowed before.
 */
trait WithSiteCrawlerAuthorisation
{
    use WithShopPpcPermissions;

    /** @var array<int, array{ids: array<int, int>|null}> */
    private array $viewableWebsiteIdsByUser = [];

    /**
     * @return array<int, int>|null the websites the user may crawl or inspect, null for all of them
     */
    public function viewableWebsiteIds(User $user): ?array
    {
        if (isset($this->viewableWebsiteIdsByUser[$user->id])) {
            return $this->viewableWebsiteIdsByUser[$user->id]['ids'];
        }

        $ids = $this->resolveViewableWebsiteIds($user);

        $this->viewableWebsiteIdsByUser[$user->id] = ['ids' => $ids];

        return $ids;
    }

    /**
     * @return array<int, int>|null
     */
    private function resolveViewableWebsiteIds(User $user): ?array
    {
        if ($this->canViewGroupSeo($user)) {
            return null;
        }

        return Website::with('shop')
            ->get()
            ->filter(fn (Website $website) => $website->shop && $this->canViewSeo($user, $website->shop))
            ->pluck('id')
            ->all();
    }

    public function canViewSiteCrawler(User $user): bool
    {
        $websiteIds = $this->viewableWebsiteIds($user);

        return $websiteIds === null || $websiteIds !== [];
    }

    public function canViewCrawlsOf(User $user, Website $website): bool
    {
        return $this->canViewGroupSeo($user) || ($website->shop && $this->canViewSeo($user, $website->shop));
    }
}
