<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\HydrateChatKnowledge;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * A note staff write for the AI to answer from, about what is on no page: "the UK shop cannot
 * ship to Germany, use the EU warehouses". Added, changed or removed by anybody who answers this
 * shop's chats; the nightly copy of pages and settings never touches it. A note kept from an answer
 * to a customer's question can start as proposed: the AI uses it once somebody other than its
 * author says yes (decide()), so a wrong answer is not repeated to every customer (INI-257).
 */
class UpdateShopChatKnowledgeNote extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanActOnChatOnShop($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body'  => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array{title: string, body: string}  $modelData
     */
    public function handle(Shop $shop, ?ChatKnowledgeEntry $note, array $modelData, ?User $user = null, string $status = 'active'): ChatKnowledgeEntry
    {
        if ($note) {
            $note->update($modelData);

            return $note;
        }

        return ChatKnowledgeEntry::create($modelData + [
            'group_id'           => $shop->group_id,
            'organisation_id'    => $shop->organisation_id,
            'shop_id'            => $shop->id,
            'kind'               => 'note',
            'source_type'        => 'manual',
            'status'             => $status,
            'is_manual'          => true,
            'created_by_user_id' => $user?->id,
        ]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop, null, $this->validatedData, $request->user());

        return back()->with('notification', ['status' => 'success', 'title' => __('Note saved')]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inNote(Organisation $organisation, Shop $shop, ChatKnowledgeEntry $chatKnowledgeEntry, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        abort_unless($chatKnowledgeEntry->is_manual && $chatKnowledgeEntry->shop_id === $shop->id, 404);
        $this->handle($shop, $chatKnowledgeEntry, $this->validatedData);

        return back()->with('notification', ['status' => 'success', 'title' => __('Note saved')]);
    }

    /**
     * A rule learned from replies, or a note kept from an answer that waits for a second person:
     * used, or removed for good so it is not learned again. Nobody approves their own note.
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function decide(Organisation $organisation, Shop $shop, ChatKnowledgeEntry $chatKnowledgeEntry, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, []);
        abort_unless($this->userCanActOnChatOnShop($request->user(), $shop) && self::awaitsDecisionBy($chatKnowledgeEntry, $request->user()) && $chatKnowledgeEntry->shop_id === $shop->id, 404);
        $chatKnowledgeEntry->update(['status' => $request->validate(['status' => ['required', 'in:active,removed']])['status'], 'conflict' => null]);
        if ($chatKnowledgeEntry->status === 'active') {
            dispatch(fn () => HydrateChatKnowledge::make()->embedMissing($shop));
        }

        return back()->with('notification', ['status' => 'success', 'title' => __('Saved')]);
    }

    public static function awaitsDecisionBy(ChatKnowledgeEntry $entry, User $user): bool
    {
        return $entry->source_type === 'learned'
            || ($entry->is_manual && $entry->status === 'proposed' && $entry->created_by_user_id !== $user->id);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function destroy(Organisation $organisation, Shop $shop, ChatKnowledgeEntry $chatKnowledgeEntry, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, []);
        abort_unless($this->userCanActOnChatOnShop($request->user(), $shop) && $chatKnowledgeEntry->is_manual && $chatKnowledgeEntry->shop_id === $shop->id, 404);
        $chatKnowledgeEntry->delete();

        return back()->with('notification', ['status' => 'success', 'title' => __('Note removed')]);
    }
}
