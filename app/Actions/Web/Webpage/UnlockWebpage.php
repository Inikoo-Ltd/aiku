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
use Lorisleiva\Actions\ActionRequest;

/**
 * Unlocks a webpage. The previous lock and the unlock reason stay in lock_data so the audit
 * trail shows who unlocked it and why; a sysadmin override must give a reason.
 */
class UnlockWebpage extends OrgAction
{
    use WithActionUpdate;

    private Webpage $webpage;
    private User $user;

    public function handle(Webpage $webpage, User $user, array $modelData): Webpage
    {
        return $this->update($webpage, [
            'locked_at'         => null,
            'locked_by_user_id' => null,
            'lock_data'         => [
                'unlocked_by_user_id' => $user->id,
                'unlocked_at'         => now()->toIso8601String(),
                'unlock_reason'       => Arr::get($modelData, 'reason'),
                'previous_lock'       => $webpage->lock_data,
            ],
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->webpage->isLocked() && $this->webpage->canManageLockBy($this->user);
    }

    public function rules(): array
    {
        $isOwner = $this->webpage->locked_by_user_id == $this->user->id;

        return [
            'reason' => [$isOwner ? 'sometimes' : 'required', 'nullable', 'string', 'max:255'],
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
