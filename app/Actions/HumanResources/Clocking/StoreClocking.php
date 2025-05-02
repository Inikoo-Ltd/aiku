<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 30 Aug 2022 12:46:32 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia F
 */

namespace App\Actions\HumanResources\Clocking;

use App\Actions\HumanResources\Clocking\Traits\SetClockingPhotoFromImage;
use App\Actions\HumanResources\Employee\Hydrators\EmployeeHydrateClockings;
use App\Actions\HumanResources\Timesheet\GetTimesheet;
use App\Actions\HumanResources\Timesheet\Hydrators\TimesheetHydrateTimeTrackers;
use App\Actions\HumanResources\TimeTracker\AddClockingToTimeTracker;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Guest\Hydrators\GuestHydrateClockings;
use App\Actions\Traits\WithBase64FileConverter;
use App\Actions\Traits\WithUpdateModelImage;
use App\Enums\HumanResources\Clocking\ClockingTypeEnum;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Http\Resources\HumanResources\ClockingHanResource;
use App\Http\Resources\HumanResources\ClockingResource;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreClocking extends OrgAction
{
    use WithBase64FileConverter;
    use WithUpdateModelImage;

    private Employee $employee;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction || $this->han) {
            return true;
        }

        if ($request->user() instanceof ClockingMachine) {
            $employeeWorkplace = $this->employee->workplaces()
                    ->wherePivot('workplace_id', $request->user()->workplace_id)
                    ->count() > 0;

            return ($this->organisation->id === $request->user()->organisation_id)
                && $employeeWorkplace
                && $this->employee->state === EmployeeStateEnum::WORKING;
        }

        return $request->user()->authTo("human-resources.{$this->organisation->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|User|Employee|Guest $generator, ClockingMachine|Workplace $parent, Employee|Guest $subject, array $modelData): Clocking
    {
        data_set($modelData, 'generator_type', class_basename($generator));
        data_set($modelData, 'generator_id', $generator->id);

        data_set($modelData, 'group_id', $parent->group_id);
        data_set($modelData, 'organisation_id', $parent->organisation_id);


        $uploadedPhoto = null;
        if (Arr::has($modelData, 'photo')) {
            /** @var UploadedFile $uploadedPhoto */
            $uploadedPhoto = Arr::pull($modelData, 'photo');
        }


        if (class_basename($parent::class) == 'ClockingMachine') {
            $modelData['clocking_machine_id'] = $parent->id;
            $modelData['workplace_id']        = $parent->workplace_id;
            $modelData['type']                = ClockingTypeEnum::CLOCKING_MACHINE;
        } else {
            $modelData['workplace_id'] = $parent->id;
            $modelData['type']         = ClockingTypeEnum::MANUAL;
        }
        data_set($modelData, 'clocked_at', now(), overwrite: false);

        $timesheet = GetTimesheet::run($subject, $modelData['clocked_at']);
        data_set($modelData, 'timesheet_id', $timesheet->id);


        $clocking = DB::transaction(function () use ($modelData, $subject, $timesheet, $uploadedPhoto) {
            /** @var Clocking $clocking */
            $clocking = $subject->clockings()->create($modelData);
            AddClockingToTimeTracker::run($timesheet, $clocking);

            $clocking->refresh();

            if ($uploadedPhoto) {
                SetClockingPhotoFromImage::run(
                    clocking: $clocking,
                    imagePath: $uploadedPhoto->getPathName(),
                    originalFilename: $uploadedPhoto->getClientOriginalName(),
                    extension: $uploadedPhoto->getClientOriginalExtension()
                );
            }

            TimesheetHydrateTimeTrackers::run($timesheet);

            return $clocking;
        });

        if ($subject instanceof Employee) {
            EmployeeHydrateClockings::dispatch($subject)->delay($this->hydratorsDelay);
        } else {
            GuestHydrateClockings::dispatch($subject)->delay($this->hydratorsDelay);
        }

        return $clocking;
    }

    public function jsonResponse(Clocking $clocking): ClockingResource|ClockingHanResource
    {
        if ($this->han) {
            return ClockingHanResource::make($clocking);
        }

        return ClockingResource::make($clocking);
    }

    public function rules(): array
    {
        $rules = [
            'clocked_at' => ['sometimes', 'required', 'date'],
            'photo'      => [
                'sometimes',
                'nullable'
            ],
        ];

        if (!$this->strict) {
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function asController(ClockingMachine|Workplace $parent, Employee|Guest $subject, ActionRequest $request): Clocking
    {
        $this->initialisation($parent->organisation, $request);

        return $this->handle($request->user(), $parent, $subject, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function han(Employee $employee, ActionRequest $request): Clocking
    {
        $this->han = true;


        if ($request->user()->organisation_id !== $employee->organisation_id) {
            abort(404);
        }
        if (in_array($employee->state, [EmployeeStateEnum::HIRED, EmployeeStateEnum::LEFT])) {
            abort(405);
        }
        $modelData = [];
        if ($request->has('photo')) {
            data_set(
                $modelData,
                'photo',
                $this->convertBase64ToFile($this->get('photo'), $employee)
            );
        }
        $this->employee  = $employee;
        $clockingMachine = $request->user();

        $this->initialisation($clockingMachine->organisation, $modelData);

        return $this->handle($employee, $clockingMachine, $employee, $this->validatedData);
    }

    public function htmlResponse(Clocking $clocking): RedirectResponse
    {
        if (!$clocking->clocking_machine_id) {
            return Redirect::route('grp.org.hr.workplaces.show.clockings.show', [
                $clocking->workplace->slug,
                $clocking->id
            ]);
        } else {
            return Redirect::route('grp.org.hr.workplaces.show.clocking_machines.show.clockings.show', [
                $clocking->workplace->slug,
                $clocking->clockingMachine->slug,
                $clocking->id
            ]);
        }
    }

    public function prepareForValidation(): void
    {
        if ($this->has('clocked_at') && is_string($this->get('clocked_at'))) {
            $this->set('clocked_at', Carbon::parse($this->get('clocked_at')));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation|User|Employee|Guest $generator, ClockingMachine|Workplace $parent, Employee|Guest $subject, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Clocking
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($parent->organisation, $modelData);

        return $this->handle($generator, $parent, $subject, $this->validatedData);
    }
}
