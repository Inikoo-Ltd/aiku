<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-14h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

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

    $this->website = $this->shop->website ?? StoreWebsite::make()->action(
        $this->shop,
        Website::factory()->definition()
    );
});

test('index web layout templates returns every template for the scope by default', function () {
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

    expect($response->json('data'))->toHaveCount(2);

    $response = getJson(route('grp.json.template_layouts.index', ['webpage' => $storefront->id, 'filter' => ['show' => 'matching']]));

    $response->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($template->id)
        ->and($response->json('data.0.name'))->toBe('Storefront template')
        ->and($response->json('data.0.scope'))->toBe('Webpage')
        ->and($response->json('data.0.blocks_count'))->toBe(2);
});

test('index web layout templates returns empty when no template matches the webpage type', function () {
    $storefront = $this->website->storefront;

    WebLayoutTemplate::query()->delete();

    WebLayoutTemplate::create([
        'name'     => 'Blog only template',
        'scope'    => 'Webpage',
        'type'     => WebpageTypeEnum::BLOG->value,
        'sub_type' => WebpageSubTypeEnum::BLOG->value,
        'blocks'   => [],
    ]);

    $response = getJson(route('grp.json.template_layouts.index', ['webpage' => $storefront->id, 'filter' => ['show' => 'matching']]));

    $response->assertOk();

    expect($response->json('data'))->toBe([]);
});

test('a layout template is deleted only by its author or the group webmaster, INI-073', function () {
    $storefront = $this->website->storefront;
    $template   = fn (?int $authorId) => WebLayoutTemplate::create([
        'name'      => 'INI-073 '.uniqid(),
        'scope'     => 'Webpage',
        'type'      => $storefront->type->value,
        'sub_type'  => $storefront->sub_type->value,
        'blocks'    => [],
        'author_id' => $authorId,
    ]);
    $own      = $template($this->user->id);
    $someones = $template(null);

    $originalRoles       = $this->user->roles->pluck('name')->toArray();
    $originalPermissions = $this->user->getDirectPermissions()->pluck('name')->all();

    try {
        actingAsUserWithOnlyPermissions($this->user, ["web.{$this->shop->id}.edit"]);

        $listed = collect(getJson(route('grp.json.template_layouts.index', ['webpage' => $storefront->id]))->assertOk()->json('data'))->keyBy('id');
        expect($listed[$own->id]['can_delete'])->toBeTrue()
            ->and($listed[$someones->id]['can_delete'])->toBeFalse();

        Pest\Laravel\deleteJson(route('grp.models.web_layout_template.delete', $someones->id))->assertForbidden();
        expect(WebLayoutTemplate::find($someones->id))->not->toBeNull();

        Pest\Laravel\deleteJson(route('grp.models.web_layout_template.delete', $own->id))->assertSuccessful();
        expect(WebLayoutTemplate::find($own->id))->toBeNull();

        actingAsUserWithOnlyPermissions($this->user, ['group-webmaster.view']);
        Pest\Laravel\deleteJson(route('grp.models.web_layout_template.delete', $someones->id))->assertSuccessful();
        expect(WebLayoutTemplate::find($someones->id))->toBeNull();
    } finally {
        setPermissionsTeamId($this->user->group_id);
        $this->user->syncPermissions($originalPermissions);
        actingAsUserWithRoles($this->user, $originalRoles);
        \App\Actions\SysAdmin\User\SetUserAuthorisedModels::run($this->user);
    }
});
