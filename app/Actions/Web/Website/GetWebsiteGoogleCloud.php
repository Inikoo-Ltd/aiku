<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 07-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Web\Website;
use Exception;
use Google\Client;
use Google\Service\Webmasters;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWebsiteGoogleCloud extends OrgAction
{
    use AsAction;
    use WithNoStrictRules;

    private Website $website;
    private bool $saveSecret = false;

    /**
     * @throws \Throwable
     */
    public function handle(Website $website, array $modelData): array
    {
        $settings = $website->group->settings;
        $oauthClientSecret = Arr::get($settings, 'gcp.oauthClientSecret');
        if (!$oauthClientSecret) {
            $oauthClientSecret = config('app.analytics.google.client_oauth_secret');
            if (!$oauthClientSecret) {
                dd("secret is empty \n");
            }
            data_set($settings, "gcp.oauthClientSecret", $oauthClientSecret);
            $website->group->update(['settings' => $settings]);
        }

        $client = new Client();
        $gcpOauthClientSecretDecoded = base64_decode($oauthClientSecret);
        $client->setAuthConfig(json_decode($gcpOauthClientSecretDecoded, true));
        $client->addScope(Webmasters::WEBMASTERS_READONLY);
        $service = new Webmasters($client);

        $websiteData = $website->data;
        $siteUrl = Arr::get($websiteData, 'gcp.siteUrl');
        if (!$siteUrl) {
            $siteEntry = $service->sites->listSites()->getSiteEntry();
            $listSite = Arr::pluck($siteEntry, "siteUrl");
            $siteUrl = Arr::where($listSite, function (string $value) use ($website) {
                return str_contains($value, $website->domain);
            });
            if (empty($siteUrl)) {
                return [];
            }
            $siteUrl = Arr::first($siteUrl);
            data_set($websiteData, 'gcp.siteUrl', $siteUrl);
            $website->update(['data' => $websiteData]);
        }

        if ($this->saveSecret) {
            return [];
        }

        $query = new SearchAnalyticsQueryRequest();
        $currentDate = Date::now();
        $query->startDate = Arr::get($modelData, 'startDate') ?? $currentDate->copy()->subWeek()->toDateString();
        $query->endDate = Arr::get($modelData, 'endDate') ?? $currentDate->toDateString();
        $query->dimensions = Arr::get($modelData, 'dimentions') ?? ['date'];
        $query->searchType = Arr::get($modelData, 'searchType') ?? 'web';

        $res = $service->searchanalytics->query($siteUrl, $query);
        return $res->getRows();
    }

    public function asController(Website $website, ActionRequest $request): array
    {
        $this->initialisationFromShop($website->shop, $request);
        return $this->action($website, $this->validatedData);
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
            'dimensions' => ['sometimes'],
            'searchType' => ['sometimes']
        ];
    }

    public function action(Website $website, array $modelData, bool $strict = true): array
    {
        $this->strict   = $strict;
        $this->asAction = true;
        $this->setRawAttributes($modelData);
        $validatedData = $this->validateAttributes();

        return $this->handle($website, $validatedData);
    }

    public string $commandSignature = "gcp-website:search-result {website?} {--saveSecret}";

    /**
     * @throws \Exception
     */
    public function asCommand($command): int
    {

        $this->saveSecret = $command->option("saveSecret");

        if ($command->argument("website")) {
            try {
                /** @var Website $website */
                $website = Website::where("slug", $command->argument("website"))->firstOrFail();
            } catch (Exception) {
                $command->error("website not found");
                exit();
            }

            $command->line("Website ".$website->slug." fetched");

        } else {
            foreach (Website::orderBy('id')->get() as $website) {
                $command->line("Website ".$website->slug." fetched");
                $this->action($website, []);
            }
        }

        return 0;
    }


}
