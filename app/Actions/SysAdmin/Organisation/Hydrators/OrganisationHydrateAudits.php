<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 24-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Enums\Helpers\Audit\AuditUserTypeEnum;
use App\Models\Helpers\Audit;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateAudits implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_audits' => DB::table('audits')->select('organisation_id')->where('organisation_id', $organisation->id)->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'audits',
                field: 'event',
                enum: AuditEventEnum::class,
                models: Audit::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
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
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );

        foreach (AuditUserTypeEnum::cases() as $case) {
            foreach (AuditEventEnum::cases() as $case2) {
                if ($case2 == AuditEventEnum::MIGRATED and $case != AuditUserTypeEnum::SYSTEM) {
                    continue;
                }
                $stats["number_audits_user_type_{$case->snake()}_event_{$case2->snake()}"] = DB::table('audits')
                    ->selectRaw('organisation_id, user_type, event')
                    ->where('organisation_id', $organisation->id)
                    ->where('user_type', $case->value)
                    ->where('event', $case2->value)
                    ->count();
            }
        }

        $organisation->stats->update($stats);
    }

    public string $commandSignature = 'hydrate:organisation_audits';

    public function asCommand($command): void
    {
        $organisations = Organisation::all();

        foreach ($organisations as $organisation) {
            $this->handle($organisation);
        }
    }
}
