<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class DeclineWebpageEditAccess extends OrgAction
{
    use WithActionUpdate;

    private Webpage $webpage;
    private User $user;

    public function handle(Webpage $webpage, User $approver, array $modelData): Webpage
    {
        $requesterId = (int) Arr::get($modelData, 'user_id');

        $lockData             = $webpage->lock_data ?? [];
        $lockData['requests'] = collect(Arr::get($lockData, 'requests', []))
            ->reject(fn (array $accessRequest) => $accessRequest['user_id'] == $requesterId)
            ->values()->all();
        $lockData['declined_requests'] = collect(Arr::get($lockData, 'declined_requests', []))
            ->reject(fn (array $declinedRequest) => $declinedRequest['user_id'] == $requesterId)
            ->push([
                'user_id'             => $requesterId,
                'declined_by_user_id' => $approver->id,
                'declined_at'         => now()->toIso8601String(),
                'message'             => Arr::get($modelData, 'message'),
            ])
            ->values()->all();

        $webpage = $this->update($webpage, ['lock_data' => $lockData]);

        NotifyWebpageEditAccess::run(
            $webpage,
            User::find($requesterId),
            $approver,
            __('Edit access to :webpage declined', ['webpage' => $webpage->code]),
            Arr::get($modelData, 'message') ?: $webpage->lockMessage()
        );

        return $webpage;
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
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
