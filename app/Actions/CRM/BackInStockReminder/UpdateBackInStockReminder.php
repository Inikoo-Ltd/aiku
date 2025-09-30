<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-16h-31m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\BackInStockReminder;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBackInStockReminders;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\BackInStockReminder;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateBackInStockReminder extends OrgAction
{
    use WithActionUpdate;

    private BackInStockReminder $backInStockReminder;

    public function handle(BackInStockReminder $backInStockReminder, array $modelData): BackInStockReminder
    {
        $backInStockReminder = $this->update($backInStockReminder, $modelData, ['data']);

        $changes = Arr::except($backInStockReminder->getChanges(), ['updated_at', 'last_fetched_at']);

        if (count($changes) > 0) {
            CustomerHydrateBackInStockReminders::dispatch($backInStockReminder->customer)->delay($this->hydratorsDelay);
            ProductHydrateCustomersWhoReminded::dispatch($backInStockReminder->product)->delay($this->hydratorsDelay);
            ProductHydrateCustomersWhoRemindedInCategories::dispatch($backInStockReminder->product)->delay($this->hydratorsDelay);
        }
        return $backInStockReminder;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        $rules = [];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }
        return $rules;

    }

    public function action(BackInStockReminder $backInStockReminder, array $modelData, int $hydratorsDelay = 0, bool $strict = true): BackInStockReminder
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->backInStockReminder       = $backInStockReminder;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($backInStockReminder->organisation, $modelData);

        return $this->handle($backInStockReminder, $this->validatedData);
    }


}
