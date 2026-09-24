<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The shop's own words at the end of the email sent while it is closed. Customer service
 * writes them, so whoever may work the shop's chat may change them; empty removes them.
 * They may also leave out the line saying when we open again, when their words say it.
 */
class UpdateShopOutOfHoursMessage extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanActOnChatOnShop($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'message'      => ['present', 'nullable', 'string', 'max:2000'],
            'opening_line' => ['sometimes', 'boolean'],
        ];
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        $settings = $shop->settings ?? [];
        $message  = trim((string) ($modelData['message'] ?? ''));

        if ($message === '') {
            data_forget($settings, 'chat.out_of_hours_message');
        } else {
            data_set($settings, 'chat.out_of_hours_message', $message);
        }

        if (array_key_exists('opening_line', $modelData)) {
            if ($modelData['opening_line']) {
                data_forget($settings, 'chat.out_of_hours_opening_line');
            } else {
                data_set($settings, 'chat.out_of_hours_opening_line', false);
            }
        }

        $shop->update(['settings' => $settings]);

        return $shop;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($shop, $this->validatedData);

        return back()->with('notification', [
            'status' => 'success',
            'title'  => __('Out of hours email saved'),
        ]);
    }
}
