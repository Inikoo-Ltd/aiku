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
            'split_gb_origin'                => ['sometimes', 'boolean'],
            'gb_goods_out_location_id'       => ['sometimes', 'nullable', 'integer'],
            'next_shipment_on'               => ['sometimes', 'nullable', 'date'],
            'shipment_every_days'            => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $bays = [
            'cosmetic_goods_out_location_id' => __('cosmetic'),
            'gb_goods_out_location_id'       => __('GB'),
        ];

        foreach ($bays as $field => $label) {
            $locationId = $this->get($field);
            if ($locationId) {
                $this->validateSplitBay($validator, $field, $label, (int) $locationId);
            }
        }

        $cosmeticBayId = $this->has('cosmetic_goods_out_location_id') ? $this->get('cosmetic_goods_out_location_id') : $this->orgPartner->cosmetic_goods_out_location_id;
        $gbBayId       = $this->has('gb_goods_out_location_id') ? $this->get('gb_goods_out_location_id') : $this->orgPartner->gb_goods_out_location_id;
        if ($cosmeticBayId && (int) $cosmeticBayId === (int) $gbBayId) {
            $validator->errors()->add('gb_goods_out_location_id', __('The GB bay must be different from the cosmetic bay'));
        }
    }

    private function validateSplitBay(Validator $validator, string $field, string $label, int $locationId): void
    {
        if (!$this->orgPartner->goods_out_location_id) {
            $validator->errors()->add($field, __('Set the partner goods out bay first'));

            return;
        }

        if ($locationId === $this->orgPartner->goods_out_location_id) {
            $validator->errors()->add($field, __('The :bay bay must be different from the partner goods out bay', ['bay' => $label]));

            return;
        }

        $location = Location::find($locationId);

        if (!$location || $location->organisation_id !== $this->orgPartner->organisation_id) {
            $validator->errors()->add($field, __('Location belongs to another organisation'));

            return;
        }
        if (!$location->is_goods_out) {
            $validator->errors()->add($field, __('Location is not a goods out gathering location'));

            return;
        }

        $usedByAnotherBay = OrgPartner::where('organisation_id', $this->orgPartner->organisation_id)
            ->where('id', '!=', $this->orgPartner->id)
            ->withBay($location->id)
            ->exists();
        if ($usedByAnotherBay) {
            $validator->errors()->add($field, __('Location is already the goods out bay of a partner'));
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
