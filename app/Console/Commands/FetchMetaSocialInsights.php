<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Actions\CRM\SocialMedia\GetSocialMediaAccount;
use App\Actions\CRM\SocialMedia\CheckMetaUsage;
use App\Enums\CRM\SocialMedia\SocialMediaPlatformEnum;
use App\Exceptions\MetaUsageLimitReached;
use App\Enums\CRM\SocialMedia\SocialMediaPostTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\SocialMediaAccount;
use App\Models\CRM\SocialMediaAccountDailyStat;
use App\Models\CRM\SocialMediaPost;
use Generator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Throwable;

/**
 * Pulls a shop's organic Facebook Page and Instagram figures into the same rows the uploaded
 * Business Suite exports fill, so either source corrects the other.
 *
 * A shop is synced when `shop.settings.meta_social` names its `facebook_page_id` and/or its
 * `instagram_account_id`. The token is the Meta Ads one (`settings.meta_ads.access_token`, then
 * `services.meta_ads.access_token`) unless `settings.meta_social.access_token` overrides it; it
 * needs pages_read_engagement, read_insights and instagram_manage_insights. Page calls are made with
 * the page's own token, which the system user token is exchanged for.
 *
 * Posts keep gaining reach for weeks, so every run re-reads the posts published in the last --days.
 * A Facebook post is kept under the post's own id, without the "{page id}_" the Graph API puts in
 * front of it, because that is the Post ID Business Suite exports: an uploaded post and a synced one
 * are the same row.
 * Metric names are Meta's "media view" family, which replaced impressions and reach in June 2026.
 *
 * Meta renames and retires metrics often, and one name it does not know fails a whole request, so
 * every daily metric is asked for on its own and the post metrics beyond the basic ones fall back to
 * the basic ones. Whatever Meta refuses is skipped and listed at the end of the run, so a first
 * --dry-run with a new token says which names to change at the top of this command.
 */
class FetchMetaSocialInsights extends Command
{
    protected $signature = 'social-media:fetch-meta
                           {shop? : Shop slug, defaults to every shop configured for Meta organic insights}
                           {--date= : Last day to fetch, defaults to yesterday}
                           {--days=28 : How many days to fetch, counting back from --date}
                           {--dry-run : Fetch and report without writing anything}';

    protected $description = 'Fetch organic Facebook Page and Instagram insights from the Meta Graph API';

    public const string FACEBOOK_POST_METRICS = 'post_total_media_view_unique,post_media_view';

    public const string FACEBOOK_POST_CLICK_METRIC = 'post_clicks_by_type';

    /**
     * Reactions are read from the post's insights, which read_insights covers: the reactions edge
     * would need pages_read_user_content, a permission that can also delete people's comments.
     */
    public const string FACEBOOK_POST_REACTION_METRIC = 'post_reactions_by_type_total';

    /**
     * A Page post's promotion_status when an ad boosts or has boosted it; `inactive` and
     * `ineligible` are posts never boosted.
     */
    public const array FACEBOOK_BOOSTED_STATUSES = ['active', 'extendable', 'finished', 'paused', 'pending', 'rejected', 'removed'];

    /**
     * Facebook Page metric per day => the daily stats column it fills.
     *
     * Interactions are not among them: page_post_engagements also counts clicks and engagement on
     * ads, 3,442 against Business Suite's 149 content interactions for the same 28 days in October
     * 2026, so they stay with the Content interactions export until Meta offers the same figure.
     */
    public const array FACEBOOK_PAGE_METRICS = [
        'page_total_media_view_unique' => 'reach',
        'page_media_view'              => 'impressions',
        'page_daily_follows_unique'    => 'new_follows',
        'page_views_total'             => 'profile_visits',
        'page_follows'                 => 'followers',
    ];

    public const string INSTAGRAM_MEDIA_METRICS = 'reach,views,saved,shares';

    public const string INSTAGRAM_STORY_METRICS = 'reach,views,shares';

    /**
     * Instagram account metrics Meta gives day by day => the daily stats column they fill.
     */
    public const array INSTAGRAM_ACCOUNT_METRICS = [
        'reach' => 'reach',
    ];

    /**
     * Instagram account metrics Meta keeps for the last 30 days only => the daily stats column they fill.
     */
    public const array INSTAGRAM_FOLLOWER_METRICS = [
        'follower_count' => 'new_follows',
    ];

    /**
     * Instagram account metrics Meta only gives as a total for a range, so they are asked for one
     * day at a time => the daily stats column they fill.
     */
    public const array INSTAGRAM_ACCOUNT_TOTALS = [
        'views'              => 'impressions',
        'total_interactions' => 'engagements',
        'profile_views'      => 'profile_visits',
        'website_clicks'     => 'link_clicks',
    ];

    private const int INSTAGRAM_ACCOUNT_MAX_DAYS = 30;

    /**
     * The longest stretch Meta answers in one insights request: 93 days for a Page, 30 for an
     * Instagram account. A longer run is asked for in windows of these.
     */
    private const int FACEBOOK_WINDOW_DAYS = 90;

    private const int INSTAGRAM_WINDOW_DAYS = 30;

    /**
     * @var array<int, string>
     */
    private array $unavailable = [];

    public function handle(): int
    {
        Nightwatch::dontSample();

        $until = $this->option('date') ? Carbon::parse($this->option('date')) : now()->subDay();
        $since = $until->copy()->subDays(max(1, (int) $this->option('days')) - 1);

        $shops = Shop::when($this->argument('shop'), fn ($query, $slug) => $query->where('slug', $slug))
            ->get()
            ->filter(fn (Shop $shop) => filled(data_get($shop->settings, 'meta_social.facebook_page_id'))
                || filled(data_get($shop->settings, 'meta_social.instagram_account_id')));

        if ($shops->isEmpty()) {
            $this->error('No shop is configured for Meta organic insights: set its Facebook Page ID or Instagram account ID.');

            return Command::FAILURE;
        }

        $failed = 0;

        foreach ($shops as $shop) {
            foreach ([SocialMediaPlatformEnum::FACEBOOK, SocialMediaPlatformEnum::INSTAGRAM] as $platform) {
                $externalId = (string) data_get($shop->settings, 'meta_social.'.($platform === SocialMediaPlatformEnum::FACEBOOK ? 'facebook_page_id' : 'instagram_account_id'));

                if ($externalId === '') {
                    continue;
                }

                try {
                    [$posts, $days] = $platform === SocialMediaPlatformEnum::FACEBOOK
                        ? $this->fetchFacebook($shop, $externalId, $since, $until)
                        : $this->fetchInstagram($shop, $externalId, $since, $until);

                    $this->info("{$shop->slug} {$platform->value}: {$posts} post(s), {$days} day(s).");
                } catch (MetaUsageLimitReached $e) {
                    $this->error("{$shop->slug} {$platform->value}: ".$e->getMessage());
                    Log::warning('Meta social insights fetch stopped at the usage limit', ['shop' => $shop->slug, 'platform' => $platform->value, 'error' => $e->getMessage()]);

                    return Command::FAILURE;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error("{$shop->slug} {$platform->value}: ".$this->withoutToken($e->getMessage()));
                    Log::error('Meta social insights fetch failed', ['shop' => $shop->slug, 'platform' => $platform->value, 'error' => $this->withoutToken($e->getMessage())]);
                }
            }
        }

        if ($this->unavailable !== []) {
            $this->warn('Meta did not give, so they were skipped: '.implode(', ', array_unique($this->unavailable)).'. Check these names at the top of '.class_basename(self::class).'.');
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fetchFacebook(Shop $shop, string $pageId, Carbon $since, Carbon $until): array
    {
        $page      = $this->get($this->url($pageId), ['fields' => 'name,followers_count,access_token'], $this->token($shop));
        $pageToken = (string) (data_get($page, 'access_token') ?: $this->token($shop));
        $account   = $this->account($shop, SocialMediaPlatformEnum::FACEBOOK, $pageId, data_get($page, 'name'), null);

        $basic     = 'id,message,created_time,permalink_url,attachments{media_type},shares';
        $comments  = 'comments.summary(total_count).limit(0)';
        $insights  = fn (string ...$metrics) => 'insights.metric('.implode(',', [self::FACEBOOK_POST_METRICS, ...$metrics]).')';
        $all       = $insights(self::FACEBOOK_POST_CLICK_METRIC, self::FACEBOOK_POST_REACTION_METRIC);
        $reactions = $insights(self::FACEBOOK_POST_REACTION_METRIC);
        $noComment = 'facebook comment counts (the token needs pages_read_user_content)';
        $noClicks  = 'facebook '.self::FACEBOOK_POST_CLICK_METRIC;
        $noReacts  = 'facebook '.self::FACEBOOK_POST_REACTION_METRIC;

        $promotions = $this->facebookPromotions($pageId, $since, $until, $pageToken);

        $posts = 0;
        foreach ($this->rowsWithFirstAccepted(
            $this->url($pageId.'/posts'),
            [
                ['fields' => "$basic,$comments,$all", 'missing' => []],
                ['fields' => "$basic,$all", 'missing' => [$noComment]],
                ['fields' => "$basic,$reactions", 'missing' => [$noComment, $noClicks]],
                ['fields' => "$basic,".$insights(), 'missing' => [$noComment, $noClicks, $noReacts]],
            ],
            [
                'since' => $since->copy()->startOfDay()->timestamp,
                'until' => $until->copy()->endOfDay()->timestamp,
                'limit' => 50,
            ],
            $pageToken
        ) as $post) {
            $insights  = $this->insightValues($post);
            $permalink = data_get($post, 'permalink_url');
            $mediaType = str_contains((string) $permalink, '/reel/') ? 'reel' : (data_get($post, 'attachments.data.0.media_type') ?? 'text');

            $this->storePost($account, Str::afterLast((string) data_get($post, 'id'), '_'), [
                'type'         => SocialMediaPostTypeEnum::fromExport($mediaType, SocialMediaPlatformEnum::FACEBOOK),
                'description'  => data_get($post, 'message'),
                'permalink'    => $permalink,
                'published_at' => Carbon::parse(data_get($post, 'created_time')),
                'reach'        => (int) ($insights['post_total_media_view_unique'] ?? 0),
                'impressions'  => (int) ($insights['post_media_view'] ?? 0),
                'reactions'    => (int) array_sum(array_filter((array) data_get(collect(data_get($post, 'insights.data', []))->firstWhere('name', self::FACEBOOK_POST_REACTION_METRIC), 'values.0.value', []), 'is_numeric')),
                'comments'     => (int) data_get($post, 'comments.summary.total_count', 0),
                'shares'       => (int) data_get($post, 'shares.count', 0),
                'link_clicks'  => (int) data_get(collect(data_get($post, 'insights.data', []))->firstWhere('name', self::FACEBOOK_POST_CLICK_METRIC), 'values.0.value.link clicks', 0),
                'is_boosted'   => in_array($promotions[(string) data_get($post, 'id')] ?? null, self::FACEBOOK_BOOSTED_STATUSES, true),
            ]);
            $posts++;
        }

        $days = $this->storeDays($account, array_replace_recursive(
            $this->eachDailyMetric($pageId, self::FACEBOOK_PAGE_METRICS, $since, $until, $pageToken, 'facebook', self::FACEBOOK_WINDOW_DAYS),
            $this->facebookAdViews($pageId, $since, $until, $pageToken)
        ));

        $this->storeFollowers($account, data_get($page, 'followers_count'));

        return [$posts, $days];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function fetchInstagram(Shop $shop, string $instagramId, Carbon $since, Carbon $until): array
    {
        $token   = $this->token($shop);
        $profile = $this->get($this->url($instagramId), ['fields' => 'username,name,followers_count'], $token);
        $account = $this->account($shop, SocialMediaPlatformEnum::INSTAGRAM, $instagramId, data_get($profile, 'name'), data_get($profile, 'username'));

        $fields = 'id,caption,media_type,media_product_type,permalink,timestamp,like_count,comments_count,insights.metric('.self::INSTAGRAM_MEDIA_METRICS.')';

        $posts = 0;
        foreach ($this->rowsWithFirstAccepted(
            $this->url($instagramId.'/media'),
            [
                ['fields' => $fields.',boost_ads_list', 'missing' => []],
                ['fields' => $fields, 'missing' => ['instagram boosted posts']],
            ],
            [
                'since' => $since->copy()->startOfDay()->timestamp,
                'until' => $until->copy()->endOfDay()->timestamp,
                'limit' => 50,
            ],
            $token
        ) as $media) {
            $insights = $this->insightValues($media);
            $boostAds = collect(data_get($media, 'boost_ads_list.data', []))->pluck('ad_id')->filter()->unique()->values();

            $this->storePost($account, (string) data_get($media, 'id'), [
                'type'         => SocialMediaPostTypeEnum::fromExport(data_get($media, 'media_product_type').' '.data_get($media, 'media_type'), SocialMediaPlatformEnum::INSTAGRAM),
                'description'  => data_get($media, 'caption'),
                'permalink'    => data_get($media, 'permalink'),
                'published_at' => Carbon::parse(data_get($media, 'timestamp')),
                'reach'        => (int) ($insights['reach'] ?? 0),
                'impressions'  => (int) ($insights['views'] ?? 0),
                'reactions'    => (int) data_get($media, 'like_count', 0),
                'comments'     => (int) data_get($media, 'comments_count', 0),
                'shares'       => (int) ($insights['shares'] ?? 0),
                'saves'        => (int) ($insights['saved'] ?? 0),
                'is_boosted'   => $boostAds->isNotEmpty(),
                ...$this->boostFigures($boostAds->all(), $this->adsToken($shop) ?: $token),
            ]);
            $posts++;
        }

        $posts += $this->fetchInstagramStories($account, $instagramId, $token);

        $accountSince = $since->copy()->max($until->copy()->subDays(self::INSTAGRAM_ACCOUNT_MAX_DAYS - 1));

        $days = $this->storeDays($account, array_replace_recursive(
            $this->eachDailyMetric($instagramId, self::INSTAGRAM_ACCOUNT_METRICS, $since, $until, $token, 'instagram', self::INSTAGRAM_WINDOW_DAYS),
            $this->eachDailyMetric($instagramId, self::INSTAGRAM_FOLLOWER_METRICS, $accountSince, $until, $token, 'instagram', self::INSTAGRAM_WINDOW_DAYS),
            $this->instagramDailyTotals($instagramId, $since, $until, $token),
            $this->instagramAdViews($instagramId, $since, $until, $token)
        ));

        $this->storeFollowers($account, data_get($profile, 'followers_count'));
        $this->storeFollowerHistory($account, data_get($profile, 'followers_count'), $this->instagramNetFollows($instagramId, $accountSince, $until, $token));

        return [$posts, $days];
    }

    /**
     * What the ads boosting a post added on top of its organic figures, which is what the Instagram
     * app counts on the post. Meta keeps them on the ads, so they need a token that may read the ad
     * account (ads_read); without one the post is still marked boosted, its ad figures left empty.
     *
     * @param array<int, string> $adIds
     *
     * @return array{ad_views: int|null, ad_reach: int|null, ad_link_clicks: int|null}
     */
    private function boostFigures(array $adIds, string $token): array
    {
        $figures = ['ad_views' => null, 'ad_reach' => null, 'ad_link_clicks' => null];

        foreach ($adIds as $adId) {
            try {
                $row = data_get($this->get($this->url($adId.'/insights'), [
                    'fields'      => 'impressions,reach,inline_link_clicks',
                    'date_preset' => 'maximum',
                ], $token), 'data.0', []);
            } catch (\RuntimeException) {
                $this->unavailable[] = 'boost ad figures (the token needs ads_read on the ad account)';

                return $figures;
            }

            $figures = [
                'ad_views'       => (int) $figures['ad_views'] + (int) data_get($row, 'impressions', 0),
                'ad_reach'       => (int) $figures['ad_reach'] + (int) data_get($row, 'reach', 0),
                'ad_link_clicks' => (int) $figures['ad_link_clicks'] + (int) data_get($row, 'inline_link_clicks', 0),
            ];
        }

        return $figures;
    }

    /**
     * Each post's promotion status, read on its own so a token that may not see it loses only the
     * Boosted mark, never the posts.
     *
     * @return array<string, string> Meta's post id => promotion status
     */
    private function facebookPromotions(string $pageId, Carbon $since, Carbon $until, string $token): array
    {
        try {
            return collect(iterator_to_array($this->rows($this->url($pageId.'/posts'), [
                'fields' => 'id,promotion_status',
                'since'  => $since->copy()->startOfDay()->timestamp,
                'until'  => $until->copy()->endOfDay()->timestamp,
                'limit'  => 100,
            ], $token), false))
                ->mapWithKeys(fn (array $post) => [(string) data_get($post, 'id') => (string) data_get($post, 'promotion_status')])
                ->all();
        } catch (\RuntimeException) {
            $this->unavailable[] = 'facebook boosted posts';

            return [];
        }
    }

    private function adsToken(Shop $shop): string
    {
        return (string) (data_get($shop->settings, 'meta_ads.access_token') ?: config('services.meta_ads.access_token'));
    }

    /**
     * Stories are only listed while they are live, so the nightly run keeps the ones of the last day.
     */
    private function fetchInstagramStories(SocialMediaAccount $account, string $instagramId, string $token): int
    {
        $stories = 0;

        try {
            foreach ($this->rows($this->url($instagramId.'/stories'), [
                'fields' => 'id,caption,media_type,media_product_type,permalink,timestamp,insights.metric('.self::INSTAGRAM_STORY_METRICS.')',
            ], $token) as $story) {
                $insights = $this->insightValues($story);

                $this->storePost($account, (string) data_get($story, 'id'), [
                    'type'         => SocialMediaPostTypeEnum::STORY,
                    'description'  => data_get($story, 'caption'),
                    'permalink'    => data_get($story, 'permalink'),
                    'published_at' => Carbon::parse(data_get($story, 'timestamp')),
                    'reach'        => (int) ($insights['reach'] ?? 0),
                    'impressions'  => (int) ($insights['views'] ?? 0),
                    'shares'       => (int) ($insights['shares'] ?? 0),
                ]);
                $stories++;
            }
        } catch (\RuntimeException) {
            $this->unavailable[] = 'instagram stories';
        }

        return $stories;
    }

    /**
     * Every metric asked for on its own, so one name Meta has retired does not lose the others.
     *
     * @param array<string, string> $metrics metric => column
     *
     * @return array<string, array<string, int>> date => column => value
     */
    private function eachDailyMetric(string $id, array $metrics, Carbon $since, Carbon $until, string $token, string $platform, int $windowDays): array
    {
        $byDate = [];

        foreach ($metrics as $metric => $column) {
            try {
                foreach ($this->dailyInsights($id, $metric, $since, $until, $token, $windowDays) as $date => $values) {
                    if (array_key_exists($metric, $values)) {
                        $byDate[$date][$column] = $values[$metric];
                    }
                }
            } catch (\RuntimeException) {
                $this->unavailable[] = $platform.' '.$metric;
            }
        }

        return $byDate;
    }

    /**
     * Instagram gives these account metrics only as a total for a range: the names Meta takes are
     * found on the first day, one by one, and every day after asks for those together.
     *
     * @return array<string, array<string, int>> date => column => value
     */
    private function instagramDailyTotals(string $instagramId, Carbon $since, Carbon $until, string $token): array
    {
        $byDate    = [];
        $available = [];

        for ($day = $since->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            $requests = $day->eq($since->copy()->startOfDay())
                ? array_map(fn (string $metric) => [$metric], array_keys(self::INSTAGRAM_ACCOUNT_TOTALS))
                : [array_values($available)];

            foreach ($requests as $metrics) {
                if ($metrics === []) {
                    continue;
                }

                try {
                    $response = $this->get($this->url($instagramId.'/insights'), [
                        'metric'      => implode(',', $metrics),
                        'metric_type' => 'total_value',
                        'period'      => 'day',
                        'since'       => $day->timestamp,
                        'until'       => $day->copy()->addDay()->timestamp,
                    ], $token);
                } catch (\RuntimeException) {
                    $this->unavailable[] = 'instagram '.implode(',', $metrics);

                    continue;
                }

                foreach (data_get($response, 'data', []) as $row) {
                    $name = data_get($row, 'name');
                    if (!isset(self::INSTAGRAM_ACCOUNT_TOTALS[$name]) || !is_numeric(data_get($row, 'total_value.value'))) {
                        continue;
                    }

                    $byDate[$day->toDateString()][self::INSTAGRAM_ACCOUNT_TOTALS[$name]] = (int) data_get($row, 'total_value.value');
                    $available[$name] = $name;
                }
            }
        }

        return $byDate;
    }

    /**
     * The Page's views of each day that came through ads (boosts and campaigns), which Meta counts
     * inside the Page's views; the rest of the day's views are organic.
     *
     * @return array<string, array{ad_impressions: int}> date => column => value
     */
    private function facebookAdViews(string $pageId, Carbon $since, Carbon $until, string $token): array
    {
        $byDate = [];

        try {
            for ($start = $since->copy()->startOfDay(); $start->lte($until); $start->addDays(self::FACEBOOK_WINDOW_DAYS)) {
                $end = $start->copy()->addDays(self::FACEBOOK_WINDOW_DAYS - 1)->min($until);

                $response = $this->get($this->url($pageId.'/insights'), [
                    'metric'    => 'page_media_view',
                    'period'    => 'day',
                    'breakdown' => 'is_from_ads',
                    'since'     => $start->timestamp,
                    'until'     => $end->copy()->addDay()->startOfDay()->timestamp,
                ], $token);

                foreach (data_get($response, 'data.0.values', []) as $value) {
                    if (!is_numeric(data_get($value, 'value')) || !array_key_exists('is_from_ads', $value)) {
                        continue;
                    }

                    $date = Carbon::parse(data_get($value, 'end_time'))->subDay()->toDateString();
                    $byDate[$date]['ad_impressions'] = ($byDate[$date]['ad_impressions'] ?? 0) + ((string) $value['is_from_ads'] === '1' ? (int) $value['value'] : 0);
                }
            }
        } catch (\RuntimeException) {
            $this->unavailable[] = 'facebook views from ads';
        }

        return $byDate;
    }

    /**
     * The account's views of each day that came through ads, Meta's `AD` media product type; asked
     * for one day at a time, since Instagram splits views only in a total for a range.
     *
     * @return array<string, array{ad_impressions: int}> date => column => value
     */
    private function instagramAdViews(string $instagramId, Carbon $since, Carbon $until, string $token): array
    {
        $byDate = [];

        for ($day = $since->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            try {
                $response = $this->get($this->url($instagramId.'/insights'), [
                    'metric'      => 'views',
                    'metric_type' => 'total_value',
                    'breakdown'   => 'media_product_type',
                    'period'      => 'day',
                    'since'       => $day->timestamp,
                    'until'       => $day->copy()->addDay()->timestamp,
                ], $token);
            } catch (\RuntimeException) {
                $this->unavailable[] = 'instagram views from ads';

                return $byDate;
            }

            if (!is_numeric(data_get($response, 'data.0.total_value.value'))) {
                continue;
            }

            $byDate[$day->toDateString()]['ad_impressions'] = (int) collect(data_get($response, 'data.0.total_value.breakdowns.0.results', []))
                ->filter(fn (array $result) => data_get($result, 'dimension_values.0') === 'AD')
                ->sum('value');
        }

        return $byDate;
    }

    /**
     * Instagram gives no follower count for past days, only how many accounts followed and unfollowed
     * on each one, so a day's net change is asked for one day at a time.
     *
     * @return array<string, int> date => followers gained less followers lost
     */
    private function instagramNetFollows(string $instagramId, Carbon $since, Carbon $until, string $token): array
    {
        $net = [];

        for ($day = $since->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            try {
                $response = $this->get($this->url($instagramId.'/insights'), [
                    'metric'      => 'follows_and_unfollows',
                    'metric_type' => 'total_value',
                    'breakdown'   => 'follow_type',
                    'period'      => 'day',
                    'since'       => $day->timestamp,
                    'until'       => $day->copy()->addDay()->timestamp,
                ], $token);
            } catch (\RuntimeException) {
                $this->unavailable[] = 'instagram follows_and_unfollows';

                return $net;
            }

            $byType = collect(data_get($response, 'data.0.total_value.breakdowns.0.results', []))
                ->mapWithKeys(fn (array $result) => [data_get($result, 'dimension_values.0') => (int) data_get($result, 'value', 0)]);

            $net[$day->toDateString()] = ($byType['FOLLOWER'] ?? 0) - ($byType['NON_FOLLOWER'] ?? 0);
        }

        return $net;
    }

    /**
     * The follower count of each past day, worked back from today's count through each later day's net
     * change. It stops at the first day whose change Meta did not give, and today's change, not asked
     * for, counts as none.
     *
     * @param array<string, int> $netByDate
     */
    private function storeFollowerHistory(SocialMediaAccount $account, mixed $followers, array $netByDate): void
    {
        if ($followers === null || $netByDate === [] || $this->option('dry-run')) {
            return;
        }

        $count = (int) $followers;
        $day   = now()->startOfDay();

        while (true) {
            $change = $day->isToday() ? ($netByDate[$day->toDateString()] ?? 0) : ($netByDate[$day->toDateString()] ?? null);
            if ($change === null) {
                return;
            }

            $count -= $change;
            $day->subDay();

            if (!isset($netByDate[$day->toDateString()])) {
                return;
            }

            SocialMediaAccountDailyStat::updateOrCreate(
                ['social_media_account_id' => $account->id, 'date' => $day->toDateString()],
                ['followers' => $count]
            );
        }
    }

    /**
     * The edge read with the richest fields Meta accepts for this token, best first: one row is asked
     * for with each until one is taken, so a missing permission or retired metric costs a request
     * rather than the account's posts, and what was left out is reported.
     *
     * @param array<int, array{fields: string, missing: array<int, string>}> $candidates
     *
     * @return Generator<int, array>
     */
    private function rowsWithFirstAccepted(string $url, array $candidates, array $query, string $token): Generator
    {
        $refusal = null;

        foreach ($candidates as $candidate) {
            try {
                $this->get($url, ['fields' => $candidate['fields'], 'limit' => 1] + $query, $token);
            } catch (\RuntimeException $e) {
                $refusal = $e;

                continue;
            }

            array_push($this->unavailable, ...$candidate['missing']);

            yield from $this->rows($url, ['fields' => $candidate['fields']] + $query, $token);

            return;
        }

        throw $refusal ?? new \RuntimeException('Meta accepted none of the field sets for '.$url);
    }

    private function account(Shop $shop, SocialMediaPlatformEnum $platform, string $externalId, ?string $name, ?string $username): SocialMediaAccount
    {
        if ($this->option('dry-run')) {
            return $shop->socialMediaAccounts()->make(['platform' => $platform, 'external_id' => $externalId, 'name' => $name ?? $externalId]);
        }

        return GetSocialMediaAccount::run($shop, $platform, $externalId, $name, $username);
    }

    private function storePost(SocialMediaAccount $account, string $externalId, array $values): void
    {
        if ($this->option('dry-run')) {
            $this->line(sprintf('  %s  %-10s %8d reach  %s', $values['published_at']->toDateString(), $values['type']->value, $values['reach'], $externalId));

            return;
        }

        SocialMediaPost::updateOrCreate(
            ['social_media_account_id' => $account->id, 'external_id' => $externalId],
            $values
        );
    }

    /**
     * @param array<string, array<string, int>> $byDate date => column => value
     */
    private function storeDays(SocialMediaAccount $account, array $byDate): int
    {
        foreach ($byDate as $date => $values) {
            if ($values === [] || $this->option('dry-run')) {
                continue;
            }

            SocialMediaAccountDailyStat::updateOrCreate(
                ['social_media_account_id' => $account->id, 'date' => $date],
                $values
            );
        }

        return count($byDate);
    }

    private function storeFollowers(SocialMediaAccount $account, mixed $followers): void
    {
        if ($followers === null || $this->option('dry-run')) {
            return;
        }

        $today = now()->toDateString();

        SocialMediaAccountDailyStat::updateOrCreate(
            ['social_media_account_id' => $account->id, 'date' => $today],
            ['followers' => (int) $followers]
        );

        $account->update(['followers' => (int) $followers, 'followers_at' => $today]);
    }

    /**
     * Meta dates a day's value by the end of that day, in the account's time zone: an end_time of the
     * 2nd at 07:00 UTC is the 1st. One request per window of days, never paged: an insights edge's
     * next link is the following window of time, so following it walks forward for ever.
     *
     * @return array<string, array<string, int>>
     */
    private function dailyInsights(string $id, string $metrics, Carbon $since, Carbon $until, string $token, int $windowDays): array
    {
        $byDate = [];

        for ($start = $since->copy()->startOfDay(); $start->lte($until); $start->addDays($windowDays)) {
            $end = $start->copy()->addDays($windowDays - 1)->min($until);

            $response = $this->get($this->url($id.'/insights'), [
                'metric' => $metrics,
                'period' => 'day',
                'since'  => $start->timestamp,
                'until'  => $end->copy()->addDay()->startOfDay()->timestamp,
            ], $token);

            foreach (data_get($response, 'data', []) as $metric) {
                foreach (data_get($metric, 'values', []) as $value) {
                    if (!is_numeric(data_get($value, 'value'))) {
                        continue;
                    }

                    $date = Carbon::parse(data_get($value, 'end_time'))->subDay()->toDateString();
                    $byDate[$date][data_get($metric, 'name')] = (int) data_get($value, 'value');
                }
            }
        }

        return $byDate;
    }

    /**
     * Meta can answer a post metric twice under one name, its lifetime total and its last days one
     * by one (`period=day`, often zeros); the lifetime total is the post's figure.
     *
     * @return array<string, int>
     */
    private function insightValues(array $row): array
    {
        return collect(data_get($row, 'insights.data', []))
            ->filter(fn (array $metric) => is_numeric(data_get($metric, 'values.0.value')))
            ->groupBy(fn (array $metric) => data_get($metric, 'name'))
            ->map(fn (Collection $metrics) => (int) data_get($metrics->first(fn (array $metric) => data_get($metric, 'period') !== 'day') ?? $metrics->first(), 'values.0.value', 0))
            ->all();
    }

    /**
     * A dropped or slow connection is retried, so one blip does not lose a whole account's run.
     * Meta's own refusals come back as responses and are not retried.
     */
    private function http(): PendingRequest
    {
        return Http::timeout(60)
            ->connectTimeout(20)
            ->retry(3, 2000, fn (Throwable $exception) => $exception instanceof ConnectionException, throw: false);
    }

    /**
     * Connection errors quote the URL, and the URL carries the token: it never reaches the output
     * or the log.
     */
    private function withoutToken(string $message): string
    {
        return (string) preg_replace('/access_token=[^&\s"\']+/', 'access_token=***', $message);
    }

    public static function token(Shop $shop): string
    {
        return (string) (data_get($shop->settings, 'meta_social.access_token')
            ?: data_get($shop->settings, 'meta_ads.access_token')
            ?: config('services.meta_ads.access_token'));
    }

    private function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('services.meta_ads.api_version').'/'.$path;
    }

    private function get(string $url, array $query, string $token): array
    {
        $response = $this->http()->get($url.'?'.http_build_query($query + ['access_token' => $token]));

        CheckMetaUsage::run($response);

        if ($response->failed()) {
            throw new \RuntimeException('Meta returned '.$response->status().': '.data_get($response->json(), 'error.message', $response->body()));
        }

        return $response->json() ?? [];
    }

    /**
     * Every row of a paginated edge. The next link already carries its paging cursor and the token,
     * so it is requested as it is: a query array alongside it would replace its query string and
     * fetch page one again. A link seen before ends the walk.
     *
     * @return Generator<int, array>
     */
    private function rows(string $url, array $query, string $token): Generator
    {
        $seen = [];
        $next = $url.'?'.http_build_query($query + ['access_token' => $token]);

        while ($next !== null && !isset($seen[$next])) {
            $seen[$next] = true;

            $response = $this->http()->get($next);

            CheckMetaUsage::run($response);

            if ($response->failed()) {
                throw new \RuntimeException('Meta returned '.$response->status().': '.data_get($response->json(), 'error.message', $response->body()));
            }

            yield from $response->json('data', []);

            $next = $response->json('paging.next');
        }
    }
}
