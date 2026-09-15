<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 12:12:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use App\Models\SysAdmin\UserPushSubscription;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreProfilePushSubscription extends OrgAction
{
    use AsAction;

    public function handle(User $user, array $modelData): UserPushSubscription
    {
        return UserPushSubscription::updateOrCreate(
            ['endpoint' => $modelData['endpoint']],
            [
                'user_id'    => $user->id,
                'public_key' => $modelData['keys']['p256dh'],
                'auth_token' => $modelData['keys']['auth'],
                'user_agent' => $modelData['user_agent'] ?? null,
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'endpoint'    => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth'   => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        $subscription = $this->handle($request->user(), [
            ...$this->validatedData,
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);

        return ['id' => $subscription->id];
    }
}
