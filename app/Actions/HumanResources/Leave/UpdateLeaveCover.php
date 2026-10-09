<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use App\Notifications\LeaveCoverNotification;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeaveCover extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    private Leave $leave;

    /**
     * @param array{cover_employee_id?: int|null} $modelData
     */
    public function handle(Leave $leave, array $modelData): Leave
    {
        $previousCover = $leave->coverEmployee;
        $coverEmployeeId = $modelData['cover_employee_id'] ?? null;

        $leave->update([
            'cover_employee_id'     => $coverEmployeeId,
            'cover_has_permissions' => (bool) $coverEmployeeId,
        ]);
        $leave->load('coverEmployee');

        if ($previousCover && $previousCover->id !== $leave->coverEmployee?->id) {
            SyncLeaveCoverRoles::run($previousCover);
        }

        $this->startCover($leave, notify: $leave->coverEmployee?->id !== $previousCover?->id);

        return $leave;
    }

    /**
     * A cover chosen while the leave is still pending is only stored; it starts when the leave is approved.
     */
    public function startCover(Leave $leave, bool $notify = true): void
    {
        if (!$leave->coverEmployee) {
            return;
        }

        SyncLeaveCoverRoles::run($leave->coverEmployee);

        if ($notify && $leave->status === LeaveStatusEnum::APPROVED) {
            Notification::send(
                $leave->coverEmployee->users()->wherePivot('status', true)->get(),
                new LeaveCoverNotification($leave)
            );
        }

        AddLeaveCoverCollaborators::dispatch($leave);
    }

    public static function coverRules(Organisation $organisation, int $absentEmployeeId): array
    {
        return [
            'cover_employee_id' => [
                'nullable',
                'integer',
                Rule::notIn([$absentEmployeeId]),
                Rule::exists('employees', 'id')
                    ->where('organisation_id', $organisation->id)
                    ->where('state', EmployeeStateEnum::WORKING->value),
            ],
        ];
    }

    public static function isOnLeaveDuring(int $employeeId, Carbon|string $startDate, Carbon|string $endDate): bool
    {
        return Leave::where('employee_id', $employeeId)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereDate('start_date', '<=', Carbon::parse($endDate)->toDateString())
            ->whereDate('end_date', '>=', Carbon::parse($startDate)->toDateString())
            ->exists();
    }

    /**
     * @param iterable<int> $employeeIds
     * @return Collection<int, Collection<int, array{0: string, 1: string}>>
     */
    public static function approvedLeavePeriods(iterable $employeeIds, Carbon $endingFrom): Collection
    {
        return Leave::whereIn('employee_id', $employeeIds)
            ->where('status', LeaveStatusEnum::APPROVED)
            ->whereDate('end_date', '>=', $endingFrom->toDateString())
            ->get(['employee_id', 'start_date', 'end_date'])
            ->groupBy('employee_id')
            ->map(fn (Collection $leaves) => $leaves->map(fn (Leave $leave) => [$leave->start_date->toDateString(), $leave->end_date->toDateString()])->values());
    }

    public function rules(): array
    {
        return self::coverRules($this->organisation, $this->leave->employee_id);
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->leave->end_date->lt(today())) {
            $validator->errors()->add('cover_employee_id', __('This leave has ended, its cover can no longer be changed.'));
        }

        if ($this->get('cover_employee_id') && $this->leave->status !== LeaveStatusEnum::APPROVED) {
            $validator->errors()->add('cover_employee_id', __('Only approved leave can be covered.'));
        }

        if ($this->get('cover_employee_id') && self::isOnLeaveDuring((int) $this->get('cover_employee_id'), $this->leave->start_date, $this->leave->end_date)) {
            $validator->errors()->add('cover_employee_id', __('This colleague is on leave during this period.'));
        }
    }

    public function asController(Organisation $organisation, Leave $leave, ActionRequest $request): Leave
    {
        abort_unless($leave->organisation_id === $organisation->id, 404);
        $this->leave = $leave;
        $this->initialisation($organisation, $request);

        return $this->handle($leave, $this->validatedData);
    }

    public function htmlResponse(Leave $leave): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => $leave->coverEmployee
                ? __(':cover is covering for :name.', ['cover' => $leave->coverEmployee->contact_name, 'name' => $leave->employee_name])
                : __('Cover ended for :name.', ['name' => $leave->employee_name]),
        ]);
    }
}
