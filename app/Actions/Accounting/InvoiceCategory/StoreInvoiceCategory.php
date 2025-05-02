<?php

/*
 * author Arya Permana - Kirin
 * created on 20-11-2024-08h-53m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Accounting\InvoiceCategory;

use App\Actions\Helpers\Colour\GetRandomColour;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryStateEnum;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreInvoiceCategory extends OrgAction
{
    use WithNoStrictRules;



    private Organisation|Group $parent;

    /**
     * @throws \Throwable
     */
    public function handle(Group|Organisation $parent, array $modelData): InvoiceCategory
    {
        data_set($modelData, 'colour', GetRandomColour::run());
        if ($parent instanceof Organisation) {
            data_set($modelData, 'group_id', $parent->group_id);
        }
        return DB::transaction(function () use ($parent, $modelData) {
            /** @var InvoiceCategory $invoiceCategory */
            $invoiceCategory = $parent->invoiceCategories()->create($modelData);
            $invoiceCategory->stats()->create();
            $invoiceCategory->orderingIntervals()->create();
            $invoiceCategory->salesIntervals()->create();

            return $invoiceCategory;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}.accounting", "org-admin.".$this->organisation->id]);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->parent instanceof Organisation) {
            $this->set('currency_id', $this->parent->currency_id);
        }
    }

    public function htmlResponse(InvoiceCategory $invoiceCategory): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        return Redirect::route('grp.org.accounting.invoice-categories.show', ['organisation' => $invoiceCategory->organisation->slug, 'invoiceCategory' => $invoiceCategory->slug]);
    }

    public function rules(): array
    {
        $rules = [
            'name'               => ['required', 'string'],
            'state'              => ['sometimes', Rule::enum(InvoiceCategoryStateEnum::class)],
            'data'               => ['sometimes', 'array'],
            'settings'           => ['sometimes', 'array'],
            'currency_id'        => ['required', 'integer', 'exists:currencies,id'],
            'priority'           => ['sometimes', 'integer'],
            'type'               => ['required', Rule::enum(InvoiceCategoryTypeEnum::class)],
            'show_in_dashboards' => ['sometimes', 'boolean'],
            'organisation_id'    => ['nullable', 'integer', Rule::exists('organisations', 'id')->where('group_id', $this->group->id)],
        ];
        if (!$this->strict) {
            $rules['state'] = ['required', Rule::enum(InvoiceCategoryStateEnum::class)];
            $rules          = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Group|Organisation $parent, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): InvoiceCategory
    {
        if (!$audit) {
            InvoiceCategory::disableAuditing();
        }
        if ($parent instanceof Organisation) {
            $group = $parent->group;
        } else {
            $group = $parent;
        }
        $this->parent         = $parent;
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromGroup($group, $modelData);

        return $this->handle($parent, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, ActionRequest $request): InvoiceCategory
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }


}
