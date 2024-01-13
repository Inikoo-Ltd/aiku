<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Apr 2023 09:57:38 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

use App\Actions\Helpers\Avatars\GetDiceBearAvatar;
use App\Actions\Market\Shop\StoreShop;
use App\Actions\SysAdmin\Group\StoreGroup;
use App\Actions\SysAdmin\Guest\StoreGuest;
use App\Actions\SysAdmin\Organisation\StoreOrganisation;
use App\Actions\Web\Website\StoreWebsite;
use App\Models\Market\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Guest;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
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
            env('DB_DATABASE_TEST', 'aiku_testing'),
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
    GetDiceBearAvatar::mock()
        ->shouldReceive('handle')
        ->andReturn(Storage::disk('art')->get('avatars/shapes.svg'));

    $group = Group::first();
    if (!$group) {
        $group = StoreGroup::make()->action(Group::factory()->definition());
    }

    $organisation = Organisation::first();
    if (!$organisation) {
        $modelData = Organisation::factory()->definition();
        data_set($modelData, 'code', 'acme');
        $organisation = StoreOrganisation::make()->action($group, $modelData);
    }

    return $organisation;
}

function createAdminGuest(Group $group): Guest
{
    $guest = Guest::first();
    if (!$guest) {
        app()->instance('group', $group);
        setPermissionsTeamId($group->id);
        $guest = StoreGuest::make()
            ->action(
                $group,
                array_merge(
                    Guest::factory()->definition(),
                    [
                        'roles' => ['super-admin']
                    ]
                )
            );
    }

    return $guest;
}

function createShop(): array
{

    $organisation=createOrganisation();
    $adminGuest  =createAdminGuest($organisation->group);

    $shop            =Shop::first();
    if(!$shop) {
        $shop = StoreShop::run(
            $organisation,
            Shop::factory()->definition()
        );

        StoreWebsite::make()->action(
            $shop,
            Website::factory()->definition()
        );
        $shop->refresh();
    }



    return [
        $organisation,
        $adminGuest->user,
        $shop
    ];
}
