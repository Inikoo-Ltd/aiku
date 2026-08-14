<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-14h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

use App\Actions\Web\Webpage\Luigi\ReindexWebpageLuigiData;
use App\Actions\Web\Website\StoreWebsite;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Website;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    list(
        $this->organisation,
        $this->user,
        $this->shop
    ) = createShop();

    actingAs($this->user);

    ReindexWebpageLuigiData::shouldRun();
    ReindexWebpageLuigiData::mock()
        ->shouldReceive('getJobUniqueId')
        ->andReturn(1);

    $this->website = $this->shop->website ?? StoreWebsite::make()->action(
        $this->shop,
        Website::factory()->definition()
    );
});

test('index web layout templates only returns the ones matching the webpage scope', function () {
    $storefront = $this->website->storefront;

    $template = WebLayoutTemplate::create([
        'name'     => 'Storefront template',
        'scope'    => 'Webpage',
        'type'     => $storefront->type->value,
        'sub_type' => $storefront->sub_type->value,
        'blocks'   => [
            ['type' => 'overview-1'],
            ['type' => 'gallery-1'],
        ],
    ]);

    WebLayoutTemplate::create([
        'name'     => 'Blog template',
        'scope'    => 'Webpage',
        'type'     => WebpageTypeEnum::BLOG->value,
        'sub_type' => WebpageSubTypeEnum::BLOG->value,
        'blocks'   => [],
    ]);

    $response = getJson(route('grp.json.template_layouts.index', ['webpage' => $storefront->id]));

    $response->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($template->id)
        ->and($response->json('data.0.name'))->toBe('Storefront template')
        ->and($response->json('data.0.scope'))->toBe('Webpage')
        ->and($response->json('data.0.blocks_count'))->toBe(2);
});

test('index web layout templates returns empty when there is no template for the scope', function () {
    $storefront = $this->website->storefront;

    WebLayoutTemplate::query()->delete();

    WebLayoutTemplate::create([
        'name'     => 'Blog only template',
        'scope'    => 'Webpage',
        'type'     => WebpageTypeEnum::BLOG->value,
        'sub_type' => WebpageSubTypeEnum::BLOG->value,
        'blocks'   => [],
    ]);

    $response = getJson(route('grp.json.template_layouts.index', ['webpage' => $storefront->id]));

    $response->assertOk();

    expect($response->json('data'))->toBe([]);
});
