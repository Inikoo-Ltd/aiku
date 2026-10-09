<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Group;
use App\Services\SeoApi\SeoApiBudget;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The monthly budget for all SEO APIs together, kept in the group settings.
 */
class UpdateSeoApiBudget extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(['group-webmaster.edit', 'sysadmin.edit']);
    }

    public function handle(Group $group, float $budget): Group
    {
        $settings = $group->settings ?? [];
        data_set($settings, SeoApiBudget::SETTING, round($budget, 2));
        $group->update(['settings' => $settings]);

        return $group;
    }

    public function rules(): array
    {
        return [
            'budget' => ['required', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    public function asController(ActionRequest $request): Group
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group(), (float) $this->validatedData['budget']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
