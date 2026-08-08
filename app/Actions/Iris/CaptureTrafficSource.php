<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 11:43:05 Central Indonesia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris;

use App\Actions\CRM\TrafficSource\GetTrafficSourceFromRefererHeader;
use App\Actions\CRM\TrafficSource\RecordTrafficSourceVisit;
use App\Actions\CRM\TrafficSource\GetTrafficSourceFromUrl;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Web\Website\WebsiteTypeEnum;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class CaptureTrafficSource
{
    use AsAction;

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function handle(): array
    {
        if (!$this->canCaptureTrafficSource()) {
            return [];
        }

        return $this->getCookies();
    }


    public function getCookies(): array
    {
        $cookies = [];
        $referer = request()->headers->get('referer', '');

        // Check both referer and current full URL
        $trafficSourceData = GetTrafficSourceFromUrl::run(request()->fullUrl());

        /* When this runs from the first-hit AJAX endpoint the ad click ids are not on the request URL,
           they are on the storefront page that issued the fetch, which is exactly what the browser
           puts in the Referer header. Without this the paid capture is silently a no-op behind Varnish,
           where the AJAX endpoint is the only capture path left. */
        if ($trafficSourceData === null) {
            $trafficSourceData = GetTrafficSourceFromUrl::run($referer);
        }

        /* Organic traffic has no click id to read, only the referring domain, and that domain is
           gone by the time this AJAX call is made: its Referer is the storefront page itself. The
           client forwards document.referrer in X-Original-Referer precisely so it survives. Varnish
           also fills this header in from the real Referer on the cached page request, so the same
           lookup keeps working if capture ever runs outside the AJAX endpoint again. */
        if ($trafficSourceData === null) {
            $trafficSourceData = GetTrafficSourceFromRefererHeader::run(request()->headers->get('X-Original-Referer', ''));
        }

        if ($trafficSourceData === null) {
            $trafficSourceData = GetTrafficSourceFromRefererHeader::run($referer);
        }

        if (!$trafficSourceData) {
            $candidates = $this->refererCandidates($referer);

            /* Three different nothings. `unmatched` arrived from somewhere we failed to classify.
               `internal` is a page view whose referrer is one of our own systems - a visitor browsing,
               not arriving - and it dominates: one session leaves one identifiable arrival and then a
               dozen of these, which is why counting them as `direct` made healthy capture read as 7%
               identified. `direct` is a genuine arrival with no referrer at all: typed, bookmarked. */
            $outcome = match (true) {
                $candidates !== [] => 'unmatched',
                filled(request()->headers->get('X-Original-Referer')) || filled($referer) => 'internal',
                default => 'direct',
            };

            $this->recordCaptureOutcome($outcome, $candidates);

            return $cookies;
        }

        $lastTrafficSource = request()->cookie('aiku_lts');

        if ($lastTrafficSource == $trafficSourceData) {
            $this->recordCaptureOutcome('repeat');

            /* A returning visitor from the same channel is a visit, but only the first time today.
               This branch fires on every page load whose URL still carries the click id, so counting
               it each time turned one person reading five pages into five visits. */
            if ($marker = $this->countVisitOnce($trafficSourceData)) {
                $cookies['aiku_vcd'] = ['value' => $marker, 'duration' => 60 * 24 * 2];
            }

            return $cookies;
        }


        // Check if the cookie already exists
        $existingCookieData = request()->cookie('aiku_tsd');
        if ($existingCookieData) {
            $appendedTrafficSourceData = $existingCookieData.'|'.now()->utc()->timestamp.$trafficSourceData;
            $cookieSize                = (4 + strlen('aiku_tsd'.$appendedTrafficSourceData)) / 1024;

            if ($cookieSize > 3.9) {
                $appendedTrafficSourceData = $this->trimOldestTrafficSource($appendedTrafficSourceData);
            }

            $cookies['aiku_tsd'] = [
                'value'    => $appendedTrafficSourceData,
                'duration' => 60 * 24 * 120,
            ];
        } else {
            $cookies['aiku_tsd'] = [
                'value'    => now()->utc()->timestamp.$trafficSourceData,
                'duration' => 60 * 24 * 120,
            ];
        }
        $cookies['aiku_lts'] = [
            'value'    => $trafficSourceData,
            'duration' => 60 * 24 * 120,
        ];

        $this->recordCaptureOutcome('matched');

        if ($marker = $this->countVisitOnce($trafficSourceData)) {
            $cookies['aiku_vcd'] = ['value' => $marker, 'duration' => 60 * 24 * 2];
        }

        return $cookies;
    }

    /**
     * Whether this hit arrived with anything at all that could name a source. A visitor who typed the
     * address or used a bookmark leaves nothing here, and produces no touch entirely legitimately;
     * one who arrived from somewhere we failed to recognise leaves a referrer we could not classify.
     * Counting those two apart is the only way to tell "our traffic is direct" from "capture is
     * missing sources it should be finding".
     *
     * @return array<int, string>
     */
    private function refererCandidates(string $referer): array
    {
        return array_values(array_filter([
            request()->headers->get('X-Original-Referer', ''),
            $referer,
        ], function (string $candidate) {
            if (blank($candidate)) {
                return false;
            }

            /* Our own systems are not a source: the admin app linking to a storefront, or one shop
               linking to another, would otherwise read as unrecognised traffic worth chasing. */
            return GetTrafficSourceFromRefererHeader::normaliseHost(parse_url($candidate, PHP_URL_HOST)) !== null;
        }));
    }

    /**
     * Counts the visit unless this browser has already been counted today, and says whether it did so
     * the caller can stamp the marker cookie.
     *
     * A visit is a person arriving, not a page being loaded. Without this, any visitor whose URL keeps
     * its click id through internal navigation counted once per page, and the visits column measured
     * browsing rather than arrivals.
     *
     * Delegated to RecordTrafficSourceVisit so a storefront arrival and an email click are counted the
     * same way; see that action for why they cannot share one code path.
     */
    private function countVisitOnce(string $trafficSourceData): ?string
    {
        $abbr = substr($trafficSourceData, 0, 1);
        $type = TrafficSourcesTypeEnum::fromAbbr($abbr);

        if (!$type) {
            return null;
        }

        /* Per channel, not per visitor. Somebody who arrives from Google in the morning and clicks a
           newsletter in the afternoon is one visit for each: both channels did send them. Splitting
           that into halves would discount the traffic twice, since the attributed columns already
           share the credit between the two touches.

           The marker is `<date>|<abbrs counted today>`, so it stays one small cookie however many
           channels a visitor arrives through. */
        [$markedDate, $counted] = array_pad(explode('|', (string) request()->cookie('aiku_vcd'), 2), 2, '');
        $today                  = now()->toDateString();
        $counted                = $markedDate === $today ? $counted : '';

        if (str_contains($counted, $abbr)) {
            return null;
        }

        /* The iris middleware merges the website into the request input; reading the attribute bag as
           well keeps this resolvable wherever it was put. */
        $website = request()->input('website') ?? request()->attributes->get('website');
        $shopId  = $website?->shop_id;

        if (!$shopId) {
            /* Nothing was counted, so nothing should be marked as counted - otherwise the marker
               suppresses the next load too and the visit is lost rather than deduplicated. */
            return null;
        }

        RecordTrafficSourceVisit::run($shopId, $type);

        return $today.'|'.$counted.$abbr;
    }

    /**
     * Per-day counters rather than log lines: this runs on every storefront first hit, so one line per
     * miss would bury the log and still need counting afterwards. Read them with
     * `traffic-source:capture-stats`.
     *
     * Nothing here may break a page view, hence the catch: a lost counter is a lost counter.
     *
     * @param array<int, string> $referers
     */
    private function recordCaptureOutcome(string $outcome, array $referers = []): void
    {
        try {
            $day = now()->toDateString();

            /* Split by whether anyone is logged in: the endpoint fires for regulars browsing as well
               as for first-time visitors, and lumping them together makes "direct" meaningless -
               a returning customer arriving direct says nothing about how we acquire people. */
            $audience = auth()->check() ? 'auth' : 'anon';
            $key      = 'traffic_capture:'.$day.':'.$audience.':'.$outcome;

            /* add() first so the counter carries an expiry; a bare increment on a missing key leaves
               it in the store forever. */
            Cache::add($key, 0, now()->addDays(8));
            Cache::increment($key);

            if ($outcome !== 'unmatched' || $referers === []) {
                return;
            }

            $host = parse_url($referers[0], PHP_URL_HOST);

            if (!$host) {
                return;
            }

            /* ponytail: capped so a referrer-spamming crawler cannot grow this without bound; the
               point is to spot the handful of real sources we are missing, not to log every host. */
            $hosts = Cache::get('traffic_capture:'.$day.':hosts', []);

            if (count($hosts) >= 100 && !isset($hosts[$host])) {
                return;
            }

            $hosts[$host] = ($hosts[$host] ?? 0) + 1;
            Cache::put('traffic_capture:'.$day.':hosts', $hosts, now()->addDays(8));
        } catch (\Throwable $e) {
            report($e);
        }
    }


    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function canCaptureTrafficSource(): bool
    {
        $routeName = request()->route() ? request()->route()->getName() : null;

        // Allow only if the route name starts with 'iris' or 'retina', these are the storefront and
        // customer-portal routes served behind Varnish, where CaptureTrafficSourceMiddleWare cannot run,
        // so this action is invoked directly from the AJAX first-hit endpoints and registration actions instead.
        if (!($routeName && (str_starts_with($routeName, 'iris') || str_starts_with($routeName, 'retina')))) {
            return false;
        }
        $website = request()->input('website');


        if (auth()->check() && $website?->type == WebsiteTypeEnum::DROPSHIPPING) {
            return false;
        }


        return true;
    }

    /**
     * The cookie is pipe-joined; splitting on the wrong separator here used to return the whole
     * history as one segment and slice it to nothing, wiping the visitor's touches the moment the
     * cookie hit its size cap.
     */
    public function trimOldestTrafficSource($trafficSourceData): string
    {
        $trafficSourceData = preg_split('/[|,]/', $trafficSourceData) ?: [];
        $trafficSourceData = array_slice($trafficSourceData, 1);

        return implode('|', $trafficSourceData);
    }

}
