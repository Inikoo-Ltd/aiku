<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 03 Aug 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Website\Analytics\TrackWebsiteVisitorActivity;
use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Lorisleiva\Actions\Decorators\JobDecorator;

use function Pest\Laravel\post;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->website = Website::factory()->create([
        'organisation_id' => $this->organisation->id,
        'shop_id'         => $this->shop->id,
        'group_id'        => $this->organisation->group_id,
        'type'            => WebsiteTypeEnum::B2C,
        'status'          => true,
    ]);
});

it('queues visitor tracking instead of doing it inside the request', function () {
    Queue::fake();

    $response = post('https://'.$this->website->domain.'/analytics/hit', [
        'analytics_app'        => 'iris',
        'analytics_webpage'    => 'home',
        'analytics_page_title' => 'Home Page',
    ], [
        'referer'      => 'https://www.google.com/search?q=test',
        'CF-IPCountry' => 'GB',
        'CF-IPCity'    => 'Sheffield',
        'CF-Region'    => 'England',
        'User-Agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ]);

    $response->assertStatus(200);

    Queue::assertPushed(JobDecorator::class, function (JobDecorator $job) {
        if (!$job->decorates(TrackWebsiteVisitorActivity::class)) {
            return false;
        }

        [$website, $sessionId, $hit] = $job->getParameters();

        return $website->id === $this->website->id
            && $sessionId !== ''
            && $hit['country'] === 'GB'
            && $hit['city'] === 'Sheffield'
            && $hit['region'] === 'England'
            && $hit['page_title'] === 'Home Page'
            && $hit['web_user_id'] === null
            && str_contains($hit['user_agent'], 'Chrome');
    });
});

it('does not track visitors when the live visitors switch is off', function () {
    config()->set('iris.analytics.live_visitors', false);
    Queue::fake();

    post('https://'.$this->website->domain.'/analytics/hit', [
        'analytics_app'     => 'iris',
        'analytics_webpage' => 'home',
    ], [
        'referer'    => 'https://www.google.com/search?q=test',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0',
    ])->assertStatus(200);

    Queue::assertNotPushed(JobDecorator::class, fn (JobDecorator $job) => $job->decorates(TrackWebsiteVisitorActivity::class));
});

it('stores the web vitals a visitor browser measured, dropping a webpage of another website', function () {
    post('https://'.$this->website->domain.'/analytics/web-vitals', [
        'webpage_id' => 999999999,
        'device'     => 'phone',
        'lcp'        => 2345,
        'cls'        => 0.123,
        'ttfb'       => 410,
    ])->assertOk();

    $sample = DB::table('web_vital_samples')->where('website_id', $this->website->id)->sole();

    expect($sample->device)->toBe('phone')
        ->and($sample->webpage_id)->toBeNull()
        ->and($sample->lcp)->toBe(2345)
        ->and((float)$sample->cls)->toBe(0.123)
        ->and($sample->inp)->toBeNull();

    post('https://'.$this->website->domain.'/analytics/web-vitals', ['device' => 'desktop'])->assertOk();
    post('https://'.$this->website->domain.'/analytics/web-vitals', ['device' => 'tablet', 'lcp' => 100], ['Accept' => 'application/json'])->assertUnprocessable();

    expect(DB::table('web_vital_samples')->where('website_id', $this->website->id)->count())->toBe(1);
});
