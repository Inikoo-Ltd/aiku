<?php

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\OrgAction;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Arr;

class StoreWorkSchedule extends OrgAction
{
    public function handle(Model $parent, array $data): WorkSchedule
    {
        return DB::transaction(function () use ($parent, $data) {
            $schedule = $parent->workSchedules()->create([
                'name' => Arr::get($data, 'name', 'Shift Schedule'),
                'type' => Arr::get($data, 'type', 'shift'),
                'is_active' => true,
            ]);

            if (Arr::has($data, 'working_hours')) {
                app(UpdateWorkSchedule::class)->handle($schedule, [
                    'working_hours' => $data['working_hours'],
                ]);
            }

            return $schedule->load('days');
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-admin.' . $this->organisation->id
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['sometimes', 'in:default,shift'],
            'working_hours' => ['sometimes', 'array'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request)
    {
        $this->initialisation($organisation, $request);

        $schedule = $this->handle($organisation, $this->validatedData);

        return response()->json($schedule);
    }

    public function action(Model $parent, array $data = []): WorkSchedule
    {
        $this->asAction = true;
        return $this->handle($parent, $data);
    }
}
