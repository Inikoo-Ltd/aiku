<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\SysAdmin\User\NotifyUserOnChosenChannels;
use App\Actions\Tasks\GetAikuAssistant;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\SysAdmin\User;
use App\Notifications\ShoppingAssistantGuruRefusedNotification;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * The guru closed the welcome, so it is never shown to them again, on any device. A guru who says
 * the role is not their job is recorded (shopping_assistant_guru_refused_at) and the managers of
 * their organisations are told, but they stay a guru and keep getting Merlin's questions: only a
 * manager changes who answers.
 */
class MarkShoppingAssistantGuruWelcomeSeen
{
    use AsAction;

    public const string REFUSED = 'shopping_assistant_guru_refused_at';

    public function handle(User $user, bool $refused = false): void
    {
        $alreadyRefused = data_get($user->settings, self::REFUSED);
        $user->update(['settings' => [
            ...($user->settings ?? []),
            GetShoppingAssistantTeamQuestionsForUser::WELCOME_SEEN => now()->toIso8601String(),
            ...($refused && !$alreadyRefused ? [self::REFUSED => now()->toIso8601String()] : []),
        ]]);

        if (!$refused || $alreadyRefused) {
            return;
        }

        foreach (GetShoppingAssistantTeamQuestionsForUser::guruOrganisations($user) as $organisation) {
            $notification = new ShoppingAssistantGuruRefusedNotification($user, $organisation);
            foreach (StoreShoppingAssistantSalesLead::managersOf($organisation, GetAikuAssistant::run($organisation->group_id)) as $manager) {
                try {
                    NotifyUserOnChosenChannels::run($manager, UserNotificationEnum::ASSISTANT_QUESTION, $notification, $notification->title(), $notification->body(), $notification->url());
                } catch (Throwable $exception) {
                    Log::warning('Telling manager '.$manager->id.' that a guru refused failed: '.$exception->getMessage());
                }
            }
        }
    }

    public function rules(): array
    {
        return ['refused' => ['sometimes', 'boolean']];
    }

    public function asController(ActionRequest $request): array
    {
        $this->handle($request->user(), $request->boolean('refused'));

        return ['seen' => true];
    }
}
