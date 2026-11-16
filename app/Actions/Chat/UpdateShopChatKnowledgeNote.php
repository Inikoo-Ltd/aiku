<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\Chat\ChatSession\HydrateChatKnowledge;
use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A note staff write for the AI to answer from, about what is on no page: "the UK shop cannot
 * ship to Germany, use the EU warehouses". Added, changed or removed by anybody who answers this
 * shop's chats; the nightly copy of pages and settings never touches it. A note kept from an answer
 * to a customer's question can start as proposed: the AI uses it once somebody other than its
 * author says yes (decide()), so a wrong answer is not repeated to every customer (INI-257).
 *
 * A note reaches one shop, every shop of its organisation, or every shop of the group (REACHES), so
 * one fact is one row instead of a copy per shop. Anybody who answers the shop's chats keeps notes
 * for the shop; only an organisation admin (or a group admin) sets one for the whole organisation,
 * and only a group admin for the group, and they alone change or remove it (canManageReach()), as a
 * wider note changes what Merlin tells the customers of every shop it reaches.
 */
class UpdateShopChatKnowledgeNote extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanActOnChatOnShop($request->user(), $this->shop);
    }

    public const array REACHES = ['shop', 'organisation', 'group'];

    /**
     * Only saving a note sends its text: removing it or deciding on it sends none, and asking for a
     * title there refused every Remove and Use it.
     */
    public function rules(): array
    {
        if (request()->isMethod('DELETE') || request()->routeIs('grp.org.shops.show.chat.settings.knowledge.status')) {
            return [];
        }

        return [
            'title' => ['required', 'string', 'max:200'],
            'body'  => ['required', 'string', 'max:2000'],
            'reach' => ['sometimes', 'string', 'in:'.implode(',', self::REACHES)],
        ];
    }

    /**
     * @param  array{title: string, body: string, reach?: string}  $modelData
     */
    public function handle(Shop $shop, ?ChatKnowledgeEntry $note, array $modelData, ?User $user = null, string $status = 'active'): ChatKnowledgeEntry
    {
        $reach     = $modelData['reach'] ?? ($note ? self::reachOf($note) : 'shop');
        $modelData = [
            'title'           => $modelData['title'],
            'body'            => $modelData['body'],
            'organisation_id' => in_array($reach, ['shop', 'organisation'], true) ? $shop->organisation_id : null,
            'shop_id'         => $reach === 'shop' ? $shop->id : null,
        ];

        if ($note) {
            $textChanged = $note->title !== $modelData['title'] || $note->body !== $modelData['body'];
            $note->update($modelData + ($textChanged ? ['embedding' => null] : []));

            return $note;
        }

        return ChatKnowledgeEntry::create($modelData + [
            'group_id'           => $shop->group_id,
            'kind'               => 'note',
            'source_type'        => 'manual',
            'status'             => $status,
            'is_manual'          => true,
            'created_by_user_id' => $user?->id,
        ]);
    }

    public static function reachOf(ChatKnowledgeEntry $note): string
    {
        return $note->shop_id ? 'shop' : ($note->organisation_id ? 'organisation' : 'group');
    }

    public static function appliesTo(ChatKnowledgeEntry $note, Shop $shop): bool
    {
        return $note->group_id === $shop->group_id
            && ($note->shop_id === $shop->id
                || (!$note->shop_id && $note->organisation_id === $shop->organisation_id)
                || (!$note->shop_id && !$note->organisation_id));
    }

    public function canManageReach(User $user, Shop $shop, string $reach): bool
    {
        $isGroupAdmin = $user->status && $user->group_id === $shop->group_id && $user->hasRole(RolesEnum::GROUP_ADMIN->value);

        return match ($reach) {
            'group'        => $isGroupAdmin,
            'organisation' => $isGroupAdmin || ($user->status && $user->authTo(["org-admin.{$shop->organisation_id}"])),
            default        => $this->userCanActOnChatOnShop($user, $shop),
        };
    }

    /**
     * The reaches this user may give a note written from this shop's page.
     *
     * @return array<int, string>
     */
    public function reachesFor(User $user, Shop $shop): array
    {
        return array_values(array_filter(self::REACHES, fn (string $reach) => $this->canManageReach($user, $shop, $reach)));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        abort_unless($this->canManageReach($request->user(), $shop, $this->validatedData['reach'] ?? 'shop'), 403);
        $this->handle($shop, null, $this->validatedData, $request->user());

        return back()->with('notification', ['status' => 'success', 'title' => __('Note saved')]);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inNote(Organisation $organisation, Shop $shop, ChatKnowledgeEntry $chatKnowledgeEntry, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        abort_unless($chatKnowledgeEntry->is_manual && self::appliesTo($chatKnowledgeEntry, $shop), 404);
        abort_unless(
            $this->canManageReach($request->user(), $shop, self::reachOf($chatKnowledgeEntry))
            && $this->canManageReach($request->user(), $shop, $this->validatedData['reach'] ?? self::reachOf($chatKnowledgeEntry)),
            403
        );
        if ($chatKnowledgeEntry->status === 'proposed' && ($this->validatedData['reach'] ?? self::reachOf($chatKnowledgeEntry)) !== self::reachOf($chatKnowledgeEntry)) {
            throw ValidationException::withMessages(['reach' => __('This note is waiting for a second yes for this shop: approve it first, then widen it.')]);
        }
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
        $status = Validator::make($request->all(), ['status' => ['required', 'in:active,removed']])->validate()['status'];
        $chatKnowledgeEntry->update(['status' => $status, 'conflict' => null]);
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
        abort_unless($chatKnowledgeEntry->is_manual && self::appliesTo($chatKnowledgeEntry, $shop), 404);
        abort_unless($this->canManageReach($request->user(), $shop, self::reachOf($chatKnowledgeEntry)), 403);
        $chatKnowledgeEntry->delete();

        return back()->with('notification', ['status' => 'success', 'title' => __('Note removed')]);
    }
}
