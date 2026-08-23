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

test('blog index paginates ten per page, newest first', function () {
    $all = BlogPosts::all();
    $first = get($this->host.'/blog')->assertOk();
    $all->take(10)->each(fn (array $post) => $first->assertSee(route('aiku-public.blog.show', $post['slug']), false));

    if ($all->count() > 10) {
        $first->assertSee('Older notes', false)->assertDontSee(route('aiku-public.blog.show', $all->last()['slug']), false);
        get($this->host.'/blog?page=2')->assertOk()->assertSee(route('aiku-public.blog.show', $all[10]['slug']), false);
    }

    get($this->host.'/blog?page=999')->assertOk()->assertSee(route('aiku-public.blog.show', $all->last()['slug']), false);
});

test('blog index filters by tag and ignores unknown tags', function () {
    $first = BlogPosts::all()->first();
    $tag = $first['tags'][0];
    $count = BlogPosts::all()->filter(fn (array $post) => in_array($tag, $post['tags'], true))->count();

    get($this->host.'/blog?tag='.$tag)
        ->assertOk()
        ->assertSee(route('aiku-public.blog.show', $first['slug']), false)
        ->assertSee('#'.$tag.' <span>'.$count.'</span>', false);

    $response = get($this->host.'/blog?tag=nope');
    $response->assertOk();
    BlogPosts::all()->take(10)->each(fn (array $post) => $response->assertSee(route('aiku-public.blog.show', $post['slug']), false));
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
