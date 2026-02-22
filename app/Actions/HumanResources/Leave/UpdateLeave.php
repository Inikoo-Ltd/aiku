<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Leave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeave extends OrgAction
{
    public function rules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5 * 1024)],
        ];
    }

    public function handle(Leave $leave, array $modelData): Leave
    {
        if (isset($modelData['attachments']) && !empty($modelData['attachments'])) {
            $leave->clearMediaCollection('attachments');

            foreach ($modelData['attachments'] as $file) {
                if ($file && is_object($file) && method_exists($file, 'getClientOriginalName')) {
                    $media = $leave->addMedia($file)->toMediaCollection('attachments');
                    $media->ulid = Str::ulid();
                    $media->save();
                }
            }
        }

        return $leave->refresh();
    }

    public function asController(ActionRequest $request): Leave
    {
        $leave = $request->route('leave');

        if (!$leave instanceof Leave) {
            $leaveId = is_numeric($leave) ? (int)$leave : null;

            if (!$leaveId) {
                throw (new ModelNotFoundException())->setModel(Leave::class, [$leave]);
            }

            $leave = Leave::query()->findOrFail($leaveId);
        }

        $this->initialisation($leave->organisation, $request);

        if ($leave->type !== LeaveTypeEnum::MEDICAL) {
            abort(403, __('Only medical leave can be edited.'));
        }

        if ($leave->status !== LeaveStatusEnum::PENDING) {
            abort(403, __('Only pending medical leave can be edited.'));
        }

        if (!$request->routeIs('grp.org.hr.leaves.update')) {
            $user = $request->user();
            $employee = $user?->employees->first();

            if (!$employee || $leave->employee_id !== $employee->id) {
                abort(403, __('You can only edit your own leave requests.'));
            }
        }

        return $this->handle($leave, $this->validatedData);
    }

    public function htmlResponse(Leave $leave, ActionRequest $request): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => __('Medical certificate updated successfully.'),
            ]);
    }

    public function jsonResponse(Leave $leave): LeaveResource
    {
        return LeaveResource::make($leave);
    }
}
