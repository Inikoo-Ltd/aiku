<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\BankPayee;

use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\OrgAction;
use App\Enums\Accounting\BankPayee\BankPayeeCategoryEnum;
use App\Enums\Accounting\BankTransaction\BankTransactionDirectionEnum;
use App\Models\Accounting\BankPayee;
use App\Models\Accounting\BankTransaction;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A person says once what a payee of the banks is: every line paid to that name, past and future, in every
 * organisation of the group, carries the category. A label only: no payment or invoice is touched.
 */
class SetBankPayeeCategory extends OrgAction
{
    use AuthorizesAccountingEdit;

    private ?User $user = null;

    public function handle(Organisation $organisation, array $modelData): BankPayee
    {
        $name = BankTransaction::where('organisation_id', $organisation->id)
            ->where('direction', BankTransactionDirectionEnum::OUT)
            ->where('payee_key', $modelData['payee_key'])
            ->min('counterparty_name');
        if ($name === null) {
            throw ValidationException::withMessages(['payee_key' => __('The banks of this organisation never paid this payee')]);
        }

        $category  = BankPayeeCategoryEnum::from($modelData['category']);
        $modelType = $modelData['model_type'] ?? null;
        $modelId   = $modelData['model_id'] ?? null;
        if ($modelType && $modelId) {
            $linked = in_array($modelType, $category->linkTypes(), true)
                && Relation::getMorphedModel($modelType)::where('id', $modelId)->where('group_id', $organisation->group_id)->exists();
            if ($linked && $modelType === 'Employee' && $this->user) {
                $linked = $this->user->authTo('accounting.'.Employee::where('id', $modelId)->value('organisation_id').'.edit');
            }
            if (!$linked) {
                throw ValidationException::withMessages(['model_id' => __('This cannot be linked to a payee of that kind')]);
            }
        }

        $bankPayee = BankPayee::updateOrCreate(
            ['group_id' => $organisation->group_id, 'key' => $modelData['payee_key']],
            [
                'name'       => $name,
                'category'   => $category,
                'model_type' => $modelType && $modelId ? $modelType : null,
                'model_id'   => $modelType && $modelId ? $modelId : null,
                'source'     => 'person',
                'created_by' => $this->user?->id,
            ]
        );

        ClassifyBankOutgoing::run($organisation->group);

        return $bankPayee;
    }

    public function rules(): array
    {
        return [
            'payee_key'  => ['required', 'string', 'max:255'],
            'category'   => ['required', Rule::enum(BankPayeeCategoryEnum::class)],
            'model_type' => ['nullable', 'string', Rule::in(['Supplier', 'Agent', 'Employee', 'Organisation'])],
            'model_id'   => ['nullable', 'integer'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): BankPayee
    {
        $this->user = $request->user();
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): BankPayee
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
