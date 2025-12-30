<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 13:01:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Holiday;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Holiday\HolidayTypeEnum;
use App\Models\HumanResources\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateHoliday extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Holiday $holiday, array $modelData): Holiday
    {
        if (!Arr::has($modelData, 'year') && Arr::has($modelData, 'from')) {
            $modelData['year'] = (int) date('Y', strtotime($modelData['from']));
        }

        $holiday->update($modelData);

        return $holiday->refresh();
    }

    public function rules(): array
    {
        return [
            'type'  => ['sometimes', 'required', Rule::enum(HolidayTypeEnum::class)],
            'year'  => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'label' => ['nullable', 'string', 'max:255'],
            'from'  => ['sometimes', 'required', 'date'],
            'to'    => ['sometimes', 'required', 'date', 'after_or_equal:from'],
            'data'  => ['nullable', 'array'],
        ];
    }

    public function action(Holiday $holiday, array $modelData): Holiday
    {
        return $this->handle($holiday, $modelData);
    }

    public function asController(Holiday $holiday, ActionRequest $request): Holiday
    {
        return $this->handle($holiday, $request->validated());
    }

    public function htmlResponse(Holiday $holiday): RedirectResponse
    {
        return Redirect::back()->with('success', __('Holiday updated successfully.'));
    }
}
