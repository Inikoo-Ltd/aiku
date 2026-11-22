<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Organisation admins set, per shop, whether customers can claim for themselves and the limits
 * under which a claim is settled with no person (GetCustomerClaimSettings), and can move the shop
 * to claims only. That switch only ever goes on here: a request to turn it off is ignored.
 */
class UpdateCustomerClaimSettings extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-admin.'.$this->shop->organisation_id);
    }

    public function rules(): array
    {
        return [
            'self_service'         => ['required', 'boolean'],
            'auto_approve'         => ['required', 'boolean'],
            'max_claim_amount'     => ['required', 'numeric', 'min:0', 'max:100000'],
            'window_days'          => ['required', 'integer', 'min:1', 'max:365'],
            'allowance_floor'      => ['required', 'numeric', 'min:0', 'max:100000'],
            'allowance_share'      => ['required', 'numeric', 'min:0', 'max:100'],
            'max_claims_in_window' => ['required', 'integer', 'min:1', 'max:1000'],
            'framework'            => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $modelData
     */
    public function handle(Shop $shop, array $modelData): Shop
    {
        $settings = $shop->settings ?? [];
        $current = GetCustomerClaimSettings::for($shop);
        data_set($settings, 'claims', array_merge(
            Arr::except($current, ['framework', 'framework_since']),
            Arr::except($modelData, ['framework', 'framework_since']),
            ['framework_since' => $current['framework_since'] ?? (!empty($modelData['framework']) ? now()->toIso8601String() : null)]
        ));
        $shop->update(['settings' => $settings]);

        return $shop;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop, $this->validatedData);

        return back();
    }
}
