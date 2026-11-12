<?php

use App\Actions\Iris\Blog\IndexIrisBlogs;
use App\Actions\Web\Webpage\Json\DiffWebpageSnapshotText;
use App\Actions\Web\Webpage\Json\GetWebpageSnapshotsComparison;
use App\Actions\Web\Webpage\UI\IndexBlogWebpages;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Actions\Web\Webpage\ProcessUpdateWebpageUrl;
use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Redirect;
use Illuminate\Support\Str;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Http\Resources\Web\WebpagesResource;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    [, $this->user, $this->shop] = createShop();
    $this->website      = createWebsite($this->shop);
});

function createBlogWebpage(Website $website, string $title, array $attributes): Webpage
{
    $webpage = StoreWebpage::make()->action($website, array_merge(
        Webpage::factory()->definition(),
        [
            'title'    => $title,
            'type'     => WebpageTypeEnum::BLOG->value,
            'sub_type' => WebpageSubTypeEnum::BLOG->value,
        ]
    ));

    $webpage->updateQuietly(array_merge(['state' => WebpageStateEnum::LIVE], $attributes));

    return $webpage->refresh();
}

function publishedLayoutWithDate(?string $publishedDate): array
{
    return [
        'web_blocks' => [
            [
                'web_block' => [
                    'layout' => [
                        'data' => [
                            'fieldValue' => [
                                'published_date' => $publishedDate,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

test('iris blogs ascending sort lists the oldest published layout date first', function () {
    $oldest = createBlogWebpage($this->website, 'Oldest published blog', [
        'live_at'          => '2026-03-01 10:00:00',
        'published_layout' => publishedLayoutWithDate('2012-08-31T14:01:00.004Z'),
    ]);

    $newest = createBlogWebpage($this->website, 'Newest published blog', [
        'live_at'          => '2026-01-01 10:00:00',
        'published_layout' => publishedLayoutWithDate('2025-01-10T15:00:00.004Z'),
    ]);

    $middle = createBlogWebpage($this->website, 'Middle published blog', [
        'live_at'          => '2026-02-01 10:00:00',
        'published_layout' => publishedLayoutWithDate('2021-09-17T12:09:00.001Z'),
    ]);

    request()->query->set('sort', 'last_published_at');
    $ascending = IndexIrisBlogs::make()->handle($this->website)->pluck('id')->all();

    request()->query->set('sort', '-last_published_at');
    $descending = IndexIrisBlogs::make()->handle($this->website)->pluck('id')->all();

    expect($ascending)->toBe([$oldest->id, $middle->id, $newest->id])
        ->and($descending)->toBe([$newest->id, $middle->id, $oldest->id]);
});

test('iris blogs without a published date in the layout fall back to the webpage dates', function () {
    $withLayoutDate = createBlogWebpage($this->website, 'Blog with layout date', [
        'live_at'          => '2026-01-01 10:00:00',
        'published_layout' => publishedLayoutWithDate('2026-05-05T10:00:00.000Z'),
    ]);

    $withoutLayoutDate = createBlogWebpage($this->website, 'Blog without layout date', [
        'live_at'           => '2026-01-02 10:00:00',
        'last_published_at' => '2026-06-06 10:00:00',
        'published_layout'  => publishedLayoutWithDate(null),
    ]);

    request()->query->set('sort', 'last_published_at');

    expect(IndexIrisBlogs::make()->handle($this->website)->pluck('id')->all())
        ->toBe([$withLayoutDate->id, $withoutLayoutDate->id]);
});

test('renaming a blog url refreshes its canonical url and only redirects the old url once the blog is live', function () {
    $draft = createBlogWebpage($this->website, 'Draft blog', ['state' => WebpageStateEnum::IN_PROCESS, 'url' => 'draft-blog-renamed']);

    ProcessUpdateWebpageUrl::run($draft, 'draft-blog-old');

    expect($draft->refresh()->canonical_url)->toEndWith('/draft-blog-renamed')
        ->and(Redirect::where('website_id', $this->website->id)->where('from_path', 'draft-blog-old')->exists())->toBeFalse();

    $live = createBlogWebpage($this->website, 'Live blog', ['url' => 'live-blog-renamed']);

    ProcessUpdateWebpageUrl::run($live, 'live-blog-old');

    expect($live->refresh()->canonical_url)->toEndWith('/live-blog-renamed')
        ->and(Redirect::where('website_id', $this->website->id)->where('from_path', 'live-blog-old')->value('to_webpage_id'))->toBe($live->id);
});

test('a blog url changed only by its slashes, or renamed back to an old url that already redirects, is not redirected again', function () {
    $slug = 'slash-blog-'.Str::lower(Str::random(6));
    $live = createBlogWebpage($this->website, 'Slash blog', ['url' => $slug]);

    ProcessUpdateWebpageUrl::run($live, '/'.$slug);

    expect(Redirect::where('website_id', $this->website->id)->where('from_path', $slug)->exists())->toBeFalse();

    $live->update(['url' => $slug.'-renamed']);
    ProcessUpdateWebpageUrl::run($live, $slug);
    $live->update(['url' => $slug.'-again']);
    ProcessUpdateWebpageUrl::run($live, '/'.$slug.'/');

    expect(Redirect::where('website_id', $this->website->id)->where('from_path', $slug)->count())->toBe(1);
});

test('back office blog list shows the published date the website shows', function () {
    $published = createBlogWebpage($this->website, 'Published blog', [
        'live_at'          => '2026-01-01 10:00:00',
        'published_layout' => publishedLayoutWithDate('2012-08-31T14:01:00.000Z'),
    ]);

    $draft = createBlogWebpage($this->website, 'Draft blog', ['state' => WebpageStateEnum::IN_PROCESS]);

    $rows = collect(WebpagesResource::collection(IndexBlogWebpages::make()->handle($this->website, bucket: 'blog'))->resolve())->keyBy('id');

    expect($rows[$published->id]['published_at'])->toBe('2012-08-31T14:01:00+00:00')
        ->and($rows[$draft->id]['published_at'])->toBeNull()
        ->and($rows[$draft->id]['last_edited_by'])->toBeNull();
});

test('a content change by a user records them as the last editor, a change made without a user clears it', function () {
    $draft = createBlogWebpage($this->website, 'Draft edited blog', ['state' => WebpageStateEnum::IN_PROCESS]);
    $draft->unpublishedSnapshot->updateQuietly(['checksum' => 'content before the edit']);

    actingAs($this->user);
    UpdateWebpageContent::run($draft->refresh());

    expect($draft->unpublishedSnapshot->refresh()->publisher_id)->toBe($this->user->id)
        ->and($draft->unpublishedSnapshot->publisher_type)->toBe('User');

    $row = collect(WebpagesResource::collection(IndexBlogWebpages::make()->handle($this->website, bucket: 'blog'))->resolve())->firstWhere('id', $draft->id);

    expect($row['last_edited_by'])->toBe($this->user->contact_name);

    UpdateWebpageContent::run($draft->refresh());

    expect($draft->unpublishedSnapshot->refresh()->publisher_id)->toBe($this->user->id);

    auth()->logout();
    $draft->unpublishedSnapshot->updateQuietly(['checksum' => 'content before a repair']);
    UpdateWebpageContent::run($draft->refresh());

    expect($draft->unpublishedSnapshot->refresh()->publisher_id)->toBeNull();
});

test('publishing a blog bans the blog dashboard and its category listing so the new blog shows there', function () {
    config(['iris.cache.varnish' => true, 'iris.cache.varnish_hosts' => ['http://varnish.test']]);
    Http::fake();
    Queue::fake();

    $blog = createBlogWebpage($this->website, 'Freshly published guide', [
        'state'    => WebpageStateEnum::IN_PROCESS,
        'sub_type' => WebpageSubTypeEnum::BUSINESS_TIPS,
    ]);

    PublishWebpage::make()->action($blog, ['comment' => 'first publish'], strict: false);

    $bannedUrls = collect(Http::recorded())
        ->map(fn (array $exchange) => $exchange[0])
        ->filter(fn (Request $request) => $request->method() === 'BAN' && $request->hasHeader('x-ban-url'))
        ->map(fn (Request $request) => $request->header('x-ban-url')[0])
        ->values()
        ->all();

    expect($blog->refresh()->state)->toBe(WebpageStateEnum::LIVE)
        ->and($bannedUrls)->toContain('/blog')
        ->and($bannedUrls)->toContain(WebpageSubTypeEnum::BUSINESS_TIPS->blogCategoryUrl());
});

function blogArticleLayout(string $title, string $content, string $image, array $extraBlocks = []): array
{
    return [
        'web_blocks' => array_merge([
            [
                'id'        => 9001,
                'name'      => 'Blog',
                'type'      => 'blog',
                'show'      => true,
                'web_block' => [
                    'layout' => [
                        'data' => [
                            'fieldValue' => [
                                'title'   => $title,
                                'content' => $content,
                                'image'   => ['source' => ['original' => $image, 'webp' => $image.'.webp']],
                            ],
                        ],
                    ],
                ],
            ],
        ], $extraBlocks),
    ];
}

function publishBlogLayout(Webpage $blog, array $layout, string $comment): Webpage
{
    $blog->refresh()->unpublishedSnapshot->updateQuietly(['layout' => $layout, 'checksum' => md5(json_encode($layout))]);

    return PublishWebpage::make()->action($blog->refresh(), ['comment' => $comment], strict: false);
}

test('text comparison marks only the changed words of an edited paragraph and keeps unchanged paragraphs', function () {
    $rows = DiffWebpageSnapshotText::run(
        "Candles for the home\nBuy wholesale scented candles today\nThe end",
        "Candles for the home\nBuy wholesale soy candles today\nNew closing line\nThe end"
    );

    expect(array_column($rows, 'type'))->toBe(['equal', 'changed', 'added', 'equal'])
        ->and(collect($rows[1]['segments'])->where('type', 'removed')->pluck('text')->implode(''))->toBe('scented')
        ->and(collect($rows[1]['segments'])->where('type', 'added')->pluck('text')->implode(''))->toBe('soy')
        ->and($rows[2]['segments'])->toBe([['type' => 'added', 'text' => 'New closing line']]);
});

test('comparing a published blog version with the one before shows the changed fields, images and added blocks', function () {
    config(['iris.cache.varnish' => false]);
    Queue::fake();

    $blog = createBlogWebpage($this->website, 'Blog with edit history', ['state' => WebpageStateEnum::IN_PROCESS]);

    publishBlogLayout($blog, blogArticleLayout(
        '<p>Scented candles</p>',
        '<p>Our candles burn for <strong>40 hours</strong>.</p><p>Read the <a href="https://example.com/old">guide</a>.</p>',
        'https://media.test/first.png'
    ), 'first');
    $firstSnapshot = $blog->refresh()->liveSnapshot;

    publishBlogLayout($blog, blogArticleLayout(
        '<p>Scented candles</p>',
        '<p>Our candles burn for <strong>50 hours</strong>.</p><p>Read the <a href="https://example.com/new">guide</a>.</p><p><img src="https://media.test/inline.png" alt="Candle on a table"></p>',
        'https://media.test/second.png',
        [['id' => 9002, 'name' => 'Text', 'type' => 'text', 'show' => true, 'web_block' => ['layout' => ['data' => ['fieldValue' => ['value' => '<p>Free delivery</p>']]]]]]
    ), 'second');
    $secondSnapshot = $blog->refresh()->liveSnapshot;

    $comparison = GetWebpageSnapshotsComparison::make()->handle($blog, $secondSnapshot);

    expect($comparison['from']['id'])->toBe($firstSnapshot->id)
        ->and($comparison['to']['id'])->toBe($secondSnapshot->id)
        ->and($comparison['to']['comment'])->toBe('second')
        ->and(array_column($comparison['options'], 'id'))->toContain($firstSnapshot->id, $secondSnapshot->id);

    $blocks = collect($comparison['blocks'])->keyBy('key');
    $fields = collect($blocks['9001']['fields'])->keyBy('field');

    expect($blocks['9001']['status'])->toBe('changed')
        ->and($fields->keys()->all())->toEqualCanonicalizing(['content', 'image'])
        ->and($fields['image']['kind'])->toBe('image')
        ->and($fields['image']['before'])->toBe('https://media.test/first.png')
        ->and($fields['image']['after'])->toBe('https://media.test/second.png')
        ->and($fields['content']['kind'])->toBe('html')
        ->and($fields['content']['after'])->toContain('guide ⟨https://example.com/new⟩', '[Image: Candle on a table]')
        ->and(collect($fields['content']['rows'])->firstWhere('type', 'changed')['segments'])->toContain(['type' => 'removed', 'text' => '40'], ['type' => 'added', 'text' => '50'])
        ->and($blocks['9002']['status'])->toBe('added')
        ->and($blocks['9002']['fields'][1]['after'])->toBe('Free delivery');

    $firstVersion = GetWebpageSnapshotsComparison::make()->handle($blog, $firstSnapshot);

    expect($firstVersion['from'])->toBeNull()
        ->and($firstVersion['blocks'][0]['status'])->toBe('added');
});

test('the snapshots comparison endpoint only compares snapshots of the requested webpage', function () {
    config(['iris.cache.varnish' => false]);
    Queue::fake();

    $blog = createBlogWebpage($this->website, 'Blog compared over http', ['state' => WebpageStateEnum::IN_PROCESS]);
    publishBlogLayout($blog, blogArticleLayout('<p>One</p>', '<p>Body</p>', 'https://media.test/a.png'), 'one');
    publishBlogLayout($blog, blogArticleLayout('<p>Two</p>', '<p>Body</p>', 'https://media.test/a.png'), 'two');

    $otherBlog = createBlogWebpage($this->website, 'Another blog', ['state' => WebpageStateEnum::IN_PROCESS]);
    publishBlogLayout($otherBlog, blogArticleLayout('<p>Other</p>', '<p>Body</p>', 'https://media.test/b.png'), 'other');

    actingAs($this->user);

    getJson(route('grp.json.webpage.snapshots_comparison', ['webpage' => $blog->id, 'to' => $blog->refresh()->live_snapshot_id]))
        ->assertOk()
        ->assertJsonPath('blocks.0.fields.0.field', 'title')
        ->assertJsonPath('blocks.0.fields.0.before', 'One')
        ->assertJsonPath('blocks.0.fields.0.after', 'Two');

    getJson(route('grp.json.webpage.snapshots_comparison', ['webpage' => $blog->id, 'to' => $otherBlog->refresh()->live_snapshot_id]))
        ->assertUnprocessable();
});

test('editing the meta description of a blog is recorded in its changelog with the value before and after', function () {
    Webpage::enableAuditing();

    $blog = createBlogWebpage($this->website, 'Blog with audited seo', ['description' => 'Old meta description']);

    actingAs($this->user);
    UpdateWebpage::make()->action($blog, ['description' => 'New meta description']);

    $audit = $blog->audits()->latest('id')->first();

    expect($audit->old_values['description'])->toBe('Old meta description')
        ->and($audit->new_values['description'])->toBe('New meta description')
        ->and($audit->user_id)->toBe($this->user->id);
});
