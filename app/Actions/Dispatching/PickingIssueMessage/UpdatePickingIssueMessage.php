<?php

/*
 * author Arya Permana - Kirin
 * created on 14-07-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\PickingIssue;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\PickingIssueMessage\PickingIssueMessageTypeEnum;
use App\Models\Inventory\PickingIssue;
use App\Models\Inventory\PickingIssueMessage;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdatePickingIssueMessage extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(PickingIssueMessage $pickingIssueMessage, array $modelData): void
    {
        $this->update($pickingIssueMessage, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'message' => ['sometimes', 'string'],
            'user_id'        => [
                'sometimes',
                Rule::Exists('users', 'id')->where('group_id', $this->group->id)
            ],
            'type'  => ['sometimes',  Rule::enum(PickingIssueMessageTypeEnum::class)]

        ];

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(PickingIssueMessage $pickingIssueMessage, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($pickingIssueMessage->warehouse, $request);

        $this->handle($pickingIssueMessage, $this->validatedData);
    }
}
