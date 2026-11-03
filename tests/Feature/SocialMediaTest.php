<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\CRM\SocialMedia\GetSocialMediaPerformance;
use App\Actions\Helpers\CurrencyExchange\GetHistoricCurrencyExchange;
use App\Actions\CRM\SocialMedia\ImportSocialMediaInsights;
use App\Actions\CRM\SocialMedia\SyncShopFromMeta;
use App\Console\Commands\FetchMetaSocialInsights;
use App\Enums\CRM\SocialMedia\SocialMediaPlatformEnum;
use App\Enums\CRM\SocialMedia\SocialMediaPostTypeEnum;
use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\CRM\SocialMediaAccount;
use App\Models\CRM\SocialMediaPost;
use App\Models\CRM\TrafficSourceCampaign;
use App\Models\CRM\TrafficSourceCampaignMetric;
use App\Models\Helpers\Currency;
use App\Models\Helpers\Upload;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    [$this->organisation, $this->user, $this->shop] = createOwnShop('social media');

    $this->shop->socialMediaAccounts()->each(fn (SocialMediaAccount $account) => $account->delete());

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
});

function socialCsv(string $name, array $lines): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, implode("\n", $lines)."\n");
}

function instagramContentExport(int $reelReach = 1000): UploadedFile
{
    return socialCsv('instagram-content.csv', [
        'Post ID,Account ID,Account username,Account name,Description,Duration (sec),Publish time,Permalink,Post type,Data comment,Date,Views,Reach,Likes,Shares,Follows,Comments,Saves',
        '"1801",17841400001,awgifts.eu,"AW Gifts Europe","Halloween range reveal",30,08/10/2026 10:00,https://www.instagram.com/reel/AAA/,IG reel,,Lifetime,5000,'.$reelReach.',80,10,4,6,4',
        '"1802",17841400001,awgifts.eu,"AW Gifts Europe","Brand spotlight",0,08/12/2026 09:30,https://www.instagram.com/p/BBB/,IG carousel,,Lifetime,"1,200",800,20,2,0,3,15',
        '"1803",17841400001,awgifts.eu,"AW Gifts Europe","Last month",0,07/20/2026 09:30,https://www.instagram.com/p/CCC/,IG image,,Lifetime,400,300,10,0,0,1,1',
    ]);
}

it('imports an instagram content export as one account and its posts', function () {
    $upload = ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());

    expect($upload->number_rows)->toBe(3)
        ->and($upload->number_success)->toBe(3)
        ->and($upload->number_fails)->toBe(0);

    $account = $this->shop->socialMediaAccounts()->sole();

    expect($account->platform)->toBe(SocialMediaPlatformEnum::INSTAGRAM)
        ->and($account->external_id)->toBe('17841400001')
        ->and($account->name)->toBe('AW Gifts Europe')
        ->and($account->username)->toBe('awgifts.eu');

    $reel = $account->posts()->where('external_id', '1801')->sole();

    expect($reel->type)->toBe(SocialMediaPostTypeEnum::REEL)
        ->and($reel->reach)->toBe(1000)
        ->and($reel->impressions)->toBe(5000)
        ->and($reel->reactions)->toBe(80)
        ->and($reel->saves)->toBe(4)
        ->and($reel->follows)->toBe(4)
        ->and($reel->published_at->toDateString())->toBe('2026-08-10')
        ->and($account->posts()->where('external_id', '1802')->value('impressions'))->toBe(1200);
});

it('corrects a post when a later export is imported, never adding it twice', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport(reelReach: 2500));

    $account = $this->shop->socialMediaAccounts()->sole();

    expect($account->posts()->count())->toBe(3)
        ->and($account->posts()->where('external_id', '1801')->value('reach'))->toBe(2500);
});

it('reads a single metric meta export into the account days and its follower count', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());

    $upload = ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('followers.csv', [
        'sep=,',
        '"Instagram followers"',
        '"Date","Primary"',
        '"2026-07-31T00:00:00","1000"',
        '"2026-08-31T00:00:00","1150"',
    ]));

    expect($upload->number_success)->toBe(2);

    $account = $this->shop->socialMediaAccounts()->sole()->refresh();

    expect($account->dailyStats()->count())->toBe(2)
        ->and($account->followers)->toBe(1150)
        ->and($account->followers_at->toDateString())->toBe('2026-08-31');
});

it('splits a daily export carrying several platforms between their accounts', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('overview.csv', [
        'Date,Facebook Page reach,Facebook Page new follows,Instagram reach,Instagram followers',
        '2026-08-01,500,3,900,2000',
    ]));

    $accounts = $this->shop->socialMediaAccounts()->get()->keyBy(fn (SocialMediaAccount $account) => $account->platform->value);

    expect($accounts)->toHaveCount(2)
        ->and($accounts['facebook']->dailyStats()->sole()->reach)->toBe(500)
        ->and($accounts['facebook']->dailyStats()->sole()->new_follows)->toBe(3)
        ->and($accounts['instagram']->dailyStats()->sole()->reach)->toBe(900)
        ->and($accounts['instagram']->refresh()->followers)->toBe(2000);
});

it('reads a business suite facebook content export as comma separated, even when its links make the colon look steadier', function () {
    $upload = ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('facebook-content.csv', [
        '"Post ID","Page ID","Page name",Description,"Publish time",Permalink,"Post type","Data comment",Date,Views,Reach,Reactions,Comments,Shares,"Total clicks","Link clicks","Seconds viewed"',
        '1701,100063543606229,"AW Artisan SL","Velas, cristales y jabones","10/01/2026 02:00",https://www.facebook.com/reel/1701/,Videos,,Lifetime,477,411,4,0,0,0,,1475.224',
        '1702,100063543606229,"AW Artisan SL","Cuencos tibetanos","10/02/2026 02:00",https://www.facebook.com/permalink.php?story_fbid=pfbid02&id=100063543606229,Photos,,Lifetime,276,179,1,0,0,13,6,',
        '1703,100063543606229,"AW Artisan SL","Joyería de cobre","10/03/2026 02:00",https://www.facebook.com/permalink.php?story_fbid=pfbid03&id=100063543606229,Photos,,Lifetime,184,125,0,0,0,1',
        '1704,100063543606229,"AW Artisan SL","Bolsos de yute","10/04/2026 02:00",https://www.facebook.com/permalink.php?story_fbid=pfbid04&id=100063543606229,Photos,,Lifetime,190,131,1,0,0,0',
    ]));

    expect($upload->number_rows)->toBe(4)
        ->and($upload->number_success)->toBe(4)
        ->and($upload->number_fails)->toBe(0);

    $account = $this->shop->socialMediaAccounts()->sole();
    $reel    = $account->posts()->where('external_id', '1701')->sole();
    $photo   = $account->posts()->where('external_id', '1702')->sole();

    expect($account->platform)->toBe(SocialMediaPlatformEnum::FACEBOOK)
        ->and($account->external_id)->toBe('100063543606229')
        ->and($reel->type)->toBe(SocialMediaPostTypeEnum::REEL)
        ->and($reel->reach)->toBe(411)
        ->and($reel->impressions)->toBe(477)
        ->and($photo->type)->toBe(SocialMediaPostTypeEnum::IMAGE)
        ->and($photo->link_clicks)->toBe(6)
        ->and($photo->published_at->toDateString())->toBe('2026-10-02');
});

it('imports a tiktok studio content export without an account id', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('tiktok.csv', [
        'Video title,Video link,Post time,Total likes,Total comments,Total shares,Total views',
        'Unboxing,https://www.tiktok.com/@awgifts/video/7400000000000000001,2026-08-05 18:00,120,8,15,9000',
    ]));

    $post = SocialMediaPost::where('permalink', 'like', '%7400000000000000001')->sole();

    expect($post->socialMediaAccount->platform)->toBe(SocialMediaPlatformEnum::TIKTOK)
        ->and($post->type)->toBe(SocialMediaPostTypeEnum::VIDEO)
        ->and($post->impressions)->toBe(9000)
        ->and($post->reactions)->toBe(120);
});

it('explains the rows it cannot import', function () {
    $upload = ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('instagram-content.csv', [
        'Post ID,Account ID,Account username,Permalink,Post type,Publish time,Reach',
        '1901,17841400001,awgifts.eu,https://www.instagram.com/p/DDD/,IG image,,100',
        '1902,17841400001,awgifts.eu,https://www.instagram.com/p/EEE/,IG image,08/03/2026 08:00,100',
    ]));

    expect($upload->number_success)->toBe(1)
        ->and($upload->number_fails)->toBe(1)
        ->and($upload->records()->where('status', UploadRecordStatusEnum::FAILED)->sole()->errors)->toBe(['Post has no publish time']);
});

it('reads a chart export whose title does not name the platform into the platform chosen at upload, viewers as reach', function () {
    $chart = fn (string $title, string $value) => socialCsv('Viewers.csv', [
        'sep=,',
        '"'.$title.'"',
        '"Date","Primary"',
        '"2026-08-01T00:00:00","'.$value.'"',
    ]);

    $unknown = ImportSocialMediaInsights::make()->handle($this->shop, $chart('Viewers', '300'));

    expect($unknown->number_fails)->toBe(1)
        ->and($this->shop->socialMediaAccounts()->count())->toBe(0);

    ImportSocialMediaInsights::make()->handle($this->shop, $chart('Viewers', '300'), SocialMediaPlatformEnum::INSTAGRAM);
    ImportSocialMediaInsights::make()->handle($this->shop, $chart('Views', '450'), SocialMediaPlatformEnum::INSTAGRAM);

    $day = $this->shop->socialMediaAccounts()->sole()->dailyStats()->sole();

    expect($day->socialMediaAccount->platform)->toBe(SocialMediaPlatformEnum::INSTAGRAM)
        ->and($day->reach)->toBe(300)
        ->and($day->impressions)->toBe(450);
});

it('takes the platform chosen at upload through the shop route', function () {
    $this->post(route('grp.models.shop.social_media.upload', ['shop' => $this->shop->id, 'platform' => 'facebook']), [
        'file' => socialCsv('Views.csv', ['sep=,', '"Views"', '"Date","Primary"', '"2026-08-01T00:00:00","120"']),
    ])->assertCreated();

    expect($this->shop->socialMediaAccounts()->sole()->platform)->toBe(SocialMediaPlatformEnum::FACEBOOK);
});

it('says a file is empty when meta exported no figures', function () {
    $upload = ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('Follows.csv', ['sep=,']));

    expect($upload->number_fails)->toBe(1)
        ->and($upload->records()->sole()->errors)->toContain(__('The file is empty: Meta exported no figures for this metric and period'));
});

it('keeps one account when a content export names the page by another id than the one the shop has', function () {
    $post = fn (string $pageId, string $postId) => socialCsv('facebook-posts.csv', [
        '"Post ID","Page ID","Page name",Description,"Publish time",Permalink,"Post type",Views,Reach,Reactions',
        $postId.','.$pageId.',"Ancient Wisdom","A post","08/12/2026 07:29",https://www.facebook.com/permalink.php?story_fbid='.$postId.',Photos,100,80,3',
    ]);

    ImportSocialMediaInsights::make()->handle($this->shop, $post('128699543823945', '9001'));
    ImportSocialMediaInsights::make()->handle($this->shop, $post('100064762416556', '9002'));

    $account = $this->shop->socialMediaAccounts()->sole();

    expect($account->external_id)->toBe('128699543823945')
        ->and($account->posts()->pluck('external_id')->sort()->values()->all())->toBe(['9001', '9002']);
});

it('rejects a file that is not a social media export', function () {
    $upload = ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('prospects.csv', [
        'Name,Email',
        'Ann,ann@example.com',
    ]));

    expect($upload->number_fails)->toBe(1)
        ->and($this->shop->socialMediaAccounts()->count())->toBe(0);
});

it('reports a period with its previous one, formats, platforms and top posts', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());

    $performance = GetSocialMediaPerformance::run(
        $this->organisation,
        Carbon::parse('2026-08-01')->startOfDay(),
        Carbon::parse('2026-08-31')->endOfDay(),
        ['shop' => $this->shop->slug]
    );

    $current  = $performance['kpis']['current'];
    $previous = $performance['kpis']['previous'];

    expect($current['posts'])->toBe(2)
        ->and($current['reach'])->toBe(1800)
        ->and($current['engagements'])->toBe(100 + 40)
        ->and($current['engagement_rate'])->toBe(round(140 / 1800 * 100, 2))
        ->and($previous['posts'])->toBe(1)
        ->and($previous['reach'])->toBe(300)
        ->and($performance['previous_from'])->toBe('2026-07-01')
        ->and($performance['top_posts'][0]['description'])->toBe('Halloween range reveal')
        ->and(collect($performance['formats'])->pluck('type')->all())->toEqualCanonicalizing(['reel', 'carousel'])
        ->and($performance['platforms'][0]['platform'])->toBe('instagram')
        ->and($performance['platforms'][0]['share'])->toBe(100.0)
        ->and($performance['accounts'][0]['posts'])->toBe(2)
        ->and($performance['trend']['bucket'])->toBe('day')
        ->and($performance['trend']['points'])->toHaveCount(31);
});

it('converts what meta reports from each ad account\'s currency into the parent\'s at the day\'s rate', function () {
    $metaAds         = createTrafficSource($this->shop, TrafficSourcesTypeEnum::META_ADS->value, 'Meta Ads');
    $currency        = $this->organisation->currency;
    $foreignCurrency = Currency::whereNot('id', $currency->id)->where('code', $currency->code === 'EUR' ? 'HUF' : 'EUR')->firstOrFail();

    $metrics = function (string $reference, Currency $sourceCurrency, array $days) use ($metaAds) {
        $campaign = TrafficSourceCampaign::create(['traffic_source_id' => $metaAds->id, 'reference' => $reference.'-'.uniqid(), 'name' => $reference, 'type' => 'campaign']);
        foreach ($days as $date => [$value, $cost]) {
            TrafficSourceCampaignMetric::create([
                'traffic_source_campaign_id' => $campaign->id,
                'date'                       => $date,
                'impressions'                => 100,
                'clicks'                     => 5,
                'conversions'                => 1,
                'source_conversions_value'   => $value,
                'source_cost'                => $cost,
                'source_currency_id'         => $sourceCurrency->id,
            ]);
        }
    };

    $metrics('home', $currency, ['2026-08-10' => [100, 10]]);
    $metrics('abroad', $foreignCurrency, ['2026-08-10' => [400, 8], '2026-08-11' => [600, 12]]);

    GetHistoricCurrencyExchange::shouldRun()->andReturnUsing(fn (Currency $from, Currency $to, Carbon $date) => $date->toDateString() === '2026-08-10' ? 0.5 : 0.25);

    $channel = collect(GetSocialMediaPerformance::run(
        $this->organisation,
        Carbon::parse('2026-08-01')->startOfDay(),
        Carbon::parse('2026-08-31')->endOfDay(),
        ['shop' => $this->shop->slug, 'compare' => 'none']
    )['paid']['channels'])->firstWhere('type', TrafficSourcesTypeEnum::META_ADS->value);

    expect($channel['meta_value'])->toBe(100 + 400 * 0.5 + 600 * 0.25)
        ->and($channel['meta_cost'])->toBe(10 + 8 * 0.5 + 12 * 0.25)
        ->and($channel['meta_roas'])->toBe(round(450 / 17, 2))
        ->and($channel['meta_purchases'])->toBe(3.0);
});

it('takes views, viewers and visits from the accounts\' daily figures over their posts, and says what moved', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());

    $chart = fn (string $title, array $days) => socialCsv('chart.csv', [
        'sep=,',
        '"'.$title.'"',
        '"Date","Primary"',
        ...array_map(fn (string $date, int $value) => '"'.$date.'T00:00:00","'.$value.'"', array_keys($days), $days),
    ]);

    ImportSocialMediaInsights::make()->handle($this->shop, $chart('Views', ['2026-08-10' => 4000, '2026-08-11' => 2000]), SocialMediaPlatformEnum::INSTAGRAM);
    ImportSocialMediaInsights::make()->handle($this->shop, $chart('Viewers', ['2026-08-10' => 900, '2026-08-11' => 300]), SocialMediaPlatformEnum::INSTAGRAM);
    ImportSocialMediaInsights::make()->handle($this->shop, $chart('Instagram profile visits', ['2026-08-10' => 7, '2026-08-11' => 3]));

    $performance = GetSocialMediaPerformance::run(
        $this->organisation,
        Carbon::parse('2026-08-01')->startOfDay(),
        Carbon::parse('2026-08-31')->endOfDay(),
        ['shop' => $this->shop->slug]
    );

    $current = $performance['kpis']['current'];

    expect($current['views'])->toBe(6000)
        ->and($current['avg_daily_viewers'])->toBe(600)
        ->and($current['visits'])->toBe(10)
        ->and($current['interactions'])->toBe(140)
        ->and($current['interaction_rate'])->toBe(round(140 / 6000 * 100, 2))
        ->and($current['reach'])->toBe(1800)
        ->and($performance['platforms'][0]['views'])->toBe(6000)
        ->and($performance['platforms'][0]['views_share'])->toBe(100.0)
        ->and(collect($performance['trend']['points'])->sum('views'))->toBe(6000)
        ->and($performance['accounts'][0]['views'])->toBe(6000)
        ->and($performance['accounts'][0]['interactions'])->toBe(140)
        ->and($performance['insight'])->toContain(__('Reel'));
});

it('compares the period with the same dates last year or with a range chosen by hand', function () {
    $period = fn (array $compare) => GetSocialMediaPerformance::run(
        $this->organisation,
        Carbon::parse('2026-08-01')->startOfDay(),
        Carbon::parse('2026-08-31')->endOfDay(),
        ['shop' => $this->shop->slug, ...$compare]
    );

    $lastYear = $period(['compare' => 'year']);
    $custom   = $period(['compare' => 'custom', 'compare_from' => '2026-06-01', 'compare_to' => '2026-06-15']);
    $broken   = $period(['compare' => 'custom', 'compare_from' => '2026-06-15', 'compare_to' => '2026-06-01']);
    $none     = $period(['compare' => 'none']);

    expect([$lastYear['previous_from'], $lastYear['previous_to']])->toBe(['2025-08-01', '2025-08-31'])
        ->and([$custom['previous_from'], $custom['previous_to']])->toBe(['2026-06-01', '2026-06-15'])
        ->and($custom['filters']['compare'])->toBe('custom')
        ->and([$broken['previous_from'], $broken['previous_to']])->toBe(['2026-07-01', '2026-07-31'])
        ->and($none['previous_from'])->toBeNull()
        ->and($none['kpis']['previous'])->toBeNull()
        ->and($none['previous_trend'])->toBeNull();
});

it('lays the compared period over the current one point for point', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());

    $performance = GetSocialMediaPerformance::run($this->organisation, Carbon::parse('2026-08-01')->startOfDay(), Carbon::parse('2026-08-31')->endOfDay(), ['shop' => $this->shop->slug]);
    $compared    = collect($performance['previous_trend']['points'])->keyBy('date');

    expect($performance['previous_trend']['bucket'])->toBe($performance['trend']['bucket'])
        ->and($compared->keys()->first())->toBe('2026-07-01')
        ->and($compared->count())->toBe(count($performance['trend']['points']))
        ->and($compared['2026-07-20']['posts'])->toBe(1)
        ->and($performance['kpis']['previous']['followers_accounts'])->toBeInt();
});

it('keeps the most viewed post among the top content even when others drew more engagements', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());
    $account = $this->shop->socialMediaAccounts()->sole();

    foreach (range(1, 55) as $number) {
        $account->posts()->create(['external_id' => 'busy-'.$number, 'type' => SocialMediaPostTypeEnum::IMAGE, 'published_at' => '2026-08-15 10:00:00', 'reach' => 50, 'impressions' => 60, 'reactions' => 100]);
    }
    $account->posts()->create(['external_id' => 'seen', 'type' => SocialMediaPostTypeEnum::REEL, 'published_at' => '2026-08-16 10:00:00', 'reach' => 9000, 'impressions' => 20000]);

    $topPosts = collect(GetSocialMediaPerformance::run($this->organisation, Carbon::parse('2026-08-01')->startOfDay(), Carbon::parse('2026-08-31')->endOfDay(), ['shop' => $this->shop->slug])['top_posts']);

    expect($topPosts->sortByDesc('impressions')->first()['impressions'])->toBe(20000)
        ->and($topPosts->sortByDesc('engagements')->first()['engagements'])->toBe(100);
});

it('splits views into organic and ads and keeps days synced before the split apart', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());
    $account = $this->shop->socialMediaAccounts()->sole();
    $account->dailyStats()->create(['date' => '2026-08-05', 'impressions' => 1000, 'ad_impressions' => 900]);
    $account->dailyStats()->create(['date' => '2026-08-06', 'impressions' => 500]);

    $performance = GetSocialMediaPerformance::run($this->organisation, Carbon::parse('2026-08-01')->startOfDay(), Carbon::parse('2026-08-31')->endOfDay(), ['shop' => $this->shop->slug, 'compare' => 'none']);
    $views       = $performance['kpis']['current'];
    $day         = collect($performance['trend']['points'])->firstWhere('date', '2026-08-05');

    expect($views['views'])->toBe(1500)
        ->and($views['organic_views'])->toBe(100)
        ->and($views['ad_views'])->toBe(900)
        ->and($views['unsplit_views'])->toBe(500)
        ->and($day['organic_views'])->toBe(100)
        ->and($day['ad_views'])->toBe(900)
        ->and($performance['accounts'][0]['ad_views'])->toBe(900)
        ->and($performance['platforms'][0]['organic_views'])->toBe(100)
        ->and($performance['insight'])->toContain('Ads brought 60% of views');
});

it('filters by platform and leaves out shops outside the filter', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());

    $from = Carbon::parse('2026-08-01')->startOfDay();
    $to   = Carbon::parse('2026-08-31')->endOfDay();

    expect(GetSocialMediaPerformance::run($this->organisation, $from, $to, ['shop' => $this->shop->slug, 'platform' => 'facebook'])['kpis']['current']['posts'])->toBe(0)
        ->and(GetSocialMediaPerformance::run($this->organisation, $from, $to, ['shop' => 'no-such-shop'])['kpis']['current']['posts'])->toBe(0);
});

it('uploads an export through the shop route', function () {
    $this->post(route('grp.models.shop.social_media.upload', ['shop' => $this->shop->id]), [
        'file' => instagramContentExport(),
    ])->assertCreated();

    expect(Upload::where('parent_type', 'Shop')->where('parent_id', $this->shop->id)->where('model', 'SocialMediaPost')->exists())->toBeTrue()
        ->and($this->shop->socialMediaAccounts()->count())->toBe(1);
});

it('shows the social media dashboard to marketing users', function () {
    $this->get(route('grp.org.marketing.social_media.dashboard', ['organisation' => $this->organisation->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Org/Marketing/SocialMediaDashboard')
            ->where('title', 'Social media')
            ->has('upload_shops')
            ->has('export_route')
            ->where('meta_sync', fn ($shops) => collect($shops)->contains(fn ($shop) => $shop['id'] === $this->shop->id && $shop['organic'] === false && $shop['paid'] === false)));

    $this->get(route('grp.marketing.social_media.dashboard'))->assertOk();
});

it('exports the posts of the period as csv', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());
    $this->user->update(['settings' => array_merge($this->user->settings ?? [], ['selected_interval' => 'all'])]);

    $csv = $this->get(route('grp.org.marketing.social_media.export', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
    ]))->assertOk()->streamedContent();

    expect($csv)->toContain('Halloween range reveal')
        ->and($csv)->toContain('Instagram');
});

it('exports the period as a summary report beside the compared period', function () {
    ImportSocialMediaInsights::make()->handle($this->shop, instagramContentExport());
    $this->user->update(['settings' => array_merge($this->user->settings ?? [], ['selected_interval' => 'all'])]);

    $csv = $this->get(route('grp.org.marketing.social_media.export', [
        'organisation' => $this->organisation->slug,
        'shop'         => $this->shop->slug,
        'report'       => 'summary',
    ]))->assertOk()->streamedContent();

    expect($csv)->toContain(__('Social media report'))
        ->and($csv)->toContain(__('Views'))
        ->and($csv)->toContain(__('Share of views %'))
        ->and($csv)->toContain(__('ROAS · Aiku'))
        ->and($csv)->toContain(__('ROAS · Meta'))
        ->and($csv)->toContain(__('Purchase value · Meta'))
        ->and($csv)->not->toContain("\nROAS,")
        ->and($csv)->toContain('Halloween range reveal');
});

function fakeMetaSocialGraph(array &$requests): void
{
    Http::fake(function (Illuminate\Http\Client\Request $request) use (&$requests) {
        $requests[] = $request->url();
        $path       = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match ($path) {
            '/v21.0/111' => Http::response(['id' => '111', 'name' => 'AW Gifts Europe', 'followers_count' => 5000, 'access_token' => 'page-token']),
            '/v21.0/ad-1/insights' => Http::response(['data' => [['impressions' => '800', 'reach' => '600', 'inline_link_clicks' => '12']]]),
            '/v21.0/111/posts' => ($query['fields'] ?? '') === 'id,promotion_status'
                ? Http::response(['data' => [['id' => '111_1', 'promotion_status' => 'active'], ['id' => '111_2', 'promotion_status' => 'inactive']]])
                : Http::response(isset($query['after'])
                ? ['data' => [[
                    'id'            => '111_2',
                    'message'       => 'Brand spotlight',
                    'created_time'  => '2026-08-03T09:00:00+0000',
                    'permalink_url' => 'https://www.facebook.com/111/posts/2',
                    'attachments'   => ['data' => [['media_type' => 'album']]],
                    'reactions'     => ['summary' => ['total_count' => 5]],
                    'comments'      => ['summary' => ['total_count' => 1]],
                ]]]
                : [
                    'data'   => [[
                        'id'            => '111_1',
                        'message'       => 'Halloween range reveal',
                        'created_time'  => '2026-08-02T10:00:00+0000',
                        'permalink_url' => 'https://www.facebook.com/reel/123',
                        'attachments'   => ['data' => [['media_type' => 'video']]],
                        'shares'        => ['count' => 7],
                        'reactions'     => ['summary' => ['total_count' => 40]],
                        'comments'      => ['summary' => ['total_count' => 3]],
                        'insights'      => ['data' => [
                            ['name' => 'post_total_media_view_unique', 'period' => 'lifetime', 'values' => [['value' => 1200]]],
                            ['name' => 'post_media_view', 'period' => 'lifetime', 'values' => [['value' => 2000]]],
                            ['name' => 'post_total_media_view_unique', 'period' => 'day', 'values' => [['value' => 0, 'end_time' => '2026-08-30T07:00:00+0000'], ['value' => 0, 'end_time' => '2026-08-31T07:00:00+0000']]],
                            ...(str_contains($query['fields'] ?? '', 'post_clicks_by_type')
                                ? [['name' => 'post_clicks_by_type', 'values' => [['value' => ['link clicks' => 15, 'other clicks' => 4]]]]]
                                : []),
                            ...(str_contains($query['fields'] ?? '', 'post_reactions_by_type_total')
                                ? [['name' => 'post_reactions_by_type_total', 'values' => [['value' => ['like' => 30, 'love' => 10]]]]]
                                : []),
                        ]],
                    ]],
                    'paging' => ['next' => 'https://graph.facebook.com/v21.0/111/posts?after=cursor2&access_token=page-token'],
                ]),
            '/v21.0/111/insights' => ($query['breakdown'] ?? null) === 'is_from_ads'
                ? Http::response(['data' => [['name' => 'page_media_view', 'period' => 'day', 'values' => [
                    ['value' => 900, 'end_time' => '2026-08-02T07:00:00+0000', 'is_from_ads' => '0'],
                    ['value' => 100, 'end_time' => '2026-08-02T07:00:00+0000', 'is_from_ads' => '1'],
                ]]]])
                : (str_contains($query['metric'] ?? '', 'page_views_total')
                ? Http::response(['error' => ['message' => '(#100) The value must be a valid insights metric']], 400)
                : Http::response(['data' => collect([
                    ['name' => 'page_total_media_view_unique', 'values' => [['value' => 300, 'end_time' => '2026-08-02T07:00:00+0000']]],
                    ['name' => 'page_daily_follows_unique', 'values' => [['value' => 4, 'end_time' => '2026-08-02T07:00:00+0000']]],
                    ['name' => 'page_media_view', 'values' => [['value' => 1000, 'end_time' => '2026-08-02T07:00:00+0000']]],
                    ['name' => 'page_post_engagements', 'values' => [['value' => 25, 'end_time' => '2026-08-02T07:00:00+0000']]],
                ])->whereIn('name', explode(',', $query['metric'] ?? ''))->values()->all(), 'paging' => ['next' => 'https://graph.facebook.com/v21.0/111/insights?since=later']])),
            '/v21.0/222' => Http::response(['id' => '222', 'username' => 'awgifts.eu', 'name' => 'AW Gifts Europe', 'followers_count' => 2000]),
            '/v21.0/222/media' => Http::response(['data' => [[
                'id'                 => '1801',
                'caption'            => 'Autumn fragrance',
                'media_type'         => 'CAROUSEL_ALBUM',
                'media_product_type' => 'FEED',
                'permalink'          => 'https://www.instagram.com/p/AAA/',
                'timestamp'          => '2026-08-05T10:00:00+0000',
                'like_count'         => 30,
                'comments_count'     => 2,
                'boost_ads_list'     => ['data' => [['ad_id' => 'ad-1', 'ad_status' => 'active']]],
                'insights'           => ['data' => [
                    ['name' => 'reach', 'values' => [['value' => 900]]],
                    ['name' => 'views', 'values' => [['value' => 1500]]],
                    ['name' => 'saved', 'values' => [['value' => 12]]],
                    ['name' => 'shares', 'values' => [['value' => 3]]],
                ]],
            ]]]),
            '/v21.0/222/insights' => match (true) {
                ($query['metric_type'] ?? null) !== 'total_value' => Http::response(['data' => collect([
                    ['name' => 'reach', 'values' => [['value' => 650, 'end_time' => '2026-08-05T07:00:00+0000']]],
                    ['name' => 'follower_count', 'values' => [['value' => 9, 'end_time' => '2026-08-05T07:00:00+0000']]],
                ])->whereIn('name', explode(',', $query['metric'] ?? ''))->values()->all()]),
                ($query['breakdown'] ?? null) === 'media_product_type' => Http::response(['data' => [[
                    'name'        => 'views',
                    'period'      => 'day',
                    'total_value' => ['value' => 700, 'breakdowns' => [['dimension_keys' => ['media_product_type'], 'results' => [
                        ['dimension_values' => ['REEL'], 'value' => 500],
                        ['dimension_values' => ['AD'], 'value' => 200],
                    ]]]],
                ]]]),
                ($query['metric'] ?? null) === 'follows_and_unfollows' => Http::response(['data' => [[
                    'name'        => 'follows_and_unfollows',
                    'period'      => 'day',
                    'total_value' => ['breakdowns' => [['dimension_keys' => ['follow_type'], 'results' => [
                        ['dimension_values' => ['FOLLOWER'], 'value' => 5],
                        ['dimension_values' => ['NON_FOLLOWER'], 'value' => 2],
                    ]]]],
                ]]]),
                str_contains($query['metric'] ?? '', 'website_clicks') => Http::response(['error' => ['message' => '(#100) metric[0] must be one of the following values']], 400),
                default => Http::response(['data' => collect(['views' => 700, 'total_interactions' => 45, 'profile_views' => 12])
                    ->only(explode(',', $query['metric'] ?? ''))
                    ->map(fn (int $value, string $name) => ['name' => $name, 'period' => 'day', 'total_value' => ['value' => $value]])
                    ->values()->all()]),
            },
            '/v21.0/222/stories' => Http::response(['data' => [[
                'id'                 => '1901',
                'media_type'         => 'IMAGE',
                'media_product_type' => 'STORY',
                'permalink'          => 'https://www.instagram.com/stories/awgifts.eu/1901/',
                'timestamp'          => '2026-08-30T18:00:00+0000',
                'insights'           => ['data' => [
                    ['name' => 'reach', 'values' => [['value' => 400]]],
                    ['name' => 'views', 'values' => [['value' => 520]]],
                ]],
            ]]]),
            default => Http::response(['error' => ['message' => 'unexpected '.$path]], 404),
        };
    });
}

describe('fetching meta organic insights', function () {
    beforeEach(function () {
        $settings = $this->shop->settings ?? [];
        data_set($settings, 'meta_social', ['facebook_page_id' => '111', 'instagram_account_id' => '222']);
        $this->shop->update(['settings' => $settings]);

        config()->set('services.meta_ads.access_token', 'system-user-token');
        config()->set('services.meta_ads.api_version', 'v21.0');
    });

    it('stores facebook page posts, every page of them, with the page token', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);

        expect(Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]))->toBe(0);

        $page = $this->shop->socialMediaAccounts()->where('platform', 'facebook')->sole();
        $reel = $page->posts()->where('external_id', '1')->sole();

        expect($page->external_id)->toBe('111')
            ->and($page->name)->toBe('AW Gifts Europe')
            ->and($page->followers)->toBe(5000)
            ->and($page->posts()->count())->toBe(2)
            ->and($reel->type)->toBe(SocialMediaPostTypeEnum::REEL)
            ->and($reel->reach)->toBe(1200)
            ->and($reel->impressions)->toBe(2000)
            ->and($reel->reactions)->toBe(40)
            ->and($reel->shares)->toBe(7)
            ->and($page->posts()->where('external_id', '2')->value('type'))->toBe(SocialMediaPostTypeEnum::CAROUSEL)
            ->and($reel->is_boosted)->toBeTrue()
            ->and($page->posts()->where('external_id', '2')->value('is_boosted'))->toBeFalse()
            ->and(collect($requests)->filter(fn ($url) => str_contains($url, '/111/posts'))->every(fn ($url) => str_contains($url, 'access_token=page-token')))->toBeTrue()
            ->and(collect($requests)->filter(fn ($url) => str_contains($url, '/111/insights') && !str_contains($url, 'breakdown='))->count())->toBe(count(FetchMetaSocialInsights::FACEBOOK_PAGE_METRICS));

        $day = $page->dailyStats()->where('date', '2026-08-01')->sole();

        expect($day->reach)->toBe(300)
            ->and($day->new_follows)->toBe(4)
            ->and($day->impressions)->toBe(1000)
            ->and($day->ad_impressions)->toBe(100);
    });

    it('stores instagram media and account days, and keeps a re-run to one row each', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);

        Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]);
        Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]);

        $instagram = $this->shop->socialMediaAccounts()->where('platform', 'instagram')->sole();
        $media     = $instagram->posts()->where('external_id', '1801')->sole();

        expect($instagram->username)->toBe('awgifts.eu')
            ->and($instagram->followers)->toBe(2000)
            ->and($media->type)->toBe(SocialMediaPostTypeEnum::CAROUSEL)
            ->and($media->reach)->toBe(900)
            ->and($media->impressions)->toBe(1500)
            ->and($media->saves)->toBe(12)
            ->and($media->reactions)->toBe(30)
            ->and($media->is_boosted)->toBeTrue()
            ->and($media->ad_views)->toBe(800)
            ->and($media->ad_link_clicks)->toBe(12)
            ->and($instagram->dailyStats()->where('date', '2026-08-04')->sole()->reach)->toBe(650)
            ->and($instagram->dailyStats()->where('date', '2026-08-04')->sole()->new_follows)->toBe(9)
            ->and($instagram->dailyStats()->where('date', '2026-08-04')->sole()->impressions)->toBe(700)
            ->and($instagram->dailyStats()->where('date', '2026-08-04')->sole()->ad_impressions)->toBe(200);
    });

    it('works instagram followers of past days back from today through each day\'s follows and unfollows', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);
        $this->travelTo(Carbon::parse('2026-09-01 10:00'));

        Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]);

        $instagram = $this->shop->socialMediaAccounts()->where('platform', 'instagram')->sole();
        $followers = $instagram->dailyStats()->whereNotNull('followers')->pluck('followers', 'date')->mapWithKeys(fn ($value, $date) => [Carbon::parse($date)->toDateString() => $value]);

        expect($followers['2026-09-01'])->toBe(2000)
            ->and($followers['2026-08-31'])->toBe(2000)
            ->and($followers['2026-08-30'])->toBe(1997)
            ->and($followers['2026-08-02'])->toBe(1913)
            ->and($followers->has('2026-08-01'))->toBeFalse();
    });

    it('fills the account a csv upload created instead of adding a second one', function () {
        ImportSocialMediaInsights::make()->handle($this->shop, socialCsv('instagram-followers.csv', [
            'Date,Instagram followers',
            '2026-07-31,1900',
        ]));

        $requests = [];
        fakeMetaSocialGraph($requests);

        Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]);

        expect($this->shop->socialMediaAccounts()->where('platform', 'instagram')->sole()->external_id)->toBe('222');
    });

    it('reads the daily views, interactions and visits, stories and post link clicks, and skips the names meta refuses', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);

        $exitCode = Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]);
        $output   = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('page_views_total')
            ->and($output)->toContain('website_clicks');

        $page      = $this->shop->socialMediaAccounts()->where('platform', 'facebook')->sole();
        $pageDay   = $page->dailyStats()->where('date', '2026-08-01')->sole();
        $instagram = $this->shop->socialMediaAccounts()->where('platform', 'instagram')->sole();
        $igDay     = $instagram->dailyStats()->where('date', '2026-08-04')->sole();
        $story     = $instagram->posts()->where('external_id', '1901')->sole();

        expect($pageDay->impressions)->toBe(1000)
            ->and($pageDay->engagements)->toBeNull()
            ->and($pageDay->profile_visits)->toBeNull()
            ->and($page->posts()->where('external_id', '1')->value('link_clicks'))->toBe(15)
            ->and($igDay->impressions)->toBe(700)
            ->and($igDay->engagements)->toBe(45)
            ->and($igDay->profile_visits)->toBe(12)
            ->and($igDay->link_clicks)->toBeNull()
            ->and($story->type)->toBe(SocialMediaPostTypeEnum::STORY)
            ->and($story->reach)->toBe(400)
            ->and($story->impressions)->toBe(520);
    });

    it('still stores facebook posts and their reactions when the token may not read comments', function () {
        Http::fake(fn (Illuminate\Http\Client\Request $request) => str_contains($request->url(), '/111/posts') && str_contains(urldecode($request->url()), 'comments.summary')
            ? Http::response(['error' => ['message' => "(#10) This endpoint requires the 'pages_read_user_content' permission"]], 400)
            : null);
        $requests = [];
        fakeMetaSocialGraph($requests);

        expect(Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]))->toBe(0)
            ->and(Artisan::output())->toContain('pages_read_user_content');

        $reel = $this->shop->socialMediaAccounts()->where('platform', 'facebook')->sole()->posts()->where('external_id', '1')->sole();

        expect($reel->reach)->toBe(1200)
            ->and($reel->link_clicks)->toBe(15)
            ->and($reel->shares)->toBe(7)
            ->and($reel->reactions)->toBe(40)
            ->and(collect($requests)->filter(fn (string $url) => str_contains($url, '/111/posts') && str_contains($url, 'limit=50'))->every(fn (string $url) => !str_contains(urldecode($url), 'comments.summary')))->toBeTrue();
    });

    it('syncs a brand from meta on request and remembers how it went', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]))->assertOk();

        $status = SyncShopFromMeta::status($this->shop->refresh());

        expect($this->shop->socialMediaAccounts()->count())->toBe(2)
            ->and($status['organic'])->toBeTrue()
            ->and($status['paid'])->toBeFalse()
            ->and($status['running'])->toBeFalse()
            ->and($status['last_sync']['ok'])->toBeTrue()
            ->and($status['available_at'])->not->toBeNull()
            ->and($status['organic_updated_at'])->not->toBeNull();

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['shop' => 'Try again in 10 minutes']);

        $this->travel(SyncShopFromMeta::COOLDOWN_MINS + 1)->minutes();

        expect(SyncShopFromMeta::status($this->shop)['available_at'])->toBeNull();

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]))->assertOk();
    });

    it('asks meta for a page\'s days in windows it accepts and syncs a new brand\'s whole history first', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]), ['days' => 45])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('days');

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]))->assertOk();

        parse_str((string) parse_url(collect($requests)->first(fn (string $url) => str_contains($url, '/111/insights')), PHP_URL_QUERY), $firstWindow);

        expect(Carbon::createFromTimestamp($firstWindow['since'], config('app.timezone'))->toDateString())->toBe(now()->subDay()->subDays(SyncShopFromMeta::MAX_DAYS - 1)->toDateString());

        $requests = [];
        Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 120]);

        expect(collect($requests)->filter(fn (string $url) => str_contains($url, '/111/insights') && str_contains($url, 'metric=page_media_view&') && !str_contains($url, 'breakdown='))->count())->toBe(2);
    });

    it('stops a run before meta\'s usage limit instead of pushing on into a block', function () {
        Http::fake(['graph.facebook.com/*' => Http::response(
            ['id' => '111', 'name' => 'AW Gifts Europe', 'followers_count' => 5000, 'access_token' => 'page-token'],
            200,
            ['X-Business-Use-Case-Usage' => json_encode(['111' => [['type' => 'pages', 'call_count' => 85, 'total_cputime' => 10, 'total_time' => 10, 'estimated_time_to_regain_access' => 12]]])]
        )]);

        expect(Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31]))->toBe(1)
            ->and(Artisan::output())->toContain('usage limit')
            ->and($this->shop->socialMediaAccounts()->count())->toBe(0);

        Http::assertSentCount(1);
    });

    it('says which meta limit stopped a part and syncs that part again by itself once', function () {
        Queue::fake();
        Http::fake(['graph.facebook.com/*' => Http::response(
            ['id' => '111', 'name' => 'AW Gifts Europe', 'followers_count' => 5000, 'access_token' => 'page-token'],
            200,
            ['X-App-Usage' => json_encode(['call_count' => 85, 'total_cputime' => 5, 'total_time' => 5])]
        )]);

        $result = SyncShopFromMeta::make()->handle($this->shop);

        expect($result['ok'])->toBeFalse()
            ->and($result['message'])->toContain('app calls 85%')
            ->and($result['message'])->toContain('sync again by itself')
            ->and($result['message'])->not->toContain('post(s)');

        SyncShopFromMeta::assertPushed(1);

        SyncShopFromMeta::make()->handle($this->shop, null, ['organic'], true);

        SyncShopFromMeta::assertPushed(1);
    });

    it('lets someone who may only see a brand\'s marketing sync it, without offering its settings', function () {
        $requests = [];
        fakeMetaSocialGraph($requests);

        setPermissionsTeamId($this->organisation->group_id);
        $viewer = User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);
        $viewer->assignRole(RolesEnum::getRoleName(RolesEnum::SHOPKEEPER_CLERK->value, $this->shop));
        actingAs($viewer);

        expect($viewer->authTo("marketing.{$this->shop->id}.view"))->toBeTrue()
            ->and($viewer->authTo("marketing.{$this->shop->id}.edit"))->toBeFalse();

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]))->assertOk();

        $this->get(route('grp.org.marketing.social_media.dashboard', ['organisation' => $this->organisation->slug]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('meta_sync', fn ($brands) => collect($brands)->firstWhere('id', $this->shop->id)['can_edit'] === false)
                ->where('upload_shops', fn ($brands) => collect($brands)->doesntContain('id', $this->shop->id)));
    });

    it('says a brand is not set up for meta rather than syncing it', function () {
        $settings = $this->shop->settings;
        data_forget($settings, 'meta_social');
        $this->shop->update(['settings' => $settings]);

        $this->postJson(route('grp.models.shop.social_media.sync', ['shop' => $this->shop->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shop');

        expect($this->shop->socialMediaAccounts()->count())->toBe(0);
    });

    it('reports a meta error as a failure and writes nothing on a dry run', function () {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#100) The value must be a valid insights metric']], 400)]);

        expect(Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug]))->toBe(1)
            ->and($this->shop->socialMediaAccounts()->count())->toBe(0);

        $requests = [];
        fakeMetaSocialGraph($requests);

        Artisan::call('social-media:fetch-meta', ['shop' => $this->shop->slug, '--date' => '2026-08-31', '--days' => 31, '--dry-run' => true]);

        expect($this->shop->socialMediaAccounts()->count())->toBe(0);
    });
});
