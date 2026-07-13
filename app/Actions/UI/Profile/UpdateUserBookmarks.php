<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Apr 2026 09:09:18 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Actions\UI\Grp\BreakUserUiProps;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

class UpdateUserBookmarks extends OrgAction
{
    /**
     * @param array{bookmarks: array<int, array{label: string, url: string, shop?: ?string, organisation?: ?string}>} $modelData
     */
    public function handle(User $user, array $modelData): User
    {
        $user->update(['bookmarks' => $modelData['bookmarks']]);

        BreakUserUiProps::run($user);

        return $user;
    }

    public function rules(): array
    {
        return [
            'bookmarks'                => ['present', 'array', 'max:50'],
            'bookmarks.*.label'        => ['required', 'string', 'max:255'],
            'bookmarks.*.url'          => ['required', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
            'bookmarks.*.shop'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'bookmarks.*.organisation' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): User
    {
        $this->initialisationFromGroup(app('group'), $request);

        return $this->handle($request->user(), $this->validatedData);
    }

    public function asAction(User $user, array $modelData): User
    {
        $this->asAction = true;
        $this->initialisationFromGroup(app('group'), $modelData);

        return $this->handle($user, $this->validatedData);
    }
}
