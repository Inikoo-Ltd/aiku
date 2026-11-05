<x-mail::message :shop="$shop" :url="$shop_url">
{{ __('Hello :name,', ['name' => $leave->employee_name]) }}

{{ $isApproved ? __('Your leave request has been approved.') : __('Your leave request has been rejected.') }}

<x-mail::table>
| | |
|:--|:--|
| {{ __('Leave type') }} | {{ $leave->leaveType?->name ?? ucfirst($leave->type) }} |
| {{ __('For') }} | {{ $leave->start_date->format('d-M-Y') }}@if (!$leave->start_date->isSameDay($leave->end_date)) – {{ $leave->end_date->format('d-M-Y') }}@endif @if ($leave->is_half_day) {{ $leave->session }}@endif |
| {{ __('Duration') }} | {{ $leave->is_half_day ? 0.5 : $leave->duration_days }} {{ __('Day') }} |
| {{ $isApproved ? __('Approved by') : __('Rejected by') }} | {{ $leave->approver?->contact_name ?? $leave->approver?->username ?? '-' }} |
@unless ($isApproved)
| {{ __('Reason') }} | {{ $leave->rejection_reason ?: '-' }} |
@endunless
</x-mail::table>
</x-mail::message>
