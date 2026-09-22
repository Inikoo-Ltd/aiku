<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession\UI;

use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectToOrgChatInbox
{
    use AsAction;
    use WithChatAgentAuthorisation;

    /**
     * The inbox is the same view from every address, so this only has to land on one the user
     * may open. It follows the chat permissions: asking the shop assignment table sent anybody
     * made an agent by their position to a 404 instead of to their conversations.
     */
    public function asController(ActionRequest $request): RedirectResponse
    {
        $user = $request->user();

        $shop = $user instanceof User
            ? Shop::where('state', ShopStateEnum::OPEN)->orderBy('id')->get()
                ->first(fn (Shop $shop) => $this->userCanViewChatOnShop($user, $shop))
            : null;

        abort_unless((bool) $shop, 403, __('You do not have access to any chat'));

        return redirect()->route('grp.org.chat.inbox', [$shop->organisation->slug]);
    }
}
