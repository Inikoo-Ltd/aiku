<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission;

use App\Actions\OrgAction;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\SupplyChain\SupplierSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff move a submission along (under review, more information requested, accepted, not proceeding) with the
 * feedback the supplier reads on its page. Accepting applies it in aiku where it can be: the company details on the
 * supplier, pictures on the supplier product, a referral as a prospect of the shop chosen here, a compliance
 * document on the supplier. A new product idea or a price change is never applied by itself.
 */
class DecideSupplierSubmission extends OrgAction
{
    private SupplierSubmission $supplierSubmission;

    /**
     * @param  array{state: string, feedback?: string|null, shop_id?: int|null, fields?: array<int, string>|null, decided_by_user_id?: int|null}  $modelData
     */
    public function handle(SupplierSubmission $supplierSubmission, array $modelData): SupplierSubmission
    {
        $state = SupplierSubmissionStateEnum::from($modelData['state']);

        return DB::transaction(function () use ($supplierSubmission, $modelData, $state) {
            $applied = null;
            if ($state === SupplierSubmissionStateEnum::ACCEPTED && !$supplierSubmission->applied_at) {
                $applied = ApplySupplierSubmission::run($supplierSubmission, Arr::only($modelData, ['shop_id', 'fields']));
            }

            $supplierSubmission->update(array_filter([
                'state'              => $state,
                'feedback'           => Arr::get($modelData, 'feedback'),
                'decided_by_user_id' => Arr::get($modelData, 'decided_by_user_id'),
                'decided_at'         => now(),
                'applied'            => $applied,
                'applied_at'         => $applied !== null ? now() : null,
            ], fn ($value) => $value !== null));

            return $supplierSubmission;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->asAction || $request->user()->authTo('supply-chain.edit');
    }

    public function rules(): array
    {
        return [
            'state'    => ['required', Rule::enum(SupplierSubmissionStateEnum::class)->except([SupplierSubmissionStateEnum::DRAFT, SupplierSubmissionStateEnum::RECEIVED])],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'shop_id'  => ['nullable', 'integer', Rule::exists('shops', 'id')->where('group_id', $this->group->id)],
            'fields'   => ['nullable', 'array'],
            'fields.*' => ['string', Rule::in(ApplySupplierSubmission::COMPANY_FIELDS)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $state = SupplierSubmissionStateEnum::tryFrom((string) $this->get('state'));
        if ($state === SupplierSubmissionStateEnum::MORE_INFORMATION_REQUESTED && !$this->get('feedback')) {
            $validator->errors()->add('feedback', __('Tell the supplier what is missing'));
        }
        if ($state === SupplierSubmissionStateEnum::NOT_PROCEEDING && !$this->get('feedback')) {
            $validator->errors()->add('feedback', __('Tell the supplier why'));
        }
        if ($state === SupplierSubmissionStateEnum::ACCEPTED && $this->supplierSubmission->type === SupplierSubmissionTypeEnum::REFERRAL && !$this->get('shop_id') && !$this->supplierSubmission->applied_at) {
            $validator->errors()->add('shop_id', __('Choose the shop the prospect goes to'));
        }
        if ($this->supplierSubmission->state === SupplierSubmissionStateEnum::DRAFT) {
            $validator->errors()->add('state', __('The supplier has not sent this yet'));
        }
    }

    public function asController(SupplierSubmission $supplierSubmission, ActionRequest $request): SupplierSubmission
    {
        $this->supplierSubmission = $supplierSubmission;
        $this->initialisationFromGroup($supplierSubmission->group, $request);

        return $this->handle($supplierSubmission, [...$this->validatedData, 'decided_by_user_id' => $request->user()->id]);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public static function shopOptions(int $groupId): array
    {
        return Shop::where('group_id', $groupId)->where('state', 'open')->orderBy('name')->get(['id', 'code', 'name'])
            ->map(fn (Shop $shop) => ['value' => $shop->id, 'label' => $shop->name.' ('.$shop->code.')'])->all();
    }
}
