<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\LeaveApprover;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreLeaveApprover extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, array $modelData): Collection
    {
        $userId = (int) $modelData['user_id'];
        $organisationIds = collect($modelData['organisation_ids'] ?? [])
            ->filter(fn ($organisationId) => $organisationId !== null && $organisationId !== '')
            ->map(fn ($organisationId) => (int) $organisationId)
            ->unique()
            ->values();

        if ($organisationIds->isEmpty()) {
            $organisationIds = collect([
                isset($modelData['organisation_id']) && $modelData['organisation_id'] !== null
                    ? (int) $modelData['organisation_id']
                    : $organisation->id,
            ]);
        }

        $user = User::findOrFail($userId);

        $modelData['user_id'] = $userId;
        $modelData['name'] = $user->contact_name
            ?? $user->username
            ?? $user->email
            ?? (string) $user->id;

        return $organisationIds->map(function (int $organisationId) use ($modelData) {
            return LeaveApprover::query()->updateOrCreate(
                [
                    'user_id' => $modelData['user_id'],
                    'organisation_id' => $organisationId,
                    'sequence_number' => $modelData['sequence_number'],
                ],
                [
                    'name' => $modelData['name'],
                    'description' => $modelData['description'] ?? null,
                    'is_active' => $modelData['is_active'] ?? true,
                ]
            );
        });
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id'],
            'organisation_id' => ['sometimes', 'nullable', Rule::exists('organisations', 'id')->where('group_id', $this->organisation->group_id)],
            'organisation_ids' => ['sometimes', 'array', 'min:1'],
            'organisation_ids.*' => ['integer', 'distinct', Rule::exists('organisations', 'id')->where('group_id', $this->organisation->group_id)],
            'sequence_number' => ['required', 'integer', 'min:0', 'max:5'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): Collection
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, array $modelData): Collection
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $this->validatedData);
    }

    public function htmlResponse(Collection $leaveApprovers): RedirectResponse
    {
        return Redirect::back()
            ->with('notification', [
                'status' => 'success',
                'title' => __('Success!'),
                'description' => $leaveApprovers->count() > 1
                    ? __('Leave approvers successfully saved for :count organisations.', ['count' => $leaveApprovers->count()])
                    : __('Leave approver successfully saved.'),
            ]);
    }
}
