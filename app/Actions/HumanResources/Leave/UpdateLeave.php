<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Holiday;
use App\Models\HumanResources\Leave;
use App\Models\HumanResources\LeaveApprover;
use App\Services\HumanResources\LeaveTypeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeave extends OrgAction
{
    private function isAdminRoute(): bool
    {
        return request()->routeIs('grp.org.hr.leaves.admin.update');
    }

    public function rules(): array
    {
        if ($this->isAdminRoute()) {
            return [
                'type'          => ['nullable', 'string'],
                'start_date'    => ['nullable', 'date'],
                'end_date'      => ['nullable', 'date', 'after_or_equal:start_date'],
                'reason'        => ['nullable', 'string', 'max:1000'],
                'attachments'   => ['nullable', 'array', 'max:5'],
                'attachments.*' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
            ];
        }

        return [
            'attachments'   => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ];
    }

    public function handle(Leave $leave, array $modelData): Leave
    {
        if ($this->isAdminRoute()) {
            return $this->handleAdminUpdate($leave, $modelData);
        }

        return $this->handleAttachmentUpdate($leave, $modelData);
    }

    private function handleAdminUpdate(Leave $leave, array $modelData): Leave
    {
        $updates = [];

        if (isset($modelData['type']) && $modelData['type'] !== $leave->type) {
            $leaveType = LeaveTypeResolver::findForOrganisationByCode(
                organisationId: $leave->organisation_id,
                code: $modelData['type'],
                onlyActive: true
            );

            if ($leaveType) {
                $updates['type'] = $modelData['type'];
                $updates['leave_type_id'] = $leaveType->id;
            }
        }

        $startDate = isset($modelData['start_date'])
            ? Carbon::parse($modelData['start_date'])
            : $leave->start_date;

        $endDate = isset($modelData['end_date'])
            ? Carbon::parse($modelData['end_date'])
            : $leave->end_date;

        if (isset($modelData['start_date'])) {
            $updates['start_date'] = $startDate;
        }

        if (isset($modelData['end_date'])) {
            $updates['end_date'] = $endDate;
        }

        if (array_key_exists('reason', $modelData)) {
            $updates['reason'] = $modelData['reason'];
        }

        if (isset($updates['start_date']) || isset($updates['end_date'])) {
            $updates['duration_days'] = $this->calculateDurationDays($startDate, $endDate, $leave->organisation_id);
        }

        if (!empty($updates)) {
            $leave->update($updates);
        }

        $this->syncAttachments($leave, $modelData);

        return $leave->refresh();
    }

    private function handleAttachmentUpdate(Leave $leave, array $modelData): Leave
    {
        $this->syncAttachments($leave, $modelData);

        return $leave->refresh();
    }

    private function syncAttachments(Leave $leave, array $modelData): void
    {
        if (empty($modelData['attachments'])) {
            return;
        }

        $leave->clearMediaCollection('attachments');

        foreach ($modelData['attachments'] as $file) {
            if ($file && is_object($file) && method_exists($file, 'getClientOriginalName')) {
                $leave->addMedia($file)
                    ->withProperties([
                        'group_id' => $leave->group_id,
                        'type'     => 'attachment',
                        'ulid'     => (string) Str::ulid(),
                    ])
                    ->toMediaCollection('attachments');
            }
        }
    }

    private function calculateDurationDays(Carbon $startDate, Carbon $endDate, int $organisationId): int
    {
        $days = 0;
        $current = $startDate->copy();

        $holidays = Holiday::query()
            ->where('organisation_id', $organisationId)
            ->whereDate('from', '<=', $endDate->toDateString())
            ->whereDate('to', '>=', $startDate->toDateString())
            ->get()
            ->flatMap(function ($holiday) {
                $dates = [];
                $day = Carbon::parse($holiday->from);
                $end = Carbon::parse($holiday->to);
                while ($day->lte($end)) {
                    $dates[$day->toDateString()] = true;
                    $day->addDay();
                }

                return $dates;
            });

        while ($current->lte($endDate)) {
            if ($current->isWeekday() && !isset($holidays[$current->toDateString()])) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    public function asController(ActionRequest $request): Leave
    {
        $leave = $request->route('leave');

        if (!$leave instanceof Leave) {
            $leave = Leave::query()->findOrFail((int) $leave);
        }

        $this->initialisation($leave->organisation, $request);

        if ($leave->status !== LeaveStatusEnum::PENDING) {
            abort(403, __('Only pending leaves can be edited.'));
        }

        if ($this->isAdminRoute()) {
            $isApprover = LeaveApprover::query()
                ->where('organisation_id', $leave->organisation_id)
                ->where('user_id', Auth::id())
                ->where('is_active', true)
                ->exists();

            if (!$isApprover) {
                abort(403, __('Only leave approvers can edit leave requests.'));
            }
        } else {
            $leave->loadMissing('leaveType');

            if (!LeaveTypeResolver::isMedical($leave->leaveType, $leave->type)) {
                abort(403, __('Only medical leave can be edited.'));
            }

            if (!$request->routeIs('grp.org.hr.leaves.update')) {
                $user = $request->user();
                $employee = $user?->employees->first();

                if (!$employee || $leave->employee_id !== $employee->id) {
                    abort(403, __('You can only edit your own leave requests.'));
                }
            }
        }

        return $this->handle($leave, $this->validatedData);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        $message = $this->isAdminRoute()
            ? __('Leave request updated successfully.')
            : __('Medical certificate updated successfully.');

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => $message,
        ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
