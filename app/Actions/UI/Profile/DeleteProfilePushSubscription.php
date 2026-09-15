<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:12:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteProfilePushSubscription extends OrgAction
{
    use AsAction;

    public function handle(User $user, string $endpoint): bool
    {
        return (bool) $user->pushSubscriptions()->where('endpoint', $endpoint)->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2000'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return [
            'deleted' => $this->handle($request->user(), $this->validatedData['endpoint'])
        ];
    }
}
