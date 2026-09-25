<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * What customer service wants AI drafts to know about the shop: minimum order, the countries
 * we ship to, dispatch times, how to open an account, samples. Drafts may state only what is
 * written here, so an empty text means those questions are left to an agent.
 */
class UpdateShopChatPolicies extends OrgAction
{
    use WithChatAgentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        return $this->userCanActOnChatOnShop($request->user(), $this->shop);
    }

    public function rules(): array
    {
        return [
            'policies' => ['present', 'nullable', 'string', 'max:6000'],
        ];
    }

    public function handle(Shop $shop, array $modelData): Shop
    {
        $settings = $shop->settings ?? [];
        $policies = trim((string) ($modelData['policies'] ?? ''));

        if ($policies === '') {
            data_forget($settings, 'chat.policies');
        } else {
            data_set($settings, 'chat.policies', $policies);
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
            'title'  => __('Facts for AI replies saved'),
        ]);
    }
}
