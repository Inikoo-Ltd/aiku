<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingIssue;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Inventory\PickingIssue;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ResolvePickingIssue extends OrgAction
{
    use AsAction;
    use WithAttributes;

    protected User $user;
    /**
     * @throws \Throwable
     */
    public function handle(PickingIssue $pickingIssue, array $modelData): void
    {
        UpdatePickingIssue::make()->action($pickingIssue, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'resolver_user_id'        => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],

        ];

        return $rules;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction && !$request->has('resolver_user_id')) {
            $this->set('resolver_user_id', $this->user->id);
        }
    }

    /**
     * @throws \Throwable
     */
    public function asController(PickingIssue $pickingIssue, ActionRequest $request): void
    {
        $this->user             = $request->user();
        $this->initialisationFromWarehouse($pickingIssue->warehouse, $request);

        $this->handle($pickingIssue, $this->validatedData);
    }
}
