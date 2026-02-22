<?php

namespace App\Http\Requests\HumanResources\Leave;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportLeaveReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string', Rule::in(LeaveTypeEnum::values())],
            'status' => ['nullable', 'string', Rule::in(LeaveStatusEnum::values())],
            'department' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer', 'exists:human_resources_employees,id'],
            'format' => ['required', 'string', Rule::in(['csv', 'xlsx'])],
        ];
    }

    public function messages(): array
    {
        return [
            'to.after_or_equal' => __('The end date must be after or equal to the start date.'),
            'type.in' => __('The selected leave type is invalid.'),
            'status.in' => __('The selected leave status is invalid.'),
            'format.in' => __('The selected export format is invalid.'),
        ];
    }
}
