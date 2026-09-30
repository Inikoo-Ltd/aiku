<?php

use App\Actions\Web\Webpage\StoreWebpage;
use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    loadDB();
    list($this->organisation, $this->user, $this->shop) = createShop();
    $this->website = createWebsite($this->shop);
});

function blogAuthor(Webpage $webpage): ?array
{
    return data_get($webpage->webBlocks()->first()->layout, 'data.fieldValue.author');
}

test('a blog created by a user has that user as its author', function () {
    $this->user->update(['contact_name' => 'Jane Writer']);

    actingAs($this->user)
        ->post(route('grp.models.shop.blog_webpage.store', [$this->shop->id, $this->website->id]), [
            'code'     => 'first-post',
            'title'    => 'First post',
            'url'      => 'first-post',
            'sub_type' => WebpageSubTypeEnum::BLOG->value,
        ])
        ->assertRedirect();

    $webpage = Webpage::where('website_id', $this->website->id)->where('code', 'first-post')->firstOrFail();

    expect(blogAuthor($webpage))->toBe(['id' => $this->user->id, 'name' => 'Jane Writer']);
});

test('a blog author can be another user', function () {
    $otherUser = User::where('group_id', $this->shop->group_id)->where('id', '!=', $this->user->id)->first() ?? $this->user;

    $webpage = StoreWebpage::make()->action($this->website, array_merge(
        Webpage::factory()->definition(),
        [
            'type'      => WebpageTypeEnum::BLOG->value,
            'sub_type'  => WebpageSubTypeEnum::BLOG->value,
            'author_id' => $otherUser->id,
        ]
    ));

    expect(blogAuthor($webpage))->toBe([
        'id'   => $otherUser->id,
        'name' => $otherUser->contact_name ?: $otherUser->username,
    ]);
});

test('blog authors lists the users of the group by name', function () {
    $this->user->update(['contact_name' => 'Jane Writer']);

    actingAs($this->user)
        ->getJson(route('grp.json.shop.blog_authors', [$this->shop->slug, 'filter[global]' => 'Jane']))
        ->assertOk()
        ->assertJsonFragment(['id' => $this->user->id, 'name' => 'Jane Writer']);
});
