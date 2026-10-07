<?php

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Models\Inventory\Location;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrgPartnerCosmeticSettings extends OrgAction
{
    private OrgPartner $orgPartner;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("org-supervisor.{$this->organisation->id}.procurement");
    }

    public function handle(OrgPartner $orgPartner, array $modelData): OrgPartner
    {
        $orgPartner->update($modelData);

        return $orgPartner->refresh();
    }

    public function rules(): array
    {
        return [
            'split_cosmetics'                => ['sometimes', 'boolean'],
            'cosmetic_goods_out_location_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $locationId = $this->get('cosmetic_goods_out_location_id');
        if (!$locationId) {
            return;
        }

        if (!$this->orgPartner->goods_out_location_id) {
            $validator->errors()->add('cosmetic_goods_out_location_id', __('Set the partner goods out bay first'));

            return;
        }

        if ((int) $locationId === $this->orgPartner->goods_out_location_id) {
            $validator->errors()->add('cosmetic_goods_out_location_id', __('The cosmetic bay must be different from the partner goods out bay'));

            return;
        }

        $location = Location::find($locationId);

        if (!$location || $location->organisation_id !== $this->orgPartner->organisation_id) {
            $validator->errors()->add('cosmetic_goods_out_location_id', __('Location belongs to another organisation'));

            return;
        }
        if (!$location->is_goods_out) {
            $validator->errors()->add('cosmetic_goods_out_location_id', __('Location is not a goods out gathering location'));

            return;
        }

        $usedByAnotherBay = OrgPartner::where('organisation_id', $this->orgPartner->organisation_id)
            ->where('id', '!=', $this->orgPartner->id)
            ->withBay($location->id)
            ->exists();
        if ($usedByAnotherBay) {
            $validator->errors()->add('cosmetic_goods_out_location_id', __('Location is already the goods out bay of a partner'));
        }
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): OrgPartner
    {
        $this->orgPartner = $orgPartner;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function action(OrgPartner $orgPartner, array $modelData): OrgPartner
    {
        $this->asAction   = true;
        $this->orgPartner = $orgPartner;
        $this->initialisation($orgPartner->organisation, $modelData);

        return $this->handle($orgPartner, $this->validatedData);
    }
}
