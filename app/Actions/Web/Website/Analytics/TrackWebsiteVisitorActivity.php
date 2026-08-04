<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Analytics;

use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Events\Web\WebsiteVisitorCountUpdated;
use App\Events\Web\WebsiteVisitorHit;
use App\Models\CRM\WebUser;
use App\Models\Ordering\Order;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Redis;
use Lorisleiva\Actions\Concerns\AsAction;

class TrackWebsiteVisitorActivity
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 60;

    public const int WINDOW = 300;

    /**
     * Hard ceiling on how many visitors the dashboard renders in detail; the totals stay exact.
     * Raise it when the canvas can handle more than ~500 bubbles.
     */
    public const int MAX_DETAILED = 500;

    /**
     * Above this many concurrent sessions the website is not being browsed, it is being flooded.
     * Tracking pauses rather than minting a Redis hash and a cold device-detector parse per
     * request, and resumes by itself once the pause expires and traffic has fallen back.
     */
    public const int MAX_ACTIVE = 2000;

    public const int PAUSE_SECONDS = 300;

    /**
     * @param  array{user_agent?: string|null, referer?: string|null, page?: string|null, page_title?: string|null, country?: string|null, city?: string|null, region?: string|null, web_user_id?: int|null}  $hit
     */
    public function handle(Website $website, string $sessionId, array $hit): void
    {
        // Checked before anything else: under a flood the expensive work is the device-detector
        // parse in buildMetadata(), which a rotating user agent would miss the cache on every time.
        if ($this->isPaused($website)) {
            return;
        }

        $now = time();

        $metadata = $this->buildMetadata($website, $hit, $now);

        $keyLoggedIn  = $this->key($website, 'visitors:logged_in');
        $keyLoggedOut = $this->key($website, 'visitors:logged_out');
        $activeKey    = $metadata['logged_in'] === '1' ? $keyLoggedIn : $keyLoggedOut;
        $dataKey      = $this->dataKey($website, $sessionId);

        $results = $this->pipeline(function ($pipe) use ($keyLoggedIn, $keyLoggedOut, $activeKey, $dataKey, $sessionId, $metadata, $now) {
            $pipe->zrem($keyLoggedIn, $sessionId);
            $pipe->zrem($keyLoggedOut, $sessionId);
            $pipe->zadd($activeKey, $now, $sessionId);
            $pipe->hmset($dataKey, $metadata);
            $pipe->expire($dataKey, self::WINDOW);
            $pipe->zremrangebyscore($keyLoggedIn, 0, $now - self::WINDOW);
            $pipe->zremrangebyscore($keyLoggedOut, 0, $now - self::WINDOW);
            $pipe->zcard($keyLoggedIn);
            $pipe->zcard($keyLoggedOut);
        });

        $loggedIn  = (int) $results[7];
        $loggedOut = (int) $results[8];

        if ($loggedIn + $loggedOut > self::MAX_ACTIVE) {
            $this->pause($website);

            return;
        }

        if (!$this->isWatched($website)) {
            return;
        }

        WebsiteVisitorCountUpdated::dispatch($website, $loggedIn, $loggedOut);
        WebsiteVisitorHit::dispatch($website, array_merge(['session_id' => $sessionId], $metadata));
    }

    /**
     * Self-healing: the pause simply expires, and re-trips on the next hit if the flood is still on.
     */
    public function isPaused(Website $website): bool
    {
        return (bool) Redis::exists($this->key($website, 'live:paused'));
    }

    public function pause(Website $website): void
    {
        Redis::setex($this->key($website, 'live:paused'), self::PAUSE_SECONDS, 1);
    }

    public function getCounts(Website $website): array
    {
        $results = $this->sweep($website);

        return [
            'logged_in'  => (int) $results[2],
            'logged_out' => (int) $results[3],
        ];
    }

    public function getActiveVisitors(Website $website, ?int $limit = null): array
    {
        $limit = min($limit ?? self::MAX_DETAILED, self::MAX_DETAILED);

        $this->sweep($website);

        $sessions = array_slice(
            array_merge(
                Redis::zrevrange($this->key($website, 'visitors:logged_in'), 0, $limit - 1),
                Redis::zrevrange($this->key($website, 'visitors:logged_out'), 0, $limit - 1)
            ),
            0,
            $limit
        );

        if (!$sessions) {
            return [];
        }

        $rows = $this->pipeline(function ($pipe) use ($website, $sessions) {
            foreach ($sessions as $sessionId) {
                $pipe->hgetall($this->dataKey($website, $sessionId));
            }
        });

        $visitors = [];
        foreach ($sessions as $index => $sessionId) {
            if (!empty($rows[$index])) {
                $visitors[] = array_merge(['session_id' => $sessionId], $rows[$index]);
            }
        }

        return $visitors;
    }

    /**
     * The per-hit broadcast only goes out while somebody has the live dashboard open, so a busy
     * storefront costs nothing on soketi when nobody is looking.
     */
    public function isWatched(Website $website): bool
    {
        return (bool) Redis::exists($this->key($website, 'live:watched'));
    }

    public function markWatched(Website $website): void
    {
        Redis::setex($this->key($website, 'live:watched'), 120, 1);
    }

    protected function sweep(Website $website): array
    {
        $cutoff       = time() - self::WINDOW;
        $keyLoggedIn  = $this->key($website, 'visitors:logged_in');
        $keyLoggedOut = $this->key($website, 'visitors:logged_out');

        return $this->pipeline(function ($pipe) use ($keyLoggedIn, $keyLoggedOut, $cutoff) {
            $pipe->zremrangebyscore($keyLoggedIn, 0, $cutoff);
            $pipe->zremrangebyscore($keyLoggedOut, 0, $cutoff);
            $pipe->zcard($keyLoggedIn);
            $pipe->zcard($keyLoggedOut);
        });
    }

    /**
     * The Redis facade documents pipeline() as taking no arguments and returning \Redis|bool, so
     * the queued commands are run through the connection, which declares the real contract.
     *
     * @return array<int, mixed>
     */
    protected function pipeline(callable $commands): array
    {
        return (array) Redis::connection()->pipeline($commands);
    }

    protected function key(Website $website, string $suffix): string
    {
        return sprintf('website:%d:%s', $website->id, $suffix);
    }

    protected function dataKey(Website $website, string $sessionId): string
    {
        return sprintf('website:%d:visitor:%s:data', $website->id, $sessionId);
    }

    /**
     * Redis hashes only hold strings, so booleans are written as '1'/'0' and nulls are dropped
     * rather than becoming indistinguishable empty strings.
     *
     * @return array<string, string>
     */
    protected function buildMetadata(Website $website, array $hit, int $now): array
    {
        $userAgent = $hit['user_agent'] ?? null;
        $browser   = $userAgent ? GetBrowserInfo::run($userAgent) : [];
        $search    = $this->extractSearchInfo($hit['referer'] ?? null);
        $webUserId = $hit['web_user_id'] ?? null;

        $metadata = array_merge(
            [
                'url'           => $hit['referer'] ?? null,
                'page'          => $hit['page'] ?? null,
                'page_title'    => $hit['page_title'] ?? null,
                'country'       => $hit['country'] ?? 'XX',
                'city'          => $hit['city'] ?? null,
                'region'        => $hit['region'] ?? null,
                'logged_in'     => $webUserId ? '1' : '0',
                'device'        => $browser['device'] ?? null,
                'browser'       => $browser['browser'] ?? null,
                'os'            => $browser['os'] ?? null,
                'search_engine' => $search['engine'],
                'search_term'   => $search['term'],
                'last_active'   => (string) $now,
            ],
            $this->getBasket($website, $webUserId)
        );

        return array_map(
            fn ($value) => (string) $value,
            array_filter($metadata, fn ($value) => $value !== null && $value !== '')
        );
    }

    /**
     * @return array{customer_id?: int, customer_name?: string|null, basket_amount?: string, currency_code?: string|null}
     */
    protected function getBasket(Website $website, ?int $webUserId): array
    {
        if (!$webUserId) {
            return [];
        }

        $webUser = WebUser::with('customer')->find($webUserId);

        if (!$webUser?->customer) {
            return [];
        }

        // A basket sits in 'creating' indefinitely, so its value alone only says "is a customer".
        // The last time it moved is what separates someone ordering from someone browsing.
        $basket = Order::where('customer_id', $webUser->customer_id)
            ->where('state', OrderStateEnum::CREATING)
            ->selectRaw('SUM(total_amount) AS amount, MAX(updated_at) AS touched_at')
            ->first();

        return [
            'customer_id'        => $webUser->customer_id,
            'customer_name'      => $webUser->customer->company_name ?: $webUser->customer->contact_name,
            'basket_amount'      => (string) ($basket?->amount ?? 0),
            'basket_touched_at'  => $basket?->touched_at ? (string) strtotime($basket->touched_at) : null,
            'currency_code'      => $website->shop?->currency?->code,
        ];
    }

    protected function extractSearchInfo(?string $referer): array
    {
        if (!$referer) {
            return ['engine' => null, 'term' => null];
        }

        $url  = parse_url($referer);
        $host = $url['host'] ?? '';
        parse_str($url['query'] ?? '', $params);

        $engines = [
            'google'     => ['host' => 'google', 'param' => 'q'],
            'bing'       => ['host' => 'bing', 'param' => 'q'],
            'yahoo'      => ['host' => 'yahoo', 'param' => 'p'],
            'duckduckgo' => ['host' => 'duckduckgo', 'param' => 'q'],
            'baidu'      => ['host' => 'baidu', 'param' => 'wd'],
        ];

        foreach ($engines as $name => $data) {
            if (str_contains($host, $data['host'])) {
                return [
                    'engine' => ucfirst($name),
                    'term'   => $params[$data['param']] ?? null
                ];
            }
        }

        return ['engine' => null, 'term' => null];
    }
}
