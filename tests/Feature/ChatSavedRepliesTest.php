<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 29 Oct 2026 14:12:55 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

/** @noinspection PhpUnhandledExceptionInspection */

use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatSavedReply;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;

require_once __DIR__.'/../Support/ChatHelpers.php';

beforeAll(function () {
    loadDB();
});

beforeEach(function () {
    $web = Website::has('shop')->first();
    if (!$web) {
        list(
            $this->organisation,
            $this->user,
            $this->shop
        ) = createShop();
        createWebsite($this->shop);
    } else {
        $this->organisation = $web->organisation;
        $this->shop         = $web->shop;
    }

    $this->otherShop = \App\Actions\Catalogue\Shop\StoreShop::make()->action($this->organisation, Shop::factory()->definition());

    $this->newUser = fn () => User::factory()->create(['group_id' => $this->organisation->group_id, 'status' => true]);

    $this->savedReply = fn (array $attributes) => ChatSavedReply::create(array_merge([
        'group_id'        => $this->organisation->group_id,
        'organisation_id' => $this->organisation->id,
        'shop_id'         => null,
        'user_id'         => null,
        'title'           => 'Reply '.uniqid(),
        'body'            => 'Thank you for your message',
    ], $attributes));

    $this->indexUrl = fn (Shop $shop) => route('grp.org.chat.agents.saved_replies.index', [$this->organisation->slug]).'?shop_id='.$shop->id;
    $this->storeUrl = route('grp.org.chat.agents.saved_replies.store', [$this->organisation->slug]);
    $this->updateUrl = fn (ChatSavedReply $reply) => route('grp.org.chat.agents.saved_replies.update', [$this->organisation->slug, $reply->id]);
    $this->destroyUrl = fn (ChatSavedReply $reply) => route('grp.org.chat.agents.saved_replies.destroy', [$this->organisation->slug, $reply->id]);
});

test('an agent lists their own replies and the ones shared on the shop or the organisation, never a colleague\'s own or another shop\'s, HELP-4362', function () {
    $agent     = ($this->newUser)();
    $colleague = ($this->newUser)();

    $mine            = ($this->savedReply)(['user_id' => $agent->id]);
    $colleagues      = ($this->savedReply)(['user_id' => $colleague->id]);
    $sharedOnShop    = ($this->savedReply)(['shop_id' => $this->shop->id]);
    $sharedOnOrg     = ($this->savedReply)([]);
    $sharedOtherShop = ($this->savedReply)(['shop_id' => $this->otherShop->id]);

    actingAsUserWithOnlyPermissions($agent, ["chat.{$this->shop->id}"]);

    $response = $this->getJson(($this->indexUrl)($this->shop))->assertOk();
    $ids      = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($mine->id, $sharedOnShop->id, $sharedOnOrg->id)
        ->and($ids)->not->toContain($colleagues->id, $sharedOtherShop->id)
        ->and($response->json('can_share'))->toBeFalse()
        ->and(collect($response->json('data'))->firstWhere('id', $mine->id)['can_manage'])->toBeTrue()
        ->and(collect($response->json('data'))->firstWhere('id', $sharedOnShop->id)['can_manage'])->toBeFalse();

    $searched = collect($this->getJson(($this->indexUrl)($this->shop).'&search='.urlencode($mine->title))->assertOk()->json('data'))->pluck('id');
    expect($searched->all())->toBe([$mine->id]);

    $this->getJson(($this->indexUrl)($this->otherShop))->assertForbidden();
});

test('somebody who may not act on the shop\'s chat cannot list or save replies there, HELP-4362', function () {
    $outsider = ($this->newUser)();
    actingAsUserWithOnlyPermissions($outsider, ["human-resources.{$this->organisation->id}.view"]);

    $this->getJson(($this->indexUrl)($this->shop))->assertForbidden();
    $this->postJson($this->storeUrl, ['shop_id' => $this->shop->id, 'title' => 'Hi', 'body' => 'Hello'])->assertForbidden();
});

test('an agent saves personal replies only, a supervisor shares them on the shop and an organisation admin across every shop, HELP-4362', function () {
    $agent      = ($this->newUser)();
    $supervisor = ($this->newUser)();
    $admin      = ($this->newUser)();

    actingAsUserWithOnlyPermissions($agent, ["chat.{$this->shop->id}"]);
    $personal = $this->postJson($this->storeUrl, ['shop_id' => $this->shop->id, 'title' => 'Delivery', 'body' => "Your parcel\nis on its way"])
        ->assertCreated()->json('data');
    expect($personal['is_personal'])->toBeTrue()
        ->and(ChatSavedReply::find($personal['id']))->user_id->toBe($agent->id)->shop_id->toBeNull();
    $this->postJson($this->storeUrl, ['shop_id' => $this->shop->id, 'title' => 'Team', 'body' => 'x', 'is_shared' => true])->assertForbidden();

    actingAsUserWithOnlyPermissions($supervisor, ["chat-m.{$this->shop->id}"]);
    $shared = $this->postJson($this->storeUrl, ['shop_id' => $this->shop->id, 'title' => 'Returns', 'body' => 'Our returns policy', 'is_shared' => true])
        ->assertCreated()->json('data');
    expect(ChatSavedReply::find($shared['id']))->user_id->toBeNull()->shop_id->toBe($this->shop->id);
    $this->postJson($this->storeUrl, ['shop_id' => $this->shop->id, 'title' => 'All', 'body' => 'x', 'is_shared' => true, 'across_shops' => true])->assertForbidden();

    actingAsUserWithOnlyPermissions($admin, ["org-admin.{$this->organisation->id}"]);
    $everywhere = $this->postJson($this->storeUrl, ['shop_id' => $this->shop->id, 'title' => 'Hours', 'body' => 'We open at nine', 'is_shared' => true, 'across_shops' => true])
        ->assertCreated()->json('data');
    expect(ChatSavedReply::find($everywhere['id']))->user_id->toBeNull()->shop_id->toBeNull();
});

test('only the owner changes or deletes a personal reply, and only a supervisor a shared one, HELP-4362', function () {
    $agent      = ($this->newUser)();
    $colleague  = ($this->newUser)();
    $supervisor = ($this->newUser)();

    $personal = ($this->savedReply)(['user_id' => $agent->id]);
    $shared   = ($this->savedReply)(['shop_id' => $this->shop->id]);
    $orgWide  = ($this->savedReply)([]);

    actingAsUserWithOnlyPermissions($colleague, ["chat.{$this->shop->id}"]);
    $this->patchJson(($this->updateUrl)($personal), ['title' => 'Stolen'])->assertForbidden();
    $this->deleteJson(($this->destroyUrl)($personal))->assertForbidden();
    $this->patchJson(($this->updateUrl)($shared), ['title' => 'Changed'])->assertForbidden();
    $this->deleteJson(($this->destroyUrl)($shared))->assertForbidden();

    actingAsUserWithOnlyPermissions($supervisor, ["chat-m.{$this->shop->id}"]);
    $this->patchJson(($this->updateUrl)($personal), ['title' => 'Stolen'])->assertForbidden();
    $this->deleteJson(($this->destroyUrl)($orgWide))->assertForbidden();
    $this->patchJson(($this->updateUrl)($shared), ['body' => 'Updated policy'])->assertOk();
    expect($shared->refresh()->body)->toBe('Updated policy');
    $this->deleteJson(($this->destroyUrl)($shared))->assertOk();
    expect(ChatSavedReply::find($shared->id))->toBeNull();

    actingAsUserWithOnlyPermissions($agent, ["chat.{$this->shop->id}"]);
    $this->patchJson(($this->updateUrl)($personal), ['title' => 'Mine'])->assertOk();
    expect($personal->refresh()->title)->toBe('Mine');
    $this->deleteJson(($this->destroyUrl)($personal))->assertOk();
    expect(ChatSavedReply::find($personal->id))->toBeNull();
});
