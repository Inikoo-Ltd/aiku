<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\Web\Seo\StoreSerpResult;
use App\Enums\UI\Web\SeoKeywordsTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoKeywordTag;
use App\Models\Web\SeoTrackedKeyword;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    Config::set('inertia.pages.paths', [resource_path('js/Pages/Grp')]);
    actingAs($this->user);
    $this->withoutVite();

    $this->token = strtolower(Str::random(8));
});

/**
 * One tracked row per device in Spain, with its latest check and keyword data.
 */
function trackSeoKeyword(Shop $shop, string $keyword, array $check = [], array $data = []): void
{
    foreach (['mobile', 'desktop'] as $device) {
        $shop->seoTrackedKeywords()->create([
            'keyword'       => $keyword,
            'country_code'  => 'ES',
            'language_code' => 'es',
            'device'        => $device,
            'frequency'     => 'weekly',
            ...$check,
        ]);
    }

    if ($data) {
        DB::table('seo_keywords')->insert([
            'shop_id'       => $shop->id,
            'keyword'       => $keyword,
            'country_code'  => 'ES',
            'language_code' => 'es',
            'source'        => 'dataforseo_labs',
            'fetched_at'    => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
            ...$data,
        ]);
    }
}

/**
 * @return array<int, string>
 */
function seoKeywordsMatching(Shop $shop, string $tab, string $token, array $filters): array
{
    $parameters = [$shop->organisation->slug, $shop->slug, 'tab' => $tab, $tab.'_filter' => ['global' => $token], $tab.'_filters' => $filters, $tab.'_perPage' => 100];

    $data = json_decode(json_encode(get(route('grp.org.shops.show.seo.keywords.show', $parameters))->assertOk()->viewData('page')['props'][$tab]), true);

    $rows = $tab === SeoKeywordsTabsEnum::RANKINGS->value ? $data['table']['data'] : $data['data'];

    return collect($rows)->pluck('keyword')->sort()->values()->all();
}

test('bulk add previews the list, then tracks the new keywords with their tags and skips the rest, HELP-4115', function () {
    $t = $this->token;
    trackSeoKeyword($this->shop, "$t tracked");
    $this->shop->seoTrackedKeywords()->create(['keyword' => "$t mobile only", 'country_code' => 'ES', 'language_code' => 'es', 'device' => 'mobile', 'frequency' => 'weekly']);
    $tag = $this->shop->seoKeywordTags()->create(['name' => "campaign $t"]);

    $request = [
        'lines'         => ["$t one", '  '.strtoupper("$t one").'  ', "$t   two", "$t tracked", '!!!', str_repeat('a', 256), '', "$t mobile only"],
        'country_codes' => ['es'],
        'language_code' => 'ES',
        'frequency'     => 'weekly',
        'tag_ids'       => [$tag->id],
    ];

    $preview = postJson(route('grp.models.shop.seo.tracked_keywords.bulk_store', $this->shop->id), [...$request, 'dry_run' => true])->assertOk()->json();

    expect(collect($preview['rows'])->pluck('status', 'line')->all())->toBe([1 => 'new', 2 => 'duplicate', 3 => 'new', 4 => 'tracked', 5 => 'invalid', 6 => 'invalid', 8 => 'add_countries'])
        ->and($preview['summary']['new'])->toBe(2)
        ->and($this->shop->seoTrackedKeywords()->where('keyword', "$t one")->exists())->toBeFalse();

    $result = postJson(route('grp.models.shop.seo.tracked_keywords.bulk_store', $this->shop->id), $request)->assertOk()->json();

    expect($result['summary'])->toMatchArray(['new' => 2, 'add_countries' => 1, 'duplicate' => 1, 'tracked' => 1, 'invalid' => 2, 'failed' => 0])
        ->and($this->shop->seoTrackedKeywords()->where('keyword', "$t two")->where('frequency', 'weekly')->count())->toBe(2)
        ->and($this->shop->seoTrackedKeywords()->where('keyword', "$t mobile only")->count())->toBe(2)
        ->and(DB::table('seo_keyword_tag_keyword')->where('tag_id', $tag->id)->orderBy('keyword')->pluck('keyword')->all())->toBe(["$t mobile only", "$t one", "$t tracked", "$t two"]);
});

test('keyword tags are created, renamed, put on and taken off keywords, and deleting one keeps the keywords, HELP-4115', function () {
    $t = $this->token;
    trackSeoKeyword($this->shop, "$t alpha");
    trackSeoKeyword($this->shop, "$t beta");

    post(route('grp.models.shop.seo.keyword_tag.store', $this->shop->id), ['name' => "  oils   $t "])->assertSessionHasNoErrors();
    post(route('grp.models.shop.seo.keyword_tag.store', $this->shop->id), ['name' => "oils $t"])->assertSessionHasErrors('name');

    $tag = SeoKeywordTag::where('shop_id', $this->shop->id)->where('name', "oils $t")->firstOrFail();

    patch(route('grp.models.seo_keyword_tag.update', $tag->id), ['name' => "essential oils $t"])->assertSessionHasNoErrors();
    expect($tag->refresh()->name)->toBe("essential oils $t");

    post(route('grp.models.shop.seo.keyword_tags.assign', $this->shop->id), ['keywords' => ["$t alpha", "$t beta", "$t not tracked"], 'add_tag_ids' => [$tag->id]])->assertSessionHasNoErrors();
    expect(DB::table('seo_keyword_tag_keyword')->where('tag_id', $tag->id)->count())->toBe(2);

    post(route('grp.models.shop.seo.keyword_tags.assign', $this->shop->id), ['keywords' => ["$t beta"], 'remove_tag_ids' => [$tag->id]])->assertSessionHasNoErrors();
    expect(DB::table('seo_keyword_tag_keyword')->where('tag_id', $tag->id)->pluck('keyword')->all())->toBe(["$t alpha"]);

    delete(route('grp.models.seo_keyword_tag.delete', $tag->id))->assertSessionHasNoErrors();

    expect(SeoKeywordTag::find($tag->id))->toBeNull()
        ->and(DB::table('seo_keyword_tag_keyword')->where('tag_id', $tag->id)->count())->toBe(0)
        ->and($this->shop->seoTrackedKeywords()->where('keyword', "$t alpha")->count())->toBe(2);
});

test('rankings filter by position, change, SERP feature, tags, intent, volume, difficulty and keyword conditions, HELP-4115', function () {
    $t       = $this->token;
    $checked = ['last_checked_at' => now(), 'previous_checked_at' => now()->subWeek()];

    trackSeoKeyword($this->shop, "$t top", [...$checked, 'position' => 2, 'previous_position' => 5, 'serp_features' => ['local_pack'], 'serp_features_owned' => ['local_pack']], ['avg_monthly_searches' => 1000, 'keyword_difficulty' => 30, 'intent' => 'commercial']);
    trackSeoKeyword($this->shop, "$t middle", [...$checked, 'position' => 15, 'previous_position' => 9, 'serp_features' => ['local_pack']], ['avg_monthly_searches' => 0, 'keyword_difficulty' => 70, 'intent' => 'informational']);
    trackSeoKeyword($this->shop, "$t lost", [...$checked, 'position' => null, 'previous_position' => 8], ['avg_monthly_searches' => 50]);
    trackSeoKeyword($this->shop, "$t new", [...$checked, 'position' => 20, 'previous_position' => null]);

    $tag = $this->shop->seoKeywordTags()->create(['name' => "tag $t"]);
    DB::table('seo_keyword_tag_keyword')->insert(['tag_id' => $tag->id, 'keyword' => "$t top"]);

    $rankings = fn (array $filters) => seoKeywordsMatching($this->shop, SeoKeywordsTabsEnum::RANKINGS->value, $t, $filters);

    expect($rankings([]))->toBe(["$t lost", "$t middle", "$t new", "$t top"])
        ->and($rankings(['position' => 'top_3']))->toBe(["$t top"])
        ->and($rankings(['position' => 'not_ranked']))->toBe(["$t lost"])
        ->and($rankings(['position_min' => 10, 'position_max' => 20]))->toBe(["$t middle", "$t new"])
        ->and($rankings(['change' => ['improved']]))->toBe(["$t top"])
        ->and($rankings(['change' => ['declined', 'lost']]))->toBe(["$t lost", "$t middle"])
        ->and($rankings(['change' => ['new']]))->toBe(["$t new"])
        ->and($rankings(['serp_feature' => 'local_pack']))->toBe(["$t middle", "$t top"])
        ->and($rankings(['serp_feature' => 'local_pack', 'serp_mode' => 'ours']))->toBe(["$t top"])
        ->and($rankings(['serp_feature' => 'local_pack', 'serp_mode' => 'not_ours']))->toBe(["$t middle"])
        ->and($rankings(['serp_feature' => 'local_pack', 'serp_mode' => 'not_on_serp']))->toBe(["$t lost", "$t new"])
        ->and($rankings(['tags' => [(string) $tag->id]]))->toBe(["$t top"])
        ->and($rankings(['tags' => ['none']]))->toBe(["$t lost", "$t middle", "$t new"])
        ->and($rankings(['tags' => [(string) $tag->id], 'tags_mode' => 'exclude']))->toBe(["$t lost", "$t middle", "$t new"])
        ->and($rankings(['intents' => ['commercial', 'informational']]))->toBe(["$t middle", "$t top"])
        ->and($rankings(['volume_max' => 100]))->toBe(["$t lost", "$t middle"])
        ->and($rankings(['volume_unknown' => 'true']))->toBe(["$t new"])
        ->and($rankings(['kd_min' => 50]))->toBe(["$t middle"])
        ->and($rankings(['rules' => [['type' => 'include', 'match' => 'ends', 'value' => 'top'], ['type' => 'include', 'match' => 'contains', 'value' => 'mid']], 'logic' => 'or']))->toBe(["$t middle", "$t top"])
        ->and($rankings(['rules' => [['type' => 'exclude', 'match' => 'contains', 'value' => 'top'], ['type' => 'exclude', 'match' => 'exact', 'value' => "$t new"]]]))->toBe(["$t lost", "$t middle"]);

    expect($rankings(['change' => [['improved']], 'serp_feature' => ['local_pack'], 'tags' => [['x']], 'rules' => [['value' => ['top']]]]))->toBe(["$t lost", "$t middle", "$t new", "$t top"]);

    $tracked = seoKeywordsMatching($this->shop, SeoKeywordsTabsEnum::TRACKED_KEYWORDS->value, $t, ['tags' => [(string) $tag->id]]);

    expect($tracked)->toBe(["$t top"]);
});

test('a SERP check records the result-page features that show our website, HELP-4115', function () {
    $website = createWebsite($this->shop);
    trackSeoKeyword($this->shop, "$this->token serp");

    $trackedKeyword = SeoTrackedKeyword::where('shop_id', $this->shop->id)->where('keyword', "$this->token serp")->where('device', 'mobile')->firstOrFail();

    StoreSerpResult::run($trackedKeyword, [
        'datetime' => now()->toDateTimeString(),
        'items'    => [
            ['type' => 'local_pack', 'domain' => 'www.'.$website->domain],
            ['type' => 'featured_snippet', 'domain' => 'other-shop.com'],
            ['type' => 'ai_overview', 'references' => [['url' => "https://$website->domain/page"]]],
            ['type' => 'organic', 'rank_group' => 4, 'domain' => $website->domain, 'url' => "https://$website->domain/x"],
        ],
    ]);

    expect($trackedKeyword->refresh()->serp_features_owned)->toBe(['local_pack', 'ai_overview'])
        ->and($trackedKeyword->serp_features)->toBe(['local_pack', 'featured_snippet', 'ai_overview'])
        ->and($trackedKeyword->position)->toBe(4);
});
