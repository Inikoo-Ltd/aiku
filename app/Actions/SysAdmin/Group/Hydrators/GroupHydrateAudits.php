<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Enums\Helpers\Audit\AuditUserTypeEnum;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateAudits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_audits' => DB::table('audits')->select('group_id')->where('group_id', $group->id)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'audits',
                field: 'event',
                enum: AuditEventEnum::class,
                models: Audit::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'audits',
                field: 'user_type',
                enum: AuditUserTypeEnum::class,
                models: Audit::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        foreach (AuditUserTypeEnum::cases() as $case) {
            foreach (AuditEventEnum::cases() as $case2) {
                if ($case2 == AuditEventEnum::MIGRATED and $case != AuditUserTypeEnum::SYSTEM) {
                    continue;
                }
                $stats["number_audits_user_type_{$case->snake()}_event_{$case2->snake()}"] = DB::table('audits')
                    ->selectRaw('group_id, user_type, event')
                    ->where('group_id', $group->id)
                    ->where('user_type', $case->value)
                    ->where('event', $case2->value)
                    ->count();
            }
        }

        $group->sysadminStats->update($stats);
    }

    public string $commandSignature = 'hydrate:group_audits';

    public function asCommand($command): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $this->handle($group);
        }
    }
}
