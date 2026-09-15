<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\StaffTask\Json;

use App\Http\Resources\Chat\StaffTaskResource;
use App\Models\Chat\StaffTask;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetStaffTasks
{
    use AsAction;

    public const array VIEWS = ['mine', 'department', 'requested', 'model'];

    public function handle(User $user, string $view = 'mine', bool $closed = false, ?string $modelType = null, ?int $modelId = null): Collection
    {
        return StaffTask::query()
            ->where('group_id', $user->group_id)
            ->when($view === 'mine', fn (Builder $query) => $query->where('assignee_id', $user->id))
            ->when($view === 'department', fn (Builder $query) => $query->whereIn('department', StaffTask::departmentsOf($user)))
            ->when($view === 'requested', fn (Builder $query) => $query->where('requester_id', $user->id))
            ->when($view === 'model', fn (Builder $query) => $query->where('model_type', $modelType)->where('model_id', $modelId))
            ->when($closed, fn (Builder $query) => $query->whereNotNull('closed_at')->orderByDesc('closed_at'), fn (Builder $query) => $query->open()->orderByRaw('due_at asc nulls last, id asc'))
            ->with(['requester.image', 'assignee.image', 'conversation', 'model'])
            ->limit(200)
            ->get();
    }

    public function rules(): array
    {
        return [
            'view'   => ['sometimes', Rule::in(self::VIEWS)],
            'closed' => ['sometimes', 'boolean'],
            'model_type' => ['required_if:view,model', Rule::in(StaffTask::LINKABLE_MODELS)],
            'model_id'   => ['required_if:view,model', 'integer'],
        ];
    }

    public function asController(ActionRequest $request): AnonymousResourceCollection
    {
        return StaffTaskResource::collection($this->handle($request->user(), $request->validated('view', 'mine'), (bool) $request->validated('closed', false), $request->validated('model_type'), $request->validated('model_id')));
    }
}
