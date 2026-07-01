<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Jun 2026 19:20:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Cloudflare;

use App\Actions\Web\Website\BlockedCountries\UpdateWebsiteBlockedCountriesRegions;
use App\Models\Web\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;

class BlockCountriesInCloudflare
{
    use asAction;

    protected string $baseUrl = 'https://api.cloudflare.com/client/v4';
    // Stable identifier so we can find/replace/remove just this rule later
    protected string $countryBlockDescription = 'Block countries (aiku)';
    private string $apiToken;

    protected function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->apiToken)
            ->baseUrl($this->baseUrl)
            ->acceptJson();
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Website $website, array $countryCodes): array
    {
        $this->apiToken = decrypt($website->cloudflare_token);

        $rulesetId = $this->getOrCreateCustomRulesetId($website->cloudflare_zone_id);

        $response = $this->client()->get("/zones/$website->cloudflare_zone_id/rulesets/$rulesetId");
        $response->throw();

        $currentRules = $response->json('result.rules', []);


        // Strip out any previous version of our managed rule
        $otherRules = array_values(
            array_filter(
                $currentRules,
                fn($rule) => ($rule['description'] ?? null) !== $this->countryBlockDescription
            )
        );


        if (empty($countryCodes)) {
            // Just remove the rule, keep everything else untouched
            $updatedRules = $otherRules;
        } else {
            $countryList = implode(
                ' ',
                array_map(
                    fn($c) => '"'.strtoupper($c).'"',
                    $countryCodes
                )
            );

            $updatedRules = [
                ...$otherRules,
                [
                    'action'      => 'block',
                    'description' => $this->countryBlockDescription,
                    'enabled'     => true,
                    'expression'  => "(ip.src.country in {{$countryList}})",
                ],
            ];
        }

        $update = $this->client()->put("/zones/$website->cloudflare_zone_id/rulesets/$rulesetId", [
            'description' => 'WAF Custom Rules',
            'rules'       => $updatedRules,
        ]);

        $update->throw();

        foreach ($countryCodes as $countryCode) {
            UpdateWebsiteBlockedCountriesRegions::run(
                $website,
                [
                    'country' => $countryCode
                ]
                , true
            );
        }


        return $update->json();
    }

    /**
     * Get the zone's custom firewall ruleset ID, creating an empty one if needed.
     *
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    protected function getOrCreateCustomRulesetId(string $zoneId): string
    {
        $existing = $this->client()
            ->get("/zones/$zoneId/rulesets", ['phase' => 'http_request_firewall_custom']);

        $existing->throw();

        $rulesets = $existing->json('result', []);

        foreach ($rulesets as $ruleset) {
            if (($ruleset['kind'] ?? '') === 'zone') {
                return $ruleset['id'];
            }
        }

        $create = $this->client()->post("/zones/$zoneId/rulesets", [
            'name'        => 'default',
            'description' => 'WAF Custom Rules',
            'kind'        => 'zone',
            'phase'       => 'http_request_firewall_custom',
            'rules'       => [],
        ]);

        $create->throw();


        return $create->json('result.id');
    }

    public function getCommandSignature(): string
    {
        return 'cloudflare:block-countries {website} {countryCodes?* : List of country codes to block (e.g., US GB CN)}';
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function asCommand(Command $command): void
    {
        $website      = Website::where('slug', $command->argument('website'))->firstOrFail();
        $countryCodes = $command->argument('countryCodes') ?? [];
        $this->handle($website, $countryCodes);

        $command->info('Country blocking rules updated successfully.');
        $command->line('Blocked countries: '.(empty($countryCodes) ? 'None' : implode(', ', $countryCodes)));
    }
}
