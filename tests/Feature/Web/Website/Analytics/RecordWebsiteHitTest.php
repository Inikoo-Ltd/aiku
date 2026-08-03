<?php

use App\Enums\Web\Website\WebsiteTypeEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Redis;

use function Pest\Laravel\post;

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

it('records website hit with metadata', function () {
    Redis::shouldReceive('zrem')->twice();
    Redis::shouldReceive('zadd')->once();
    Redis::shouldReceive('hmset')->once();
    Redis::shouldReceive('expire')->once();
    Redis::shouldReceive('zremrangebyscore')->twice();
    Redis::shouldReceive('zcard')->twice()->andReturn(0);
    Redis::shouldReceive('hgetall')->andReturn([]);

    $response = post('https://' . $this->website->domain . '/analytics/hit', [
        'analytics_app' => 'iris',
        'analytics_webpage' => 'home',
        'analytics_page_title' => 'Home Page',
    ], [
        'referer' => 'https://www.google.com/search?q=test',
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    ]);

    $response->assertStatus(200);
});
