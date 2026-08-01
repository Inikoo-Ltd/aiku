<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        DB::statement(
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

        DB::statement(
            'UPDATE users
             SET timezone_id = (SELECT id FROM timezones WHERE name = ? LIMIT 1)
             WHERE timezone_id IS NULL',
            [config('app.timezone')]
        );
    }

    public function down(): void
    {
        DB::table('users')->update(['timezone_id' => null]);
    }
};
