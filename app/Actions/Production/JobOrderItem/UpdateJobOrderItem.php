<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 23:07:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrderItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Production\JobOrderItem\JobOrderItemStateEnum;
use App\Enums\Production\JobOrderItem\JobOrderItemStatusEnum;
use App\Models\CRM\WebUser;
use App\Models\Production\JobOrderItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateJobOrderItem extends OrgAction
{
    use WithActionUpdate;

    private JobOrderItem $jobOrderItem;

    public function handle(JobOrderItem $jobOrderItem, array $modelData): JobOrderItem
    {

        return $jobOrderItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("productions-view.{$this->organisation->id}");
    }

    public function rules(): array
    {
        return [
            'status' => [
                'sometimes',
                Rule::enum(JobOrderItemStatusEnum::class),
            ],
            'state' => [
                'sometimes',
                Rule::enum(JobOrderItemStateEnum::class),
            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'received_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function asController(JobOrderItem $jobOrderItem, ActionRequest $request): JobOrderItem
    {
        $this->jobOrderItem = $jobOrderItem;
        $this->initialisation($jobOrderItem->organisation, $request);

        return $this->handle($jobOrderItem, $this->validatedData);
    }

    public function action(JobOrderItem $jobOrderItem, array $modelData, int $hydratorsDelay = 0): JobOrderItem
    {
        $this->jobOrderItem = $jobOrderItem;
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($jobOrderItem->organisation, $modelData);

        return $this->handle($jobOrderItem, $this->validatedData);
    }
}
