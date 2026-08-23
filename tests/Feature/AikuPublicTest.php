<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Aug 2026 14:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use App\Actions\UI\AikuPublic\BlogPosts;

use function Pest\Laravel\get;

beforeEach(function () {
    $this->host = 'http://'.config('app.domain');
});

test('home renders server side with drawings and latest notes', function () {
    $response = get($this->host.'/');

    $response->assertOk()
        ->assertSee('open source operating system for commerce', false)
        ->assertSee('draw-dashboard.svg', false)
        ->assertSee(BlogPosts::all()->first()['title'], false);
});

test('blog index lists every post', function () {
    $response = get($this->host.'/blog');

    $response->assertOk();
    BlogPosts::all()->each(fn (array $post) => $response->assertSee(route('aiku-public.blog.show', $post['slug']), false));
});

test('blog post renders markdown and structured data', function () {
    $post = BlogPosts::all()->first();

    get($this->host.'/blog/'.$post['slug'])
        ->assertOk()
        ->assertSee('<h2>', false)
        ->assertSee('"@type":"BlogPosting"', false)
        ->assertSee($post['title'], false);
});

test('unknown post is 404 and slug is validated', function () {
    get($this->host.'/blog/does-not-exist')->assertNotFound();
    expect(BlogPosts::find('../../.env'))->toBeNull();
});

test('sitemap and robots are served', function () {
    get($this->host.'/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('aiku-public.blog.index').'</loc>', false);

    get($this->host.'/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: '.route('aiku-public.sitemap'), false);
});
