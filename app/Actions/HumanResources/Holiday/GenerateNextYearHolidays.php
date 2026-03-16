<?php

namespace App\Actions\HumanResources\Holiday;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class GenerateNextYearHolidays extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, int $targetYear): int
    {
        $sourceYear = $targetYear - 1;

        $sourceHolidays = $organisation->holidays()
            ->where('year', $sourceYear)
            ->get()
            ->filter(function ($holiday) {
                return (bool) ($holiday->data['is_recurring'] ?? false);
            })
            ->values();

        if ($sourceHolidays->isEmpty()) {
            return 0;
        }

        $created = 0;

        foreach ($sourceHolidays as $holiday) {
            $from = $holiday->from?->copy()->year($targetYear);
            $to = $holiday->to?->copy()->year($targetYear);

            $exists = $organisation->holidays()
                ->where('year', $targetYear)
                ->where('type', $holiday->type->value)
                ->where('label', $holiday->label)
                ->whereDate('from', $from)
                ->whereDate('to', $to)
                ->exists();

            if ($exists) {
                continue;
            }

            $organisation->holidays()->create([
                'group_id' => $organisation->group_id,
                'type'     => $holiday->type->value,
                'year'     => $targetYear,
                'label'    => $holiday->label,
                'from'     => $from,
                'to'       => $to,
                'data'     => $holiday->data,
            ]);

            $created++;
        }

        return $created;
    }

    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        $data = $this->validatedData;

        $targetYear = (int) Arr::get($data, 'year', (int) now()->year);

        return $this->handle($organisation, $targetYear);
    }

    public function htmlResponse(int $created): RedirectResponse
    {
        if ($created === 0) {
            request()->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('No holidays generated'),
                'description' => __('No recurring holidays found in the previous year.'),
            ]);
        } else {
            request()->session()->flash('notification', [
                'status'      => 'success',
                'title'       => __('Success!'),
                'description' => __('Generated :count holidays from previous year.', ['count' => $created]),
            ]);
        }

        return Redirect::back();
    }
}
