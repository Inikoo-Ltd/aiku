<?php

/*
 * author Arya Permana - Kirin
 * created on 20-11-2024-09h-05m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateInvoiceCategory extends OrgAction
{
    use WithNoStrictRules;
    use WithActionUpdate;

    public function handle(InvoiceCategory $invoiceCategory, array $modelData): InvoiceCategory
    {
        return $this->update($invoiceCategory, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}.accounting", "org-admin.".$this->organisation->id]);
    }

    public function asController(InvoiceCategory $invoiceCategory, ActionRequest $request): InvoiceCategory
    {
        $this->initialisation($invoiceCategory->organisation, $request);

        return $this->handle($invoiceCategory, $this->validatedData);
    }


    public function rules(): array
    {
        $rules = [
            'name'               => ['sometimes', 'string'],
            'state'              => ['sometimes', Rule::enum(InvoiceCategoryStateEnum::class)],
            'data'               => ['sometimes', 'array'],
            'settings'           => ['sometimes', 'array'],
            'priority'           => ['sometimes', 'integer'],
            'show_in_dashboards' => ['sometimes', 'boolean'],
            'organisation_id'    => ['nullable', 'integer', Rule::exists('organisations', 'id')->where('group_id', $this->group->id)],
            'colour'             => ['sometimes', 'string'],

        ];
        if (!$this->strict) {
            $rules                = $this->noStrictUpdateRules($rules);
            $rules['type']        = ['sometimes', Rule::enum(InvoiceCategoryTypeEnum::class)];
            $rules['currency_id'] = ['sometimes', 'integer', 'exists:currencies,id'];
        }

        return $rules;
    }

    public function action(InvoiceCategory $invoiceCategory, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): InvoiceCategory
    {
        $this->asAction = true;
        if (!$audit) {
            InvoiceCategory::disableAuditing();
        }
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($invoiceCategory->group, $modelData);

        return $this->handle($invoiceCategory, $this->validatedData);
    }


}
