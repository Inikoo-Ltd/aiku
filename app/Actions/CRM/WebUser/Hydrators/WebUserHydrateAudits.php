<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 31 Dec 2024 01:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Hydrators;

use App\Enums\Helpers\Audit\AuditEventEnum;
use App\Models\CRM\WebUser;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WebUserHydrateAudits
{
    use AsAction;

    private WebUser $webUser;

    public function __construct(WebUser $webUser)
    {
        $this->webUser = $webUser;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->webUser->id))->dontRelease()];
    }

    public function handle(WebUser $webUser): void
    {
        $baseQuery = DB::table('audits')
            ->where('website_id', $webUser->website_id)
            ->where('customer_id', $webUser->customer_id)
            ->where('user_type', 'WebUser');

        $stats = [
            'number_audits' => $baseQuery->count(),
        ];

        foreach (AuditEventEnum::cases() as $case) {
            if ($case == AuditEventEnum::MIGRATED) {
                continue;
            }

            $stats["number_audits_event_{$case->snake()}"] = $baseQuery->clone()
            ->where('event', $case)
            ->count();
        }

        $webUser->stats->update($stats);
    }


}
