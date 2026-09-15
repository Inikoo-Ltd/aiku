<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 15 Sep 2026 17:30:00 Malaysia Time, Kuala Lumpur, Malaysia
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
 * Unlocks the webpages of a master family's shop families on the websites the user picked.
 * Pages not picked, or locked by someone the user cannot override, stay locked.
 */
class UnlockMasterFamilyWebpages extends OrgAction
{
    /**
     * @return array<int, Webpage>
     */
    public function handle(MasterProductCategory $masterFamily, User $user, array $modelData): array
    {
        $webpages = Webpage::whereIn('id', Arr::get($modelData, 'webpage_ids', []))
            ->whereIn('id', $masterFamily->productCategories()->whereNotNull('webpage_id')->select('webpage_id'))
            ->whereNotNull('locked_at')
            ->get();

        $unlockedWebpages = [];
        foreach ($webpages as $webpage) {
            if (!$webpage->canManageLockBy($user)) {
                continue;
            }

            $unlockedWebpages[] = UnlockWebpage::make()->action($webpage, $user, [
                'reason' => Arr::get($modelData, 'reason'),
            ]);
        }

        return $unlockedWebpages;
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
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): array
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $request->user(), $this->validatedData);
    }

    public function action(MasterProductCategory $masterFamily, User $user, array $modelData): array
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterFamily->group, $modelData);

        return $this->handle($masterFamily, $user, $this->validatedData);
    }
}
