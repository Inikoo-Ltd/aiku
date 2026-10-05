<?php

namespace App\Actions\HumanResources\EmployeeBulkEmail;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\EmployeeBulkEmail;
use App\Models\SysAdmin\Organisation;
use App\Services\HTMLSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SendEmployeeBulkEmail extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    /**
     * @param array{subject: string, body: string, employee_ids?: array<int, int>|null, attachments?: array<int, UploadedFile>|null} $modelData
     */
    public function handle(Organisation $organisation, array $modelData, ?int $senderId = null): EmployeeBulkEmail
    {
        $body        = app(HTMLSanitizer::class)->cleanEmail($modelData['body']);
        $attachments = $this->storeAttachments(Arr::get($modelData, 'attachments') ?? []);

        $employees = $organisation->employees()
            ->where('state', EmployeeStateEnum::WORKING)
            ->when(Arr::get($modelData, 'employee_ids'), fn ($query, array $employeeIds) => $query->whereIn('id', $employeeIds))
            ->get()
            ->filter(fn (Employee $employee): bool => (bool) $this->recipientEmail($employee));

        $employeeBulkEmail = EmployeeBulkEmail::create([
            'group_id'          => $organisation->group_id,
            'organisation_id'   => $organisation->id,
            'sender_id'         => $senderId,
            'subject'           => $modelData['subject'],
            'body'              => $body,
            'number_recipients' => $employees->count(),
            'number_pending'    => $employees->count(),
            'attachments'       => $attachments,
        ]);

        foreach ($employees as $employee) {
            $email = $this->recipientEmail($employee);
            SendEmployeeBulkEmailToEmployee::dispatch($employeeBulkEmail, $email, $employee->contact_name ?: ($employee->alias ?: $email));
        }

        if ($employees->isEmpty()) {
            SendEmployeeBulkEmailToEmployee::deleteAttachments($employeeBulkEmail);
        }

        return $employeeBulkEmail;
    }

    /**
     * @param array<int, UploadedFile> $files
     * @return array<int, array{path: string, name: string}>
     */
    private function storeAttachments(array $files): array
    {
        $directory = 'employee-bulk-emails/'.Str::uuid();

        return array_map(fn (UploadedFile $file): array => [
            'path' => $file->storeAs($directory, $file->hashName(), 'local'),
            'name' => $file->getClientOriginalName(),
        ], $files);
    }

    private function recipientEmail(Employee $employee): ?string
    {
        return $employee->work_email ?: $employee->email;
    }

    public function rules(): array
    {
        return [
            'subject'        => ['required', 'string', 'max:255'],
            'body'           => ['required', 'string'],
            'employee_ids'   => ['nullable', 'array'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->where('organisation_id', $this->organisation->id)],
            'attachments'    => ['nullable', 'array', 'max:5'],
            'attachments.*'  => ['file', 'max:5120'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): EmployeeBulkEmail
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData, $request->user()->id);
    }

    public function action(Organisation $organisation, array $modelData): EmployeeBulkEmail
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(EmployeeBulkEmail $employeeBulkEmail): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => $employeeBulkEmail->number_recipients ? 'success' : 'warning',
            'title'       => __('Bulk email'),
            'description' => trans_choice('Sending to :count employee|Sending to :count employees', $employeeBulkEmail->number_recipients),
        ]);

        return Redirect::back();
    }
}
