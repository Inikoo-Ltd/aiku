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
 * Whether an email whose customer only thanks us waits for the customer and then closes, and
 * for how long. How the inbox is worked is a supervisor's call.
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
            'wait_for_customer_hours'    => ['sometimes', 'integer', 'min:1', 'max:720'],
        ];
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        $settings = $shop->settings ?? [];

        data_set($settings, 'chat.close_after_thanks', (bool) $modelData['close_after_thanks']);

        if (isset($modelData['wait_for_customer_hours'])) {
            data_set($settings, 'chat.wait_for_customer_hours', (int) $modelData['wait_for_customer_hours']);
        }

        $shop->update(['settings' => $settings]);

        return $shop;
    }

    public static function isOn(Shop $shop): bool
    {
        return (bool) data_get($shop->settings, 'chat.close_after_thanks', true);
    }

    public static function waitingHours(Shop $shop): int
    {
        return (int) data_get($shop->settings, 'chat.wait_for_customer_hours', config('chat.wait_for_customer_hours'));
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
