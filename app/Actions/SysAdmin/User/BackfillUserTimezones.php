<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class BackfillUserTimezones
{
    use AsAction;

    public string $commandSignature = 'users:backfill-timezones';

    public string $commandDescription = 'Give any user without a timezone the one of the organisation they work for';

    /**
     * Idempotent: only ever touches users whose timezone_id is null, so it is safe to run
     * again after an import creates users outside StoreUser.
     *
     * @return array{from_organisation: int, from_app_default: int}
     */
    public function handle(): array
    {
        $fromOrganisation = DB::update(
            "UPDATE users u
             SET timezone_id = organisation_timezone.timezone_id
             FROM (
                 SELECT DISTINCT ON (user_has_models.user_id)
                        user_has_models.user_id,
                        organisations.timezone_id
                 FROM user_has_models
                 JOIN employees ON employees.id = user_has_models.model_id
                 JOIN organisations ON organisations.id = employees.organisation_id
                 WHERE user_has_models.model_type = 'Employee'
                   AND organisations.timezone_id IS NOT NULL
                 ORDER BY user_has_models.user_id, employees.organisation_id
             ) AS organisation_timezone
             WHERE organisation_timezone.user_id = u.id
               AND u.timezone_id IS NULL"
        );

        $fromAppDefault = DB::update(
            'UPDATE users
             SET timezone_id = (SELECT id FROM timezones WHERE name = ? LIMIT 1)
             WHERE timezone_id IS NULL',
            [config('app.timezone')]
        );

        return [
            'from_organisation' => $fromOrganisation,
            'from_app_default'  => $fromAppDefault,
        ];
    }

    public function asCommand(Command $command): int
    {
        $backfilled = $this->handle();

        $command->info($backfilled['from_organisation'].' users given their organisation timezone');
        $command->info($backfilled['from_app_default'].' users given '.config('app.timezone').', no organisation found');

        return 0;
    }
}
