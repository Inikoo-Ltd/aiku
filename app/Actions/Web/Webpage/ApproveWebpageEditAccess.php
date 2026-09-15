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
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * The lock owner turns an edit access request into a temporary edit grant.
 * The grant lasts until the editor's next publish, one hour, or a chosen date; after that the
 * existing lock rules lock the page again for that editor.
 */
class ApproveWebpageEditAccess extends OrgAction
{
    use WithActionUpdate;

    private Webpage $webpage;
    private User $user;

    public function handle(Webpage $webpage, User $approver, array $modelData): Webpage
    {
        $editorId = (int) Arr::get($modelData, 'user_id');
        $mode     = Arr::get($modelData, 'mode');
        $until    = match ($mode) {
            'one_hour'   => now()->addHour()->toIso8601String(),
            'until_date' => Carbon::parse(Arr::get($modelData, 'until'))->toIso8601String(),
            default      => null,
        };

        $lockData             = $webpage->lock_data ?? [];
        $lockData['requests'] = collect(Arr::get($lockData, 'requests', []))
            ->reject(fn (array $accessRequest) => $accessRequest['user_id'] == $editorId)
            ->values()->all();
        $lockData['editors']  = collect(Arr::get($lockData, 'editors', []))
            ->reject(fn (array $grant) => $grant['user_id'] == $editorId)
            ->push([
                'user_id'             => $editorId,
                'until'               => $until,
                'until_publish'       => $mode === 'until_publish',
                'approved_by_user_id' => $approver->id,
            ])
            ->values()->all();

        $webpage = $this->update($webpage, ['lock_data' => $lockData]);

        $expiry = match ($mode) {
            'until_publish' => __('until your next publish'),
            default         => __('until :time', ['time' => Carbon::parse($until)->format('d M Y H:i')]),
        };
        NotifyWebpageEditAccess::run(
            $webpage,
            User::find($editorId),
            $approver,
            __('Edit access to :webpage allowed', ['webpage' => $webpage->code]),
            __('You can edit :webpage :expiry.', ['webpage' => $webpage->code, 'expiry' => $expiry])
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
            'mode'    => ['required', Rule::in(['until_publish', 'one_hour', 'until_date'])],
            'until'   => ['required_if:mode,until_date', 'nullable', 'date', 'after:now'],
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
