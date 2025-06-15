<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\User\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UserHydrateAudits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(User $user): string
    {
        return $user->id;
    }

    public function handle(User $user): void
    {
        $queryBase = DB::table('audits')
            ->where('user_id', $user->id)
            ->where('user_type', 'User');

        $stats = [
            'number_audits' => $queryBase->count(),
        ];

        foreach (AuditEventEnum::cases() as $case) {
            if ($case == AuditEventEnum::MIGRATED) {
                continue;
            }

            $stats["number_audits_event_{$case->snake()}"] = $queryBase->clone()
            ->where('event', $case)
            ->count();
        }

        $user->stats->update($stats);
    }

    public string $commandSignature = 'hydrate:user_audits';

    public function asCommand($command): void
    {
        $users = User::all();

        foreach ($users as $user) {
            $this->handle($user);
        }
    }

}
