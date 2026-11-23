<?php

/*
 * Author Louis Perez
 * Created on 23-11-2026-11h-12m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\StaticWebsite;

use App\Actions\OrgAction;
use App\Actions\Traits\WithVarnishBan;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Drops every page of an info website from Varnish, so its domain serves what Aiku holds now. Info
 * pages carry no X-AIKU-WEBSITE header, so the website and all-websites bans never reach them: each
 * page is banned by its host and path instead, on every Varnish host. Cloudflare does not keep these
 * pages, so there is no edge to purge.
 */
class BreakStaticWebsiteCache extends OrgAction
{
    use WithVarnishBan;

    public const array SERVED_FILES = ['/robots.txt', '/sitemap.xml'];

    /**
     * @param list<string>|null $paths the pages to drop, with the sitemap whose dates they change; null drops every page
     */
    public function handle(StaticWebsite $staticWebsite, ?array $paths = null): StaticWebsite
    {
        if (!config('iris.cache.varnish')) {
            return $staticWebsite;
        }

        $host  = 'www.'.strtolower($staticWebsite->domain);
        $paths = $paths === null
            ? $staticWebsite->webpages()->pluck('path')->merge(self::SERVED_FILES)
            : collect($paths)->push('/sitemap.xml');

        $this->sendVarnishBansHttpPool(
            $paths->unique()
                ->map(fn (string $path) => [
                    'x-ban-host' => $host,
                    'x-ban-url'  => implode('/', array_map('rawurlencode', explode('/', $path))),
                ])
                ->values()
                ->all()
        );

        return $staticWebsite;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('group-webmaster.edit');
    }

    public function asController(StaticWebsite $staticWebsite, ActionRequest $request): StaticWebsite
    {
        $this->initialisationFromGroup(group(), $request);
        abort_unless($staticWebsite->group_id === $this->group->id, 404);

        return $this->handle($staticWebsite);
    }

    public function inWebpage(StaticWebpage $webpage, ActionRequest $request): StaticWebsite
    {
        $this->initialisationFromGroup(group(), $request);
        abort_unless($webpage->staticWebsite->group_id === $this->group->id, 404);

        return $this->handle($webpage->staticWebsite, [$webpage->path]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
