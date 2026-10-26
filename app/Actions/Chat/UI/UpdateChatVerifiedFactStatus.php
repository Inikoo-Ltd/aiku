<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 13:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\UI;

use App\Actions\Chat\ChatSession\RememberVerifiedChatFact;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\Chat\ChatKnowledgeEntry;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Staff take a verified fact out of what the assistant trusts, or put a removed one back to be
 * checked again, from the Verified facts tab; only on the shops whose chats they work.
 */
class UpdateChatVerifiedFactStatus
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        $fact = $request->route('chatKnowledgeEntry');

        return $fact instanceof ChatKnowledgeEntry
            && $fact->source_type === RememberVerifiedChatFact::SOURCE
            && $fact->shop
            && $this->userCanActOnChatOnShop($request->user(), $fact->shop);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:removed,disputed'],
        ];
    }

    public function asController(ChatKnowledgeEntry $chatKnowledgeEntry, ActionRequest $request): RedirectResponse
    {
        $status = $request->validated('status');

        RememberVerifiedChatFact::setStatusByStaff($chatKnowledgeEntry, $status, $request->user()->id);

        return back()->with('notification', ['status' => 'success', 'title' => $status === 'removed' ? __('Fact removed') : __('Fact restored, to be checked again')]);
    }
}
