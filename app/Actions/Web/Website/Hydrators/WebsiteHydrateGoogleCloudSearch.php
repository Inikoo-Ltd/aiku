<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 11-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Web\Website;
use Exception;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Google\Client;
use Google\Service\Webmasters;
use GuzzleHttp\Exception\ConnectException;

class WebsiteHydrateGoogleCloudSearch implements ShouldBeUnique
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:website_data_google_cloud {organisations?*} {--s|slugs=}';

    public function getJobUniqueId(Website $website): string
    {
        return $website->id;
    }

    /**
     * @throws \Google\Exception
     */
    public function handle(Website $website): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $groupSettings = $website->group->settings;
        $apiToken      = Arr::get($groupSettings, 'gcp.oauthClientSecret');
        if (!$apiToken) {
            $apiToken = config('app.analytics.google.client_oauth_secret');
            if (!$apiToken) {
                return;
            }
            data_set($groupSettings, 'gcp.oauthClientSecret', $apiToken);
            $website->group->update(['settings' => $groupSettings]);
        }

        $client                      = new Client();
        $gcpOauthClientSecretDecoded = base64_decode($apiToken);
        $client->setAuthConfig(json_decode($gcpOauthClientSecretDecoded, true));
        $client->addScope(Webmasters::WEBMASTERS_READONLY);
        $service = new Webmasters($client);

        $this->saveSiteUrl($website, $service);
    }

    private function saveSiteUrl(Website $website, $service, $retry = 3): ?string
    {
        if ($retry == 0) {
            return '';
        }
        $websiteData = $website->data;
        $siteUrl     = Arr::get($websiteData, 'gcp.siteUrl');
        if (!$siteUrl) {
            try {
                $siteEntry = $service->sites->listSites()->getSiteEntry();
                $listSite  = Arr::pluck($siteEntry, "siteUrl");
                $siteUrl   = Arr::where($listSite, function (string $value) use ($website) {
                    return str_contains($value, $website->domain);
                });
                if (empty($siteUrl)) {
                    return null;
                }
                $siteUrl = Arr::first($siteUrl);
                data_set($websiteData, 'gcp.siteUrl', $siteUrl);
                $website->update(['data' => $websiteData]);
            } catch (ConnectException) {
                return $this->saveSiteUrl($website, $service, $retry - 1);
            } catch (Exception $e) {
                debug($e);
            }
        }

        return null;
    }


}
