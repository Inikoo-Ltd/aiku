<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jul 2026 13:16:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Http\Middleware\DetectIrisWebsite;
use App\Http\Middleware\DetectWebsite;
use App\Models\Web\Website;
use Illuminate\Http\Request;
use App\Actions\Web\Website\UI\DetectWebsiteFromDomain;

beforeEach(function () {
    loadDB();
    $this->organisation = createOrganisation();
    $this->shop = createShop($this->organisation)[2];
    $this->website = createWebsite($this->shop);
    $this->website->update(['status' => true]);
});

test('it detects website', function () {
    DetectWebsiteFromDomain::shouldRun()->andReturn($this->website);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectWebsite();

    $response = $middleware->handle($request, function ($req) {
        expect($req->input('website'))->toBeInstanceOf(Website::class)
            ->and($req->input('website')->id)->toBe($this->website->id)
            ->and($req->input('domain'))->toBe($this->website->domain);

        return response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('it processes blocked country regions in DetectWebsite', function () {
    $this->website->update([
        'blocked_country_regions' => [
            'US' => ['cities' => ['New York']],
            'GB' => ['postcode' => ['SW1A 1AA']],
            'FR' => ['something_else' => 'value']
        ]
    ]);

    DetectWebsiteFromDomain::shouldRun()->andReturn($this->website);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectWebsite();

    $middleware->handle($request, function ($req) {
        expect($req->input('has_blocked_country_regions'))->toBeTrue()
            ->and($req->input('blocked_countries'))->toHaveCount(2)
            ->and($req->input('blocked_countries'))->toContain('US', 'GB')
            ->and($req->input('blocked_country_regions'))->toEqual($this->website->blocked_country_regions);

        return response('OK');
    });
});

test('it detects iris website', function () {
    // Mock parseDomain to return the website's domain
    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectIrisWebsite();

    $response = $middleware->handle($request, function ($req) {
        expect($req->input('website'))->toBeInstanceOf(Website::class)
            ->and($req->input('website')->id)->toBe($this->website->id)
            ->and($req->input('domain'))->toBe($this->website->domain);

        return response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
});

test('it processes blocked country regions in DetectIrisWebsite', function () {
    $this->website->update([
        'blocked_country_regions' => [
            'US' => ['cities' => ['New York']],
            'GB' => ['postcode' => ['SW1A 1AA']],
            'FR' => ['something_else' => 'value'] // Should not be in blocked_countries
        ]
    ]);

    DetectWebsiteFromDomain::mock()
        ->shouldReceive('parseDomain')
        ->andReturn($this->website->domain);

    $request = Request::create('http://' . $this->website->domain, 'GET');

    $middleware = new DetectIrisWebsite();

    $middleware->handle($request, function ($req) {
        expect($req->input('has_blocked_country_regions'))->toBeTrue()
            ->and($req->input('blocked_countries'))->toHaveCount(2)
            ->and($req->input('blocked_countries'))->toContain('US', 'GB')
            ->and($req->input('blocked_country_regions'))->toEqual($this->website->blocked_country_regions);

        return response('OK');
    });
});
