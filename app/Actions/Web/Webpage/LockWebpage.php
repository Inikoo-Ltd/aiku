<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Locks a webpage, or rewrites the reason, note and edit grants of an existing lock.
 * The first lock records the owner; later saves keep the original owner and lock time.
 */
class LockWebpage extends OrgAction
{
    use WithActionUpdate;

    private Webpage $webpage;
    private User $user;

    public function handle(Webpage $webpage, User $user, array $modelData): Webpage
    {
        $editors = collect(Arr::get($modelData, 'editors', []))->map(fn (array $grant) => [
            'user_id'       => (int) $grant['user_id'],
            'until'         => Arr::get($grant, 'until'),
            'until_publish' => (bool) Arr::get($grant, 'until_publish', false),
        ])->values()->all();

        return $this->update($webpage, [
            'locked_at'         => $webpage->locked_at ?? now(),
            'locked_by_user_id' => $webpage->locked_by_user_id ?? $user->id,
            'lock_data'         => [
                'reason'  => Arr::get($modelData, 'reason'),
                'note'    => Arr::get($modelData, 'note'),
                'editors'  => $editors,
                'requests' => $webpage->isLocked() ? Arr::get($webpage->lock_data, 'requests', []) : [],
                'declined_requests' => $webpage->isLocked() ? Arr::get($webpage->lock_data, 'declined_requests', []) : [],
                'scope'    => Arr::get($modelData, 'scope', $webpage->isLocked() ? Arr::get($webpage->lock_data, 'scope', 'webpage') : 'webpage'),
                'master_product_category_id' => Arr::get($modelData, 'master_product_category_id', $webpage->isLocked() ? Arr::get($webpage->lock_data, 'master_product_category_id') : null),
            ],
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->webpage->canEditLockBy($this->user);
    }

    public function rules(): array
    {
        return [
            'reason'                  => ['required', 'string', 'max:255'],
            'note'                    => ['sometimes', 'nullable', 'string', 'max:2000'],
            'editors'                 => ['sometimes', 'array'],
            'editors.*.user_id'       => ['required', 'integer', 'exists:users,id'],
            'editors.*.until'         => ['sometimes', 'nullable', 'date'],
            'editors.*.until_publish' => ['sometimes', 'boolean'],
            'scope'                   => ['sometimes', Rule::in(['webpage', 'master_family'])],
            'master_product_category_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->webpage = $webpage;
        $this->user    = $request->user();
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $request->user(), $this->validatedData);
    }

    public function action(Webpage $webpage, User $user, array $modelData): Webpage
    {
        $this->asAction = true;
        $this->webpage  = $webpage;
        $this->user     = $user;
        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $user, $this->validatedData);
    }
}
