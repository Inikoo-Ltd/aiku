<x-mail::message :shop="$shop" :url="$shop_url">
{{ __('A leave request has been approved. Details of the request are as follows:') }}

## {{ __('Staff details') }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('Staff member') }} | {{ $leave->employee_name }} |
| {{ __('Department') }} | {{ $leave->employee?->getCurrentDepartment() ?? '-' }} |
</x-mail::table>

## {{ __('Request details') }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('Submitted') }} | {{ $leave->created_at?->format('d-M-Y H:i') }} |
| {{ __('Leave type') }} | {{ $leave->leaveType?->name ?? ucfirst($leave->type) }} |
| {{ __('For') }} | {{ $leave->start_date->format('d-M-Y') }}@if (!$leave->start_date->isSameDay($leave->end_date)) – {{ $leave->end_date->format('d-M-Y') }}@endif @if ($leave->is_half_day) {{ $leave->session }}@endif |
| {{ __('Duration') }} | {{ $leave->is_half_day ? 0.5 : $leave->duration_days }} {{ __('Day') }} |
| {{ __('Reason') }} | {{ $leave->reason ?: '-' }} |
</x-mail::table>

## {{ __('Allowance details') }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('Allowance year') }} | {{ $balance->period_start?->format('d-M-Y') ?? '-' }} |
| {{ __('Opening balance') }} | {{ $balance->contract?->annual_leave_days ?? '-' }} {{ __('Days') }} |
| {{ __('Remaining balance') }} | {{ $balance->annual_remaining }} {{ __('Days') }} |
| {{ __('Pending leave') }} | {{ $pendingLeaveDays ?: '-' }} |
</x-mail::table>

## {{ __('Approval details') }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('Approved by') }} | {{ $leave->approver?->contact_name ?? $leave->approver?->username ?? '-' }} |
| {{ __('Approved date/time') }} | {{ $leave->approved_at?->format('d-M-Y H:i') }} |
</x-mail::table>
</x-mail::message>
