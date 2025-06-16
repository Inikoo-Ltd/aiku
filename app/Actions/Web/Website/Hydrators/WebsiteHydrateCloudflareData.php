<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Website\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Web\Website;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class WebsiteHydrateCloudflareData implements ShouldBeUnique
{
    use WithHydrateCommand;

    private Website $website;
    private Collection $siteList;
    private ?string $apiToken;
    private Collection $zoneAccountTag;

    public string $commandSignature = 'hydrate:website_data_cloudflare {organisations?*} {--s|slugs=}';


    public function __construct(Website $website)
    {
        $this->website        = $website;
        $this->siteList       = collect();
        $this->zoneAccountTag = collect();
        $this->model          = Website::class;
    }

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Website $website): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $groupSettings  = $website->group->settings;
        $this->apiToken = Arr::get($groupSettings, 'cloudflare.apiToken');
        if (!$this->apiToken) {
            $this->apiToken = env('CLOUDFLARE_ANALYTICS_API_TOKEN'); // from env cause group not stored api token yet
            if (!$this->apiToken) {
                return;
            }
            data_set($groupSettings, 'cloudflare.apiToken', $this->apiToken);
            $website->group->update(['settings' => $groupSettings]);
        }
        $newWebsiteData = $website->data;

        $this->zoneAccountTag = $this->getZoneAccountTag($website);
        if (!$this->zoneAccountTag->isEmpty()) {
            data_set($newWebsiteData, 'cloudflare.zoneTag', $this->zoneAccountTag->get('id'));
            data_set($newWebsiteData, 'cloudflare.accountTag', $this->zoneAccountTag->get('account')['id']);
        } else {
            return;
        }

        if ($this->siteList->isEmpty()) {
            $this->siteList = $this->getAnalyticsList($this->zoneAccountTag->get('account')['id']);
            if ($this->siteList->isEmpty()) {
                return;
            }
        }

        $this->siteList->each(function ($site) use ($website, &$newWebsiteData) {
            if (isset($site['ruleset']) && $site['ruleset']['zone_name'] == $website->domain) {
                data_set($newWebsiteData, 'cloudflare.siteTag', $site['site_tag']);
            }
        });

        $website->update(['data' => $newWebsiteData]);
    }


    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    private function getZoneAccountTag(Website $website, $try = 3): Collection
    {
        $urlCloudflareRest = "https://api.cloudflare.com/client/v4";
        if ($try == 0) {
            return collect();
        }
        try {
            $resultZone = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer $this->apiToken",
                'Content-Type'  => 'application/json',
            ])->get($urlCloudflareRest."/zones", [
                'name' => $website->domain,
            ])->json();
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Resolving timed out')) {
                return $this->getZoneAccountTag($website, --$try);
            }
            throw $e;
        }

        if (!empty($resultZone['errors'])) {
            return collect();
        }
        if (!Arr::get($resultZone, 'result')) {
            return collect();
        }
        if (empty($resultZone['result'])) {
            return collect();
        }

        return collect($resultZone['result'][0]);
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    private function getAnalyticsList(string $accountId, $try = 3): Collection
    {
        try {
            $urlCloudflareResAnalytic = "https://api.cloudflare.com/client/v4/accounts/$accountId/rum/site_info/list";
            $resultAnalytic           = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer $this->apiToken",
                'Content-Type'  => 'application/json',
            ])->get($urlCloudflareResAnalytic, [
                'per_page' => Website::count(),
            ])->json();
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Resolving timed out')) {
                return $this->getAnalyticsList($accountId, --$try);
            }
            throw $e;
        }
        if (!empty($resultAnalytic['errors'])) {
            return collect();
        }
        if (!Arr::get($resultAnalytic, 'result')) {
            return collect();
        }

        return collect($resultAnalytic['result']);
    }
}
