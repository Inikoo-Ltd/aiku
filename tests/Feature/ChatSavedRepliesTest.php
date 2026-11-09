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

test('a reply an agent starts is kept on the server: a colleague sees it in the list and the conversation, can carry it on, and sending removes it, HELP-4523', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastChatListEvent::class]);
    \Illuminate\Support\Facades\Queue::fake();

    $lenka     = ($this->newUser)();
    $colleague = ($this->newUser)();
    makeChatWorker($lenka, $this->shop);
    makeChatWorker($colleague, $this->shop);

    $session = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.\Illuminate\Support\Str::random(5),
        'language_id'      => 68,
        'priority'         => \App\Enums\CRM\Livechat\ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    \App\Models\Chat\ChatMessage::create([
        'chat_session_id' => $session->id,
        'message_type'    => \App\Enums\CRM\Livechat\ChatMessageTypeEnum::TEXT->value,
        'sender_type'     => \App\Enums\CRM\Livechat\ChatSenderTypeEnum::GUEST->value,
        'message_text'    => 'Can I have the barcodes?',
        'is_read'         => false,
    ]);

    $draftUrl    = route('grp.org.chat.agents.sessions.reply_draft.update', [$this->organisation->slug, $session->ulid]);
    $showUrl     = route('grp.org.chat.agents.sessions.reply_draft.show', [$this->organisation->slug, $session->ulid]);
    $takeOverUrl = route('grp.org.chat.agents.sessions.reply_draft.take_over', [$this->organisation->slug, $session->ulid]);

    $this->actingAs($lenka)->putJson($draftUrl, ['text' => 'Hi Stuart, I have requested the'])->assertOk()->assertJsonPath('saved', true);
    $this->actingAs($lenka)->putJson($draftUrl, ['text' => 'Hi Stuart, I have requested the barcodes and will get back to you as'])->assertOk();
    \Illuminate\Support\Facades\Event::assertDispatchedTimes(\App\Events\BroadcastChatListEvent::class, 1);

    $this->actingAs($lenka)->getJson($showUrl)->assertOk()
        ->assertJsonPath('mine.text', 'Hi Stuart, I have requested the barcodes and will get back to you as')
        ->assertJsonPath('colleague', null);

    $colleagueView = $this->actingAs($colleague)->getJson($showUrl)->assertOk()
        ->assertJsonPath('mine', null)
        ->assertJsonPath('colleague.text', 'Hi Stuart, I have requested the barcodes and will get back to you as');
    expect($colleagueView->json('colleague.name'))->toBe($lenka->contact_name ?: $lenka->username);

    $listed = collect(\App\Actions\Chat\ChatSession\GetChatSessions::make()->handle(['shop_id' => $this->shop->id, 'limit' => 100])->items())
        ->firstWhere('id', $session->id);
    expect((new \App\Http\Resources\CRM\Livechat\ChatSessionListResource($listed))->resolve()['reply_draft']['user_id'])->toBe($lenka->id);

    $takeOver = ['draft_id' => $colleagueView->json('colleague.id'), 'agent_id' => $colleagueView->json('colleague.agent_id')];
    $this->actingAs($colleague)->patchJson($takeOverUrl, $takeOver)->assertOk()
        ->assertJsonPath('text', 'Hi Stuart, I have requested the barcodes and will get back to you as');
    $this->actingAs($colleague)->patchJson($takeOverUrl, $takeOver)->assertNotFound();

    $this->actingAs($lenka)->putJson($draftUrl, ['text' => 'Hi Stuart, I have requested the barcodes and will get back to you as soon', 'existed' => true])
        ->assertStatus(409)
        ->assertJsonPath('name', $colleague->contact_name ?: $colleague->username);

    $colleagueAgent = \App\Models\Chat\ChatAgent::where('user_id', $colleague->id)->firstOrFail();
    expect($session->replyDrafts()->pluck('chat_agent_id')->all())->toBe([$colleagueAgent->id]);
    $this->actingAs($lenka)->getJson($showUrl)->assertJsonPath('mine', null);

    \App\Actions\Chat\ChatSession\SendChatMessage::make()->handle($session, [
        'message_text' => 'Hi Stuart, the barcodes are attached.',
        'sender_type'  => \App\Enums\CRM\Livechat\ChatSenderTypeEnum::AGENT->value,
        'sender_id'    => $colleagueAgent->id,
    ]);
    expect($session->replyDrafts()->count())->toBe(0);

    $this->actingAs($lenka)->putJson($draftUrl, ['text' => 'Something else'])->assertOk();
    $this->actingAs($lenka)->putJson($draftUrl, ['text' => '  '])->assertOk()->assertJsonPath('saved', false);
    expect($session->replyDrafts()->count())->toBe(0);
});

test('staff who do not work chat on the shop cannot read, write or take over its reply drafts, HELP-4523', function () {
    $outsider = ($this->newUser)();
    $session  = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.\Illuminate\Support\Str::random(5),
        'language_id'      => 68,
        'priority'         => \App\Enums\CRM\Livechat\ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    $params = [$this->organisation->slug, $session->ulid];

    $this->actingAs($outsider)->getJson(route('grp.org.chat.agents.sessions.reply_draft.show', $params))->assertForbidden();
    $this->actingAs($outsider)->putJson(route('grp.org.chat.agents.sessions.reply_draft.update', $params), ['text' => 'Hello'])->assertForbidden();
    $this->actingAs($outsider)->patchJson(route('grp.org.chat.agents.sessions.reply_draft.take_over', $params), ['draft_id' => 1, 'agent_id' => 1])->assertForbidden();
    expect($session->replyDrafts()->count())->toBe(0);
});

test('Aiku\'s draft word for word is never saved as an agent\'s reply draft, and one that was is removed, HELP-4587', function () {
    \Illuminate\Support\Facades\Event::fake([\App\Events\BroadcastChatListEvent::class]);

    $lenka = ($this->newUser)();
    makeChatWorker($lenka, $this->shop);

    $session = \App\Models\Chat\ChatSession::create([
        'ulid'             => (string) \Illuminate\Support\Str::ulid(),
        'status'           => \App\Enums\CRM\Livechat\ChatSessionStatusEnum::WAITING,
        'guest_identifier' => 'guest_'.\Illuminate\Support\Str::random(5),
        'language_id'      => 68,
        'priority'         => \App\Enums\CRM\Livechat\ChatPriorityEnum::NORMAL,
        'shop_id'          => $this->shop->id,
        'ai_model_version' => 'default',
    ]);
    \App\Models\Chat\ChatAiDraft::create([
        'group_id'        => $this->shop->group_id,
        'organisation_id' => $this->shop->organisation_id,
        'shop_id'         => $this->shop->id,
        'chat_session_id' => $session->id,
        'topic'           => \App\Enums\CRM\Livechat\ChatTopicEnum::MISSING_OR_DAMAGED,
        'intent'          => 'damaged_item',
        'text'            => "Hi James,\n\nAustralia is outside our delivery area.",
        'status'          => \App\Enums\CRM\Livechat\ChatAiDraftStatusEnum::DISCARDED,
    ]);

    $draftUrl = route('grp.org.chat.agents.sessions.reply_draft.update', [$this->organisation->slug, $session->ulid]);

    $this->actingAs($lenka)->putJson($draftUrl, ['text' => "Hi James,\n\nAustralia is outside our delivery area.\n"])
        ->assertOk()->assertJsonPath('saved', false);
    expect($session->replyDrafts()->count())->toBe(0);

    $this->actingAs($lenka)->putJson($draftUrl, ['text' => "Hi James,\n\nAustralia is outside our delivery area, sorry."])
        ->assertOk()->assertJsonPath('saved', true);
    $this->actingAs($lenka)->putJson($draftUrl, ['text' => "Hi James,\n\nAustralia is outside our delivery area.", 'existed' => true])
        ->assertOk()->assertJsonPath('saved', false);
    expect($session->replyDrafts()->count())->toBe(0);
});
