<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Apr 2023 09:57:38 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Grouping\Group\StoreGroup;
use App\Actions\Grouping\Organisation\StoreOrganisation;
use App\Models\Grouping\Group;
use App\Models\Grouping\Organisation;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');

function loadDB($dumpName): void
{
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/../', '.env.testing');
    $dotenv->load();

    $process = new Process(
        [
            __DIR__.'/../devops/devel/reset_test_database.sh',
            env('DB_DATABASE_TEST', 'aiku_test'),
            env('DB_PORT'),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            $dumpName
        ]
    );
    $process->run();
}

function createOrganisation(): Organisation
{
    $group=Group::first();
    if(!$group) {
        $group = StoreGroup::make()->action(Group::factory()->definition());
    }

    $organisation = Organisation::first();
    if (!$organisation) {
        $organisation = StoreOrganisation::make()->action($group, Organisation::factory()->definition());
    }

    return $organisation;
}
