<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Exception;
use Google\Client;
use Google\Service\Webmasters;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebpageGoogleCloud extends OrgAction
{
    use AsAction;
    use WithNoStrictRules;

    private Website $website;

    /**
     * @throws \Throwable
     */
    public function handle(Webpage $webpage, array $modelData): array
    {

        $user = auth()->user();

        $cacheKey = "ui_state-user:{$user->id};webpage:{$webpage->id};filter-webpage-analytics:".md5(json_encode($modelData));

        $cachedData = cache()->get($cacheKey);

        if ($cachedData) {
            return $cachedData;
        }

        $settings = $webpage->group->settings;
        $oauthClientSecret = Arr::get($settings, 'gcp.oauthClientSecret');
        if (! $oauthClientSecret) {
            $oauthClientSecret = env('GOOGLE_OAUTH_CLIENT_SECRET');
            if (! $oauthClientSecret) {
                debug("secret is empty \n");

                return [];
            }
            data_set($settings, 'gcp.oauthClientSecret', $oauthClientSecret);
            $webpage->group->update(['settings' => $settings]);
        }

        $client = new Client;
        $gcpOauthClientSecretDecoded = base64_decode($oauthClientSecret);
        $client->setAuthConfig(json_decode($gcpOauthClientSecretDecoded, true));
        $client->addScope(Webmasters::WEBMASTERS_READONLY);
        $service = new Webmasters($client);

        $siteUrl = $this->getSiteUrl($webpage, $service);

        if ($this->saveSecret || ! $siteUrl) {
            return [];
        }

        $data = $this->getSearchAnalytics($webpage, $service, $siteUrl, $modelData);

        cache()->put($cacheKey, $data, now()->addMinutes(5));

        return $data;
    }

    private function getSiteUrl(Webpage $webpage, $service, $retry = 3): string
    {
        if ($retry == 0) {
            return '';
        }
        $websiteData = $webpage->website->data;
        $siteUrl = Arr::get($websiteData, 'gcp.siteUrl');
        if (! $siteUrl) {
            try {
                $siteEntry = $service->sites->listSites()->getSiteEntry();
                $listSite = Arr::pluck($siteEntry, 'siteUrl');
                $siteUrl = Arr::where($listSite, function (string $value) use ($webpage) {
                    return str_contains($value, $webpage->website->domain);
                });
                if (empty($siteUrl)) {
                    return '';
                }
                $siteUrl = Arr::first($siteUrl);
                data_set($websiteData, 'gcp.siteUrl', $siteUrl);
                $webpage->website->update(['data' => $websiteData]);
            } catch (ConnectException) {
                return $this->getSiteUrl($webpage, $service, $retry - 1);
            } catch (Exception $e) {
                debug($e);
            }
        }

        return $siteUrl;
    }

    private function getSearchAnalytics(Webpage $webpage, $service, string $siteUrl, array $modelData, $retry = 3): array
    {
        if ($retry == 0) {
            return [];
        }
        $query = new SearchAnalyticsQueryRequest;
        $currentDate = Date::now()->setTimezone('UTC');
        $query->startDate = Arr::get($modelData, 'startDate') ?? $currentDate->copy()->subWeek()->toDateString();
        $query->endDate = Arr::get($modelData, 'endDate') ?? $currentDate->toDateString();
        $query->dimensions = ['date'];
        $query->searchType = Arr::get($modelData, 'searchType') ?? 'web';
        $query->dataState = 'all';
        if ($webpage->url) {
            $query->setDimensionFilterGroups([
                'filters' => [
                    'dimension' => 'PAGE',
                    'expression' => "/$webpage->url$",
                    'operator' => 'INCLUDING_REGEX',
                ],
            ]);
        }

        try {
            $res = $service->searchanalytics->query($siteUrl, $query)->getRows();
        } catch (ConnectException) {
            return $this->getSearchAnalytics($webpage, $service, $siteUrl, $modelData, $retry - 1);
        } catch (Exception $e) {
            debug($e);
        }

        return $res;
    }

    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->action($webpage, $this->validatedData);
    }

    public function jsonResponse($data): array
    {
        return $data;
    }

    public function rules(): array
    {
        return [
            'startDate' => ['sometimes', 'date'],
            'endDate' => ['sometimes', 'date'],
            'searchType' => ['sometimes'],
        ];
    }

    public function action(Webpage $webpage, array $modelData, bool $strict = true): array
    {
        $this->strict = $strict;
        $this->asAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($webpage, $validatedData);
    }
}
