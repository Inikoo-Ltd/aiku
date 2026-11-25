<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\StaticWebsite;

use App\Enums\Web\StaticWebsite\StaticWebsiteLiveDomainStatusEnum;
use App\Models\Web\StaticWebsite;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Loads the home page of the info website's real domain and records whether Aiku answered it
 * (ServeStaticWebsite marks its pages with X-AIKU-STATIC-WEBSITE) or another host still does, with
 * the domain's nameservers, so staff can see which sites are really in Aiku before editing them.
 */
class CheckStaticWebsiteLiveDomain
{
    use AsAction;

    public string $commandSignature = 'static_website:check_live_domain {domain? : Only this info website}';

    public function handle(StaticWebsite $staticWebsite): StaticWebsite
    {
        $staticWebsite->update([
            'live_domain_status'     => $this->status($staticWebsite),
            'nameservers'            => $this->nameservers($staticWebsite->domain),
            'live_domain_checked_at' => now(),
        ]);

        return $staticWebsite;
    }

    private function status(StaticWebsite $staticWebsite): StaticWebsiteLiveDomainStatusEnum
    {
        try {
            $response = Http::timeout(10)->connectTimeout(5)->withoutRedirecting()->get('https://www.'.$staticWebsite->domain.'/');
        } catch (ConnectionException) {
            return StaticWebsiteLiveDomainStatusEnum::UNREACHABLE;
        }

        if ($response->header('X-AIKU-STATIC-WEBSITE') !== '') {
            return StaticWebsiteLiveDomainStatusEnum::AIKU;
        }

        return $response->serverError() ? StaticWebsiteLiveDomainStatusEnum::UNREACHABLE : StaticWebsiteLiveDomainStatusEnum::ELSEWHERE;
    }

    private function nameservers(string $domain): ?string
    {
        $records = @dns_get_record($domain, DNS_NS) ?: [];
        $hosts   = collect($records)->pluck('target')->filter()->map(fn (string $host) => strtolower($host))->sort()->values();

        return $hosts->isEmpty() ? null : $hosts->implode(', ');
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $query = StaticWebsite::query()->orderBy('domain');
        if ($command->argument('domain')) {
            $query->where('domain', StoreStaticWebsite::normaliseDomain($command->argument('domain')));
        }

        $query->each(function (StaticWebsite $staticWebsite) use ($command) {
            $this->handle($staticWebsite);
            $command->line("$staticWebsite->domain: {$staticWebsite->live_domain_status->value} (".($staticWebsite->nameservers ?? 'no nameservers').')');
        });

        return 0;
    }
}
