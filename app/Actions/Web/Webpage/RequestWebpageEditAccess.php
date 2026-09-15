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

/**
 * A user who cannot edit a locked webpage asks its owner for temporary edit access.
 * One pending request per user is kept in lock_data; asking again replaces it.
 */
class RequestWebpageEditAccess extends OrgAction
{
    use WithActionUpdate;

    private Webpage $webpage;
    private User $user;

    public function handle(Webpage $webpage, User $user, array $modelData): Webpage
    {
        $lockData             = $webpage->lock_data ?? [];
        $lockData['requests'] = collect(Arr::get($lockData, 'requests', []))
            ->reject(fn (array $accessRequest) => $accessRequest['user_id'] == $user->id)
            ->push([
                'user_id'      => $user->id,
                'note'         => Arr::get($modelData, 'note'),
                'requested_at' => now()->toIso8601String(),
            ])
            ->values()->all();

        $lockData['declined_requests'] = collect(Arr::get($lockData, 'declined_requests', []))
            ->reject(fn (array $declinedRequest) => $declinedRequest['user_id'] == $user->id)
            ->values()->all();

        $webpage = $this->update($webpage, ['lock_data' => $lockData]);

        $requesterName = $user->contact_name ?: $user->username;
        NotifyWebpageEditAccess::run(
            $webpage,
            $webpage->lockedBy,
            $user,
            __(':user requests edit access to :webpage', ['user' => $requesterName, 'webpage' => $webpage->code]),
            Arr::get($modelData, 'note') ?: __('Open the webpage to allow or decline the request.')
        );

        return $webpage;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->webpage->isLocked() && !$this->webpage->canBeEditedBy($this->user);
    }

    public function rules(): array
    {
        return [
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
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
