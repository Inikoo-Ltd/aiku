<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 22:07:01 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Actions\Web\Website\BlockedCountries\CheckIfCountryRegionsIsBlocked;
use App\Actions\Web\Website\BlockedCountries\LogRestrictedCountryRegion;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchFirewallBlockedCountryEvents
{
    use AsAction;

    protected string $baseUrl = 'https://api.cloudflare.com/client/v4';
    protected string $countryBlockDescription = 'Block countries (aiku)';
    // ponytail: Cloudflare's adaptive dataset ingests events with a delay; querying up to "now" and
    // advancing the cursor there would permanently skip events not yet ingested. Trail "until" behind now.
    protected int $ingestionLagMinutes = 15;

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(): void
    {
        $websites = Website::whereNotNull('cloudflare_zone_id')->where('migrated', 'true')->whereNotNull('cloudflare_token')->get();

        /** @var Website $website */
        foreach ($websites as $website) {
            $currentBlockedCountryRegions  = $website->blocked_country_regions;
            $currentCountryWithRegionsData = array_filter($currentBlockedCountryRegions, fn ($item) => !empty($item['postcode']));
            if (!empty($currentCountryWithRegionsData)) {
                $this->handleWebsite($website);
            }
        }
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handleWebsite(Website $website): void
    {
        $apiToken = decrypt($website->cloudflare_token);

        $client = Http::withToken($apiToken)->baseUrl($this->baseUrl)->acceptJson();

        $ruleId = $this->getBlockCountriesRuleId($client, $website->cloudflare_zone_id);

        if ($ruleId === null) {
            return;
        }

        $cursorKey = $this->cursorKey($website);
        $since     = Cache::get($cursorKey) ? Carbon::parse(Cache::get($cursorKey)) : now()->subHour();
        $until     = now()->subMinutes($this->ingestionLagMinutes);

        if ($since->gte($until)) {
            return;
        }

        $events = $this->getFirewallEvents($apiToken, $website->cloudflare_zone_id, $ruleId, 'block', $since, $until);

        foreach ($events as $event) {
            $geoData = CheckIfCountryRegionsIsBlocked::make()->getRequestGeoData($event['clientCountryName'], $event['clientIP']);

            if (Arr::get($geoData, 'id')) {
                $date = Carbon::parse($event['datetime']);
                LogRestrictedCountryRegion::run(
                    [
                        true,
                        $geoData['id']
                    ],
                    $date
                );
            }
        }

        Cache::forever($cursorKey, $until->toIso8601String());
    }

    protected function cursorKey(Website $website): string
    {
        return "cloudflare-firewall-events-fetched-until:$website->id";
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function getBlockCountriesRuleId(PendingRequest $client, string $zoneId): ?string
    {
        $rulesets = $client->get("/zones/$zoneId/rulesets", ['phase' => 'http_request_firewall_custom']);
        $rulesets->throw();

        $rulesetId = null;
        foreach ($rulesets->json('result', []) as $ruleset) {
            if (($ruleset['kind'] ?? '') === 'zone') {
                $rulesetId = $ruleset['id'];
                break;
            }
        }

        if ($rulesetId === null) {
            return null;
        }


        $ruleset = $client->get("/zones/$zoneId/rulesets/$rulesetId");
        $ruleset->throw();


        foreach ($ruleset->json('result.rules', []) as $rule) {
            if (($rule['description'] ?? null) === $this->countryBlockDescription) {
                return $rule['id'];
            }
        }

        return null;
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function getFirewallEvents(string $apiToken, string $zoneTag, string $ruleId, string $action, CarbonInterface $since, CarbonInterface $until): array
    {
        $since = $since->toIso8601String();
        $until = $until->toIso8601String();

        $query = <<<GQL
            query Viewer {
                viewer {
                    zones(filter: { zoneTag: "$zoneTag" }) {
                        firewallEventsAdaptive(
                            filter: { ruleId: "$ruleId", action: "$action", datetime_geq: "$since", datetime_leq: "$until" }
                            limit: 1000
                            orderBy: [datetime_ASC]
                        ) {
                            action
                            clientIP
                            clientCountryName
                            datetime
                            ruleId
                        }
                    }
                }
            }
        GQL;

        $response = Http::timeout(10)->withHeaders([
            'Authorization' => "Bearer $apiToken",
            'Content-Type'  => 'application/json',
        ])->post("$this->baseUrl/graphql", ['query' => $query]);

        $response->throw();

        return $response->json('data.viewer.zones.0.firewallEventsAdaptive', []);
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:fetch-firewall-blocked-country-events';
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Firewall blocked country events fetched.');
    }
}
