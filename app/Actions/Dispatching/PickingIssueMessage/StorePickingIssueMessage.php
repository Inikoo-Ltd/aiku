<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingIssue;

use App\Actions\OrgAction;
use App\Enums\Dispatching\PickingIssueMessage\PickingIssueMessageTypeEnum;
use App\Models\Inventory\PickingIssue;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePickingIssueMessage extends OrgAction
{
    use AsAction;
    use WithAttributes;
    protected User $user;
    /**
     * @throws \Throwable
     */
    public function handle(PickingIssue $pickingIssue, array $modelData): void
    {
        data_set('group_id', $modelData,  $pickingIssue->group_id);
        data_set('organisation_id', $modelData,  $pickingIssue->organisation_id);
        $pickingIssue->messages()->create($modelData);
    }

    public function rules(): array
    {
        $rules = [
            'message' => ['required', 'string'],
            'user_id'        => [
                'required',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],
            'type'  => ['required',  Rule::enum(PickingIssueMessageTypeEnum::class)]

        ];

        return $rules;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->asAction && !$request->has('user_id')) {
            $this->set('user_id', $this->user->id);
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
