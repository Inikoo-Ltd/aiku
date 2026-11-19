<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-13h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

/** @noinspection PhpUnhandledExceptionInspection */

namespace Tests\Feature;

use App\Http\Middleware\SetMobileApiLocale;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\getJson;

function getMobileAppVersion(array $headers = ['X-Aiku-App-Key' => 'mobile-test-key']): TestResponse
{
    return getJson(route('grp.api.mobile.app_version'), $headers);
}

beforeEach(function () {
    config(['app.mobile_app_keys' => ['mobile-test-key']]);
});

test('mobile app version answers with the default versions to an app that is not signed in', function () {
    $defaults = [
        'minimum_version_code' => 1,
        'latest_version_code'  => 1,
        'latest_version_name'  => '0.1.0',
        'update_url'           => null,
    ];

    getMobileAppVersion()->assertOk()->assertExactJson(['data' => ['android' => $defaults, 'ios' => $defaults]]);
});

test('mobile app version gives each platform the versions and update link from the config', function () {
    config(['app.mobile_versions' => [
        'android' => [
            'minimum_version_code' => '3',
            'latest_version_code'  => 5,
            'latest_version_name'  => '1.2.0',
            'update_url'           => 'https://appdistribution.firebase.dev/i/abc123',
        ],
        'ios'     => [
            'minimum_version_code' => 2,
            'latest_version_code'  => '4',
            'latest_version_name'  => '1.1.0',
            'update_url'           => null,
        ],
    ]]);

    getMobileAppVersion()->assertOk()->assertExactJson(['data' => [
        'android' => [
            'minimum_version_code' => 3,
            'latest_version_code'  => 5,
            'latest_version_name'  => '1.2.0',
            'update_url'           => 'https://appdistribution.firebase.dev/i/abc123',
        ],
        'ios'     => [
            'minimum_version_code' => 2,
            'latest_version_code'  => 4,
            'latest_version_name'  => '1.1.0',
            'update_url'           => null,
        ],
    ]]);
});

test('mobile app version falls back to the defaults for missing or unusable config instead of failing', function (mixed $mobileVersions) {
    config(['app.mobile_versions' => $mobileVersions]);

    getMobileAppVersion()->assertOk()
        ->assertJsonPath('data.android', ['minimum_version_code' => 1, 'latest_version_code' => 1, 'latest_version_name' => '0.1.0', 'update_url' => null])
        ->assertJsonPath('data.ios', ['minimum_version_code' => 1, 'latest_version_code' => 1, 'latest_version_name' => '0.1.0', 'update_url' => null]);
})->with([
    'no config'        => [null],
    'not a list'       => ['1'],
    'no platforms'     => [[]],
    'platform not set' => [['android' => null, 'ios' => 'x']],
    'unusable values'  => [[
        'android' => ['minimum_version_code' => 0, 'latest_version_code' => 'abc', 'latest_version_name' => '', 'update_url' => ''],
        'ios'     => ['minimum_version_code' => '-2', 'latest_version_code' => '1.5', 'latest_version_name' => '  ', 'update_url' => 'not a url'],
    ]],
]);

test('mobile app version needs the app key', function () {
    getMobileAppVersion([])->assertNotFound();
    getMobileAppVersion(['X-Aiku-App-Key' => 'unknown-key'])->assertNotFound();
});

test('the Accept-Language middleware is on the mobile routes only', function () {
    $routes = Route::getRoutes();

    expect($routes->getByName('grp.api.mobile.app_version')->gatherMiddleware())->toContain(SetMobileApiLocale::class)
        ->and($routes->getByName('grp.api.mobile.login')->gatherMiddleware())->toContain(SetMobileApiLocale::class)
        ->and($routes->getByName('grp.api.mobile.warehouses.goods_in_hub')->gatherMiddleware())->toContain(SetMobileApiLocale::class)
        ->and($routes->getByName('grp.api.profile')->gatherMiddleware())->not->toContain(SetMobileApiLocale::class);
});

test('the mobile api language is read from Accept-Language among the lang files only', function (?string $acceptLanguage, ?string $locale) {
    expect(SetMobileApiLocale::requestedLocale($acceptLanguage))->toBe($locale);
})->with([
    'slovak'                     => ['sk', 'sk'],
    'chinese as in lang'         => ['zh-Hans', 'zh-Hans'],
    'any case or underscore'     => ['ZH_hans', 'zh-Hans'],
    'region of a lang locale'    => ['pt-BR', 'pt'],
    'first acceptable by weight' => ['el, de;q=0.5, fr;q=0.8', 'fr'],
    'refused language'           => ['de;q=0', null],
    'not a lang locale'          => ['el', null],
    'chinese without script'     => ['zh-CN', null],
    'any language'               => ['*', null],
    'empty'                      => ['', null],
    'no header'                  => [null, null],
]);

test('the mobile api offers the 23 Aiku languages', function () {
    expect(array_values(SetMobileApiLocale::supportedLocales()))->toEqualCanonicalizing([
        'en', 'bg', 'cs', 'de', 'es', 'fr', 'hi', 'hr', 'hu', 'id', 'it', 'ja', 'lv', 'ne', 'nl', 'pl', 'pt', 'ro', 'sk', 'sv', 'tr', 'uk', 'zh-Hans',
    ]);
});
