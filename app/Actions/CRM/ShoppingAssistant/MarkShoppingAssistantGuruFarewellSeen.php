<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Merlin no longer asks gurus (INI-574, 27 Nov 2026). Every person still named in an organisation's
 * or shop's settings.assistant_questions sees a farewell once, in aiku, and OK records it so it never
 * comes back. Remove this, GuruFarewellDialog.vue and the assistant_questions settings values once
 * everyone has seen it or after 30 days.
 */
class MarkShoppingAssistantGuruFarewellSeen
{
    use AsAction;

    public const string SEEN = 'shopping_assistant_guru_farewell_seen_at';

    public function handle(User $user): void
    {
        $user->update(['settings' => [...($user->settings ?? []), self::SEEN => now()->toIso8601String()]]);
    }

    public static function isDueFor(User $user): bool
    {
        if (data_get($user->settings, self::SEEN)) {
            return false;
        }

        $id = (string) $user->id;

        return Organisation::where('group_id', $user->group_id)
            ->where(fn ($query) => $query
                ->whereRaw("settings->'assistant_questions'->>'user_id' = ?", [$id])
                ->orWhereRaw("settings->'assistant_questions'->>'backup_user_id' = ?", [$id]))
            ->exists()
            || Shop::where('group_id', $user->group_id)->whereRaw("settings->'assistant_questions'->>'user_id' = ?", [$id])->exists();
    }

    /**
     * @return array{farewell: bool}
     */
    public function isDue(ActionRequest $request): array
    {
        return ['farewell' => self::isDueFor($request->user())];
    }

    /**
     * @return array{seen: bool}
     */
    public function asController(ActionRequest $request): array
    {
        $this->handle($request->user());

        return ['seen' => true];
    }
}
