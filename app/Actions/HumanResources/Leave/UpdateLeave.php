<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Http\Resources\HumanResources\LeaveResource;
use App\Models\HumanResources\Leave;
use Illuminate\Http\RedirectResponse;
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
        if (isset($modelData['attachments'])) {
            $leave->clearMediaCollection('attachments');

            foreach ($modelData['attachments'] as $file) {
                $media = $leave->addMedia($file)->toMediaCollection('attachments');
                $media->ulid = Str::ulid();
                $media->save();
            }
        }

        return $leave->refresh();
    }

    public function asController(Leave $leave, ActionRequest $request): Leave
    {
        $this->initialisation($leave->organisation, $request);

        if ($leave->type !== LeaveTypeEnum::MEDICAL) {
            abort(403, __('Only medical leave can be edited.'));
        }

        $user = $request->user();
        $employee = $user?->employees->first();

        if (!$employee || $leave->employee_id !== $employee->id) {
            abort(403, __('You can only edit your own leave requests.'));
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
