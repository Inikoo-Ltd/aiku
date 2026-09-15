<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use App\Models\SysAdmin\User;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Locks the webpages of a master family's shop families, but only on the websites the user picked:
 * pages sharing the family code elsewhere (e.g. translations still in progress) stay untouched.
 */
class LockMasterFamilyWebpages extends OrgAction
{
    private User $user;

    /**
     * @return array<int, Webpage>
     */
    public function handle(MasterProductCategory $masterFamily, User $user, array $modelData): array
    {
        $webpages = Webpage::whereIn('id', Arr::get($modelData, 'webpage_ids', []))
            ->whereIn('id', $masterFamily->productCategories()->whereNotNull('webpage_id')->select('webpage_id'))
            ->get();

        $lockedWebpages = [];
        foreach ($webpages as $webpage) {
            if (!$webpage->canManageLockBy($user)) {
                continue;
            }

            $lockedWebpages[] = LockWebpage::make()->action($webpage, $user, [
                'reason'                     => Arr::get($modelData, 'reason'),
                'note'                       => Arr::get($modelData, 'note'),
                'editors'                    => $webpage->isLocked() ? Arr::get($webpage->lock_data, 'editors', []) : [],
                'scope'                      => 'master_family',
                'master_product_category_id' => $masterFamily->id,
            ]);
        }

        return $lockedWebpages;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('masters.edit');
    }

    public function rules(): array
    {
        return [
            'webpage_ids'   => ['required', 'array', 'min:1'],
            'webpage_ids.*' => ['integer'],
            'reason'        => ['required', 'string', 'max:255'],
            'note'          => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): array
    {
        $this->user = $request->user();
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $request->user(), $this->validatedData);
    }

    public function action(MasterProductCategory $masterFamily, User $user, array $modelData): array
    {
        $this->asAction = true;
        $this->user     = $user;
        $this->initialisationFromGroup($masterFamily->group, $modelData);

        return $this->handle($masterFamily, $user, $this->validatedData);
    }
}
