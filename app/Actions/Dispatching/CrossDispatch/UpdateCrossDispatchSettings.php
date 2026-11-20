<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\CrossDispatch;

use App\Actions\Dispatching\CrossDispatch\UI\IndexCrossDispatchSettings;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCrossDispatchSettings extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'organisations.edit',
            'org-admin.'.$this->organisation->id,
        ]);
    }

    /**
     * Each side switches only its own flag: `enabled` is the acting organisation's, whichever side of the pair it is.
     *
     * @throws \Throwable
     */
    public function handle(OrgPartner $orgPartner, Organisation $actingOrganisation, array $modelData): OrgPartner
    {
        abort_unless(IndexCrossDispatchSettings::directionsQuery($actingOrganisation)->whereKey($orgPartner->id)->exists(), 404);

        return DB::transaction(function () use ($orgPartner, $actingOrganisation, $modelData) {
            $orgPartner = OrgPartner::lockForUpdate()->findOrFail($orgPartner->id);

            if (array_key_exists('enabled', $modelData)) {
                $ownFlag             = $actingOrganisation->id === $orgPartner->organisation_id ? 'enabled_by_origin' : 'enabled_by_dispatcher';
                $modelData[$ownFlag] = (bool) $modelData['enabled'];
                unset($modelData['enabled']);
            }

            if (array_key_exists('dispatch_fee_per_parcel', $modelData)) {
                $modelData['dispatch_fee_per_parcel'] = (float) $modelData['dispatch_fee_per_parcel'];
            }

            $current = $orgPartner->crossDispatchSettings();
            $changes = array_filter(
                $modelData,
                fn ($value, $key) => $value != $current[$key],
                ARRAY_FILTER_USE_BOTH
            );

            if (!$changes) {
                return $orgPartner;
            }

            return $this->update($orgPartner, ['data' => ['cross_dispatch' => $changes]], ['data']);
        });
    }

    public function rules(): array
    {
        $options = OrgPartner::CROSS_DISPATCH_OPTIONS;

        return [
            'enabled'                 => ['sometimes', 'boolean'],
            'goods_price_basis'       => ['sometimes', Rule::in($options['goods_price_basis'])],
            'dispatch_fee_per_parcel' => ['sometimes', 'numeric', 'min:0', 'max:9999'],
            'shipping_pass_through'   => ['sometimes', Rule::in($options['shipping_pass_through'])],
            'settlement_period'       => ['sometimes', Rule::in($options['settlement_period'])],
            'allow_customs_crossing'  => ['sometimes', 'boolean'],
            'tax_treatment'           => ['sometimes', Rule::in($options['tax_treatment'])],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): OrgPartner
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $organisation, $this->validatedData);
    }

    public function action(OrgPartner $orgPartner, Organisation $actingOrganisation, array $modelData): OrgPartner
    {
        $this->asAction = true;
        $this->initialisation($actingOrganisation, $modelData);

        return $this->handle($orgPartner, $actingOrganisation, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
