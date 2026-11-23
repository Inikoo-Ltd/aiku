<?php

/*
 * Author Louis Perez
 * Created on 23-11-2026-11h-44m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Models\Traits;

use App\Actions\Web\StaticWebsite\BreakStaticWebsiteCache;

/**
 * Varnish keeps an info website's pages for days and nothing else drops them, so every change to a page
 * or shared part bans the copies it makes stale, whoever made it. The ban waits for the transaction,
 * or a visitor arriving in between would put the old version back in the cache.
 */
trait BansStaticWebsitePagesFromVarnish
{
    public static function bootBansStaticWebsitePagesFromVarnish(): void
    {
        static::created(fn (self $model) => $model->banStalePagesOnceCommitted());

        static::updated(function (self $model) {
            if ($model->wasChanged(['html', 'path', 'code'])) {
                $model->banStalePagesOnceCommitted();
            }
        });

        static::deleted(fn (self $model) => $model->banStalePagesOnceCommitted());
    }

    /**
     * @return list<string>|null the paths whose cached copies this change makes stale, null for every page of the site
     */
    abstract public function stalePaths(): ?array;

    private function banStalePagesOnceCommitted(): void
    {
        $paths = $this->stalePaths();

        $this->getConnection()->afterCommit(fn () => BreakStaticWebsiteCache::run($this->staticWebsite, $paths));
    }
}
