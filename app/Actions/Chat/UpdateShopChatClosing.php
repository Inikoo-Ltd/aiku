<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Actions\OrgAction;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Whether a customer who only thanks us gets a 👍 and the conversation closes, and how long
 * it waits first when an agent is in the chat. How the inbox is worked is a supervisor's call.
 */
class UpdateShopChatClosing extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userSupervisesChatOnShop($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'close_after_thanks'         => ['required', 'boolean'],
            'close_after_thanks_minutes' => ['required', 'integer', 'min:1', 'max:60'],
        ];
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        $settings = $shop->settings ?? [];

        data_set($settings, 'chat.close_after_thanks', (bool) $modelData['close_after_thanks']);
        data_set($settings, 'chat.close_after_thanks_minutes', (int) $modelData['close_after_thanks_minutes']);

        $shop->update(['settings' => $settings]);

        return $shop;
    }

    public static function isOn(Shop $shop): bool
    {
        return (bool) data_get($shop->settings, 'chat.close_after_thanks', true);
    }

    public static function minutes(Shop $shop): int
    {
        return (int) data_get($shop->settings, 'chat.close_after_thanks_minutes', config('chat.close_after_thanks_minutes'));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($shop, $this->validatedData);

        return back()->with('notification', [
            'status' => 'success',
            'title'  => __('Closing chats saved'),
        ]);
    }
}
